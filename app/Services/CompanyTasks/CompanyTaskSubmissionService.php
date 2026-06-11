<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskSubmissionRepositoryInterface;
use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanyTaskSubmissionService
{
    public function __construct(
        private readonly CompanyTaskSubmissionRepositoryInterface $submissionRepository
    ) {}

    public function submit(
        int $assignmentId,
        int $studentUserId,
        array $data
    ): CompanyTaskSubmission {
        $assignment = $this->submissionRepository
            ->findStudentAssignmentOrFail(
                $assignmentId,
                $studentUserId
            );

        $this->ensureAssignmentCanBeSubmitted($assignment);

        $this->ensureDeadlineHasNotPassed($assignment);

        $this->ensureNoExistingSubmission($assignmentId);

        $storedZipPath = null;

        try {
            if (isset($data['zip_file'])) {
                $storedZipPath = $this->storeZipFile(
                    $data['zip_file'],
                    $assignmentId
                );
            }

            $submission = DB::transaction(function () use (
                $assignment,
                $studentUserId,
                $data,
                $storedZipPath
            ): CompanyTaskSubmission {
                $submission = $this->submissionRepository->create([
                    'company_task_assignment_id' => $assignment->id,
                    'student_user_id' => $studentUserId,
                    'github_url' => $data['github_url'] ?? null,
                    'demo_url' => $data['demo_url'] ?? null,
                    'zip_file_path' => $storedZipPath,
                    'notes' => $data['notes'],
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]);

                $this->submissionRepository
                    ->markAssignmentAsSubmitted($assignment);

                return $submission;
            });

            return $submission->load([
                'assignment.task:id,company_id,title,deadline,submission_type',
                'student:id,name,email,profile_picture_url',
            ]);
        } catch (Throwable $exception) {
            if ($storedZipPath !== null) {
                Storage::disk('public')->delete($storedZipPath);
            }

            throw $exception;
        }
    }

    public function getStudentSubmission(
        int $assignmentId,
        int $studentUserId
    ): CompanyTaskSubmission {
        $this->submissionRepository
            ->findStudentAssignmentOrFail(
                $assignmentId,
                $studentUserId
            );

        return $this->findSubmissionOrFail($assignmentId);
    }

    public function getCompanySubmission(
        int $assignmentId,
        int $companyId
    ): CompanyTaskSubmission {
        $this->submissionRepository
            ->findCompanyAssignmentOrFail(
                $assignmentId,
                $companyId
            );

        return $this->findSubmissionOrFail($assignmentId);
    }

    private function ensureAssignmentCanBeSubmitted(
        CompanyTaskAssignment $assignment
    ): void {
        if ($assignment->status !== 'working') {
            throw ValidationException::withMessages([
                'assignment' => [
                    'لا يمكن تسليم هذه المهمة لأن حالة التنفيذ ليست قيد العمل. | This task cannot be submitted because the assignment is not in working status.',
                ],
            ]);
        }
    }

    private function ensureDeadlineHasNotPassed(
        CompanyTaskAssignment $assignment
    ): void {
        if (
            $assignment->task?->deadline !== null
            && $assignment->task->deadline->isPast()
        ) {
            throw ValidationException::withMessages([
                'deadline' => [
                    'انتهى الموعد النهائي للمهمة ولا يمكن إرسال التسليم. | The task deadline has passed and the submission cannot be sent.',
                ],
            ]);
        }
    }

    private function ensureNoExistingSubmission(
        int $assignmentId
    ): void {
        $existingSubmission = $this->submissionRepository
            ->findLatestByAssignment($assignmentId);

        if ($existingSubmission !== null) {
            throw ValidationException::withMessages([
                'submission' => [
                    'تم إرسال تسليم نهائي لهذه المهمة مسبقاً. | A final submission has already been sent for this assignment.',
                ],
            ]);
        }
    }

    private function findSubmissionOrFail(
        int $assignmentId
    ): CompanyTaskSubmission {
        $submission = $this->submissionRepository
            ->findLatestByAssignment($assignmentId);

        if ($submission === null) {
            throw ValidationException::withMessages([
                'submission' => [
                    'لا يوجد تسليم نهائي لهذه المهمة حتى الآن. | No final submission exists for this assignment yet.',
                ],
            ]);
        }

        return $submission;
    }

    private function storeZipFile(
        UploadedFile $zipFile,
        int $assignmentId
    ): string {
        return $zipFile->store(
            "company-task-submissions/{$assignmentId}",
            'public'
        );
    }
}
