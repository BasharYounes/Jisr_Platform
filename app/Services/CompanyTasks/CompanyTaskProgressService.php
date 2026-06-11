<?php

namespace App\Services\CompanyTasks;

use App\Interfaces\CompanyTaskProgressRepositoryInterface;
use App\Models\CompanyTaskProgressUpdate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanyTaskProgressService
{
    public function __construct(
        private readonly CompanyTaskProgressRepositoryInterface $progressRepository
    ) {}

    public function createProgressUpdate(
        int $assignmentId,
        int $studentUserId,
        array $data
    ): CompanyTaskProgressUpdate {
        $assignment = $this->progressRepository
            ->findStudentAssignmentOrFail(
                $assignmentId,
                $studentUserId
            );

        $this->ensureAssignmentAllowsProgress($assignment->status);

        $this->ensureProgressDoesNotDecrease(
            $assignmentId,
            (int) $data['progress_percentage']
        );

        $storedPaths = [];

        try {
            $storedPaths = $this->storeAttachments(
                $data['attachments'],
                $assignmentId
            );

            return DB::transaction(function () use (
                $assignmentId,
                $studentUserId,
                $data,
                $storedPaths
            ) {
                return $this->progressRepository->create([
                    'company_task_assignment_id' => $assignmentId,
                    'student_user_id' => $studentUserId,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'progress_percentage' => $data['progress_percentage'],
                    'github_url' => $data['github_url'] ?? null,
                    'demo_url' => $data['demo_url'] ?? null,
                    'attachments' => $storedPaths,
                ]);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }
    }

    public function getStudentProgressUpdates(
        int $assignmentId,
        int $studentUserId
    ): array {
        $assignment = $this->progressRepository
            ->findStudentAssignmentOrFail(
                $assignmentId,
                $studentUserId
            );

        return [
            'assignment' => $assignment,
            'updates' => $this->progressRepository
                ->getAssignmentProgressUpdates($assignmentId),
        ];
    }

    public function getCompanyProgressUpdates(
        int $assignmentId,
        int $companyId
    ): array {
        $assignment = $this->progressRepository
            ->findCompanyAssignmentOrFail(
                $assignmentId,
                $companyId
            );

        return [
            'assignment' => $assignment,
            'updates' => $this->progressRepository
                ->getAssignmentProgressUpdates($assignmentId),
        ];
    }

    private function ensureAssignmentAllowsProgress(string $status): void
    {
        if ($status !== 'working') {
            throw ValidationException::withMessages([
                'assignment' => [
                    'ar' => 'لا يمكن إضافة تحديث تقدم لأن المهمة ليست قيد التنفيذ.',
                    'en' => 'Progress updates can only be added while the task is in progress.',
                ],
            ]);
        }
    }

    private function ensureProgressDoesNotDecrease(
        int $assignmentId,
        int $newPercentage
    ): void {
        $latestPercentage = $this->progressRepository
            ->getLatestProgressPercentage($assignmentId);

        if ($newPercentage < $latestPercentage) {
            throw ValidationException::withMessages([
                'progress_percentage' => [
                    'ar' => "نسبة التقدم الجديدة لا يمكن أن تكون أقل من {$latestPercentage}%.",
                    'en' => "The new progress percentage cannot be less than {$latestPercentage}%.",
                ],
            ]);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     * @return array<int, string>
     */
    private function storeAttachments(
        array $attachments,
        int $assignmentId
    ): array {
        $paths = [];

        foreach ($attachments as $attachment) {
            $paths[] = $attachment->store(
                "company-task-progress/{$assignmentId}",
                'public'
            );
        }

        return $paths;
    }
}
