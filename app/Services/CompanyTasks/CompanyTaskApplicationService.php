<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskApplicationRepositoryInterface;
use App\Interfaces\CompanyTaskAssignmentRepositoryInterface;
use App\Models\CompanyTaskApplication;
use App\Models\CompanyTaskAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyTaskApplicationService
{
    public function __construct(
        private readonly CompanyTaskApplicationRepositoryInterface $applicationRepository,
        private readonly CompanyTaskAssignmentRepositoryInterface $assignmentRepository
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

    public function acceptApplication(int $companyId,int $applicationId,array $data = []): CompanyTaskAssignment {
       $assignment = DB::transaction(function () use ($companyId, $applicationId, $data) {
       $application = $this->applicationRepository->findCompanyApplicationOrFail($companyId, $applicationId);

    $this->ensureApplicationIsPending($application);
    $this->ensureAcceptedLimitNotReached($application);
    $this->ensureAssignmentDoesNotExist($application);

    // $application = $this->applicationRepository->markAsAccepted($application, $data);

    // $assignment = $this->assignmentRepository->createFromApplication($application);

    // $this->taskAssignmentChatService->createForAssignment($assignment);

    return $assignment;
    });

    DB::afterCommit(function () use ($assignment) {
    // send notification
    });

    return $assignment;

            return $this->assignmentRepository->create([
                'company_task_id' => $application->company_task_id,
                'company_task_application_id' => $application->id,
                'student_user_id' => $application->student_user_id,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        }
        












    public function rejectApplication(
        int $companyId,
        int $applicationId,
        array $data = []
    ): CompanyTaskApplication {
        return DB::transaction(function () use ($companyId, $applicationId, $data) {
            $application = $this->applicationRepository->findCompanyApplicationOrFail(
                companyId: $companyId,
                applicationId: $applicationId
            );

            $this->ensureApplicationIsPending($application);

            return $this->applicationRepository->update($application, [
                'status' => 'rejected',
                'reviewed_at' => now(),
                'company_notes' => $data['company_notes'] ?? null,
            ]);
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