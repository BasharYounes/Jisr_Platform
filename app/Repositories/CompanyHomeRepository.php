<?php

namespace App\Repositories;

use App\Interfaces\CompanyHomeRepositoryInterface;
use App\Models\Company;
use App\Models\CompanyTask;
use App\Models\CompanyTaskApplication;
use App\Models\CompanyTaskAssignment;
use App\Models\CompanyTaskSubmission;
use Illuminate\Support\Collection;

class CompanyHomeRepository implements CompanyHomeRepositoryInterface
{
    public function getCompanySummary(int $companyId): array
    {
        $company = Company::query()
            ->with(['users:id,name'])
            ->select(['id'])
            ->findOrFail($companyId);

        return [
            'id' => $company->id,
            'name' => $company->users->first()?->name,
        ];
    }

    public function getActiveOpportunitiesStats(int $companyId): array
    {
        $tasks = CompanyTask::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['published', 'in_progress'])
            ->where('deadline', '>=', now())
            ->pluck('id');

        return [
            'count' => $tasks->count(),
            'ids' => $tasks->toArray(),
        ];
    }

    public function getNewApplicantsStats(int $companyId): array
    {
        $applicants = CompanyTaskApplication::query()
            ->where('status', 'pending')
            ->whereHas('task', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->get(['id as application_id', 'student_user_id', 'company_task_id as task_id']);

        return [
            'count' => $applicants->count(),
            'items' => $applicants->toArray(),
        ];
    }

    public function getPendingReviewsStats(int $companyId): array
    {
        $reviews = CompanyTaskSubmission::query()
            ->where('status', 'submitted')
            ->whereHas('assignment.task', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->with(['assignment' => function ($q) {
                $q->select('id', 'company_task_id', 'student_user_id');
            }])
            ->get(['id', 'company_task_assignment_id']);

        $items = $reviews->map(function ($review) {
            return [
                'submission_id' => $review->id,
                'assignment_id' => $review->company_task_assignment_id,
                'task_id' => $review->assignment?->company_task_id,
                'student_user_id' => $review->assignment?->student_user_id,
            ];
        });

        return [
            'count' => $items->count(),
            'items' => $items->toArray(),
        ];
    }

    public function getActiveAssignmentsStats(int $companyId): array
    {
        $activeStatuses = [
            'not_started',
            'working',
        ];

        $assignments = CompanyTaskAssignment::query()
            ->whereIn('status', $activeStatuses)
            ->whereHas('task', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->get(['id as assignment_id', 'company_task_id as task_id', 'student_user_id']);

        return [
            'count' => $assignments->count(),
            'items' => $assignments->toArray(),
        ];
    }

    public function getRequiredActions(int $companyId): Collection
    {
        $submissionReviewActions = CompanyTaskSubmission::query()
            ->with([
                'assignment.task',
                'assignment.student',
            ])
            ->where('status', 'submitted')
            ->whereHas('assignment.task', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->latest('submitted_at')
            ->limit(3)
            ->get()
            ->map(function (CompanyTaskSubmission $submission) {
                return [
                    'type' => 'review',
                    'title' => 'إجراء مطلوب',
                    'description' => 'الطالب '
                        .($submission->assignment?->student?->name ?? '')
                        .' أرسل تسليماً نهائياً لمهمة '
                        .($submission->assignment?->task?->title ?? ''),

                    'action_label' => 'Review',

                    'target_type' => 'task_submission',
                    'target_id' => $submission->id,

                    'student_user_id' => $submission->assignment?->student?->id,
                    'task_id' => $submission->assignment?->task?->id,

                    '_action_at' => $submission->submitted_at,
                ];
            });

        $applicationReviewActions = CompanyTaskApplication::query()
            ->with([
                'task',
                'student',
            ])
            ->where('status', 'pending')
            ->whereHas('task', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->latest('applied_at')
            ->limit(3)
            ->get()
            ->map(function (CompanyTaskApplication $application) {
                return [
                    'type' => 'application',
                    'title' => 'إجراء مطلوب',
                    'description' => 'الطالب '
                        .($application->student?->name ?? '')
                        .' قدّم على مهمة '
                        .($application->task?->title ?? ''),

                    'action_label' => 'Review',

                    'target_type' => 'task_application',
                    'target_id' => $application->id,

                    'student_user_id' => $application->student_user_id,
                    'task_id' => $application->company_task_id,

                    '_action_at' => $application->applied_at,
                ];
            });

        return $submissionReviewActions
            ->concat($applicationReviewActions)
            ->sortByDesc('_action_at')
            ->take(3)
            ->values()
            ->map(function (array $action) {
                unset($action['_action_at']);

                return $action;
            });
    }

    public function getRecentActivities(int $companyId): Collection
    {
        return CompanyTaskApplication::query()
            ->with(['task'])
            ->where('status', 'pending')
            ->whereHas('task', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->latest('applied_at')
            ->limit(5)
            ->get()
            ->map(function ($application) {
                return [
                    'type' => 'new_applicant',
                    'title' => 'متقدمون جدد',
                    'description' => 'طالب جديد تقدم لمهمة '.($application->task?->title ?? ''),
                    'action_label' => 'عرض',
                    'target_type' => 'task_applications',
                    'target_id' => $application->company_task_id,
                ];
            });
    }
}
