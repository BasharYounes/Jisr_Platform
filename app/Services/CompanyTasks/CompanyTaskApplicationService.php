<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskApplicationRepositoryInterface;
use App\Interfaces\CompanyTaskAssignmentRepositoryInterface;
use App\Interfaces\CompanyTaskRepositoryInterface;
use App\Models\CompanyTaskApplication;
// use App\Models\CompanyTaskAssignment;
use App\Services\Conversations\TaskAssignmentConversationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Notification imports
use App\Jobs\SendFirebaseNotificationJob;
use App\Models\User;
//

class CompanyTaskApplicationService
{
    public function __construct(
        private readonly CompanyTaskApplicationRepositoryInterface $applicationRepository,
        private readonly CompanyTaskAssignmentRepositoryInterface $assignmentRepository,
        private readonly CompanyTaskRepositoryInterface $companyTaskRepository,
        private readonly TaskAssignmentConversationService $taskAssignmentConversationService,
    ) {}

    public function getTaskApplications(
        int $companyId,
        int $taskId
    ): Collection {
        return $this->applicationRepository->getByCompanyTask(
            companyId: $companyId,
            taskId: $taskId
        );
    }

    public function getApplicationDetails(
        int $companyId,
        int $applicationId
    ): CompanyTaskApplication {
        return $this->applicationRepository->findCompanyApplicantDetailsOrFail(
            companyId: $companyId,
            applicationId: $applicationId
        );
    }

    public function acceptApplication(
        int $companyId,
        int $applicationId,
        array $data = []
    ) {
        $assignment = DB::transaction(function () use (
            $companyId,
            $applicationId,
            $data
        ) {
            $application = $this->applicationRepository->findCompanyApplicationOrFail(
                $companyId,
                $applicationId
            );

            $this->ensureApplicationIsPending($application);
            $this->ensureTaskCanAcceptStudents($application);
            $this->ensureAcceptedLimitNotReached($application);
            $this->ensureAssignmentDoesNotExist($application);

            $application = $this->applicationRepository->update($application, [
                'status' => 'accepted',
                'reviewed_at' => now(),
                'company_notes' => $data['company_notes'] ?? null,
            ]);

            $assignment = $this->assignmentRepository->create([
                'company_task_id' => $application->company_task_id,
                'company_task_application_id' => $application->id,
                'student_user_id' => $application->student_user_id,
                'status' => 'working',
                'started_at' => now(),
            ]);

            $this->markTaskAsInProgressIfNeeded($application);

            $this->taskAssignmentConversationService->createForAssignment($assignment);

            $student = User::query()->findOrFail($application->student_user_id);
            $taskTitle = $application->task?->title ?? 'مهمة شركة';

            SendFirebaseNotificationJob::dispatch(
                $student,
                'تم قبول طلبك',
                "تم قبول طلبك على المهمة: {$taskTitle}",
                [
                    'type' => 'company_task_application_accepted',
                    'decision' => 'accepted',
                    'application_id' => (string) $application->id,
                    'company_task_id' => (string) $application->company_task_id,
                ],
            );

            return $assignment;
        });
    }

    private function ensureTaskCanAcceptStudents(
        CompanyTaskApplication $application
    ): void {
        $taskStatus = $application->task?->status;

        if (in_array($taskStatus, ['published', 'in_progress'], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'task' => [
                'لا يمكن قبول طلاب على تاسك في حالته الحالية. | Students cannot be accepted for this task in its current status.',
            ],
        ]);
    }

    private function markTaskAsInProgressIfNeeded(
        CompanyTaskApplication $application
    ): void {
        $task = $application->task;

        if ($task?->status !== 'published') {
            return;
        }

        $this->companyTaskRepository->update($task, [
            'status' => 'in_progress',
        ]);
    }

    // Reject application
    public function rejectApplication(int $companyId, int $applicationId, array $data = []): CompanyTaskApplication
    {
        return DB::transaction(function () use ($companyId, $applicationId, $data) {
            $application = $this->applicationRepository->findCompanyApplicationOrFail(
                companyId: $companyId,
                applicationId: $applicationId
            );

            $this->ensureApplicationIsPending($application);

            $application = $this->applicationRepository->update($application, [
                'status' => 'rejected',
                'reviewed_at' => now(),
                'company_notes' => $data['company_notes'] ?? null,
            ]);

            $student = User::query()->findOrFail($application->student_user_id);
            $taskTitle = $application->task?->title ?? 'مهمة شركة';

            SendFirebaseNotificationJob::dispatch(
                $student,
                'تم رفض طلبك',
                "تم رفض طلبك على المهمة: {$taskTitle}",
                [
                    'type' => 'company_task_application_rejected',
                    'decision' => 'rejected',
                    'application_id' => (string) $application->id,
                    'company_task_id' => (string) $application->company_task_id,
                ],
            );

            return $application;
        });
    }

    private function ensureApplicationIsPending(CompanyTaskApplication $application): void
    {
        if ($application->status !== 'pending') {
            throw ValidationException::withMessages([
                'application' => [
                    'لا يمكن اتخاذ قرار على طلب تمت مراجعته مسبقاً. | This application has already been reviewed.',
                ],
            ]);
        }
    }

    private function ensureAcceptedLimitNotReached(CompanyTaskApplication $application): void
    {
        $acceptedCount = $this->applicationRepository->countAcceptedForTask(
            taskId: $application->company_task_id
        );

        $maxAccepted = $application->task?->max_accepted_students;

        if ($maxAccepted !== null && $acceptedCount >= $maxAccepted) {
            throw ValidationException::withMessages([
                'task' => [
                    'تم الوصول إلى الحد الأقصى للطلاب المقبولين في هذه المهمة. | Maximum accepted students limit has been reached for this task.',
                ],
            ]);
        }
    }

    private function ensureAssignmentDoesNotExist(CompanyTaskApplication $application): void
    {
        if ($this->assignmentRepository->existsForApplication($application->id)) {
            throw ValidationException::withMessages([
                'application' => [
                    'يوجد تكليف سابق لهذا الطلب. | An assignment already exists for this application.',
                ],
            ]);
        }
    }
}
