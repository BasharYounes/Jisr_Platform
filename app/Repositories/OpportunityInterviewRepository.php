<?php

namespace App\Repositories;

use App\Interfaces\OpportunityInterviewRepositoryInterface;
use App\Models\OpportunityInterview;
use Illuminate\Support\Collection;

class OpportunityInterviewRepository implements OpportunityInterviewRepositoryInterface
{
    public function create(array $data): OpportunityInterview
    {
        return OpportunityInterview::query()
            ->create($data)
            ->load([
                'application.user.studentProfile',
                'application.cv',
                'opportunity.company',
                'company',
                'student',
            ]);
    }

    public function findByApplicationId(int $applicationId): ?OpportunityInterview
    {
        return OpportunityInterview::query()
            ->with([
                'application.user.studentProfile',
                'application.cv',
                'opportunity.company',
                'company',
                'student',
            ])
            ->where('application_id', $applicationId)
            ->first();
    }

    public function findCompanyInterviewOrFail(
        int $companyId,
        int $opportunityId,
        int $interviewId
    ): OpportunityInterview {
        return OpportunityInterview::query()
            ->with([
                'application.user.studentProfile',
                'application.cv',
                'opportunity.company',
                'company',
                'student',
            ])
            ->whereKey($interviewId)
            ->where('company_id', $companyId)
            ->where('opportunity_id', $opportunityId)
            ->firstOrFail();
    }

    public function update(
        OpportunityInterview $interview,
        array $data
    ): OpportunityInterview {
        $interview->update($data);

        return $interview->fresh([
            'application.user.studentProfile',
            'application.cv',
            'opportunity.company',
            'company',
            'student',
        ]);
    }

    public function getStudentInterviews(
        int $studentUserId,
        array $filters = []
    ): Collection {
        $now = now();

        $filter = $filters['filter'] ?? null;
        $status = $filters['status'] ?? null;

        $query = OpportunityInterview::query()
            ->with([
                'application',
                'opportunity',
                'company',
            ])
            ->where('student_user_id', $studentUserId);

        if ($filter === 'upcoming') {
            $query
                ->whereIn('status', OpportunityInterview::SCHEDULED_STATUSES)
                ->where('scheduled_at', '>', $now);
        }

        if ($filter === 'history') {
            $query->where(function ($query) use ($now): void {
                $query
                    ->whereIn('status', [
                        OpportunityInterview::STATUS_COMPLETED,
                        OpportunityInterview::STATUS_CANCELLED,
                    ])
                    ->orWhere(function ($query) use ($now): void {
                        $query
                            ->whereIn('status', OpportunityInterview::SCHEDULED_STATUSES)
                            ->where('scheduled_at', '<=', $now);
                    });
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($filter === 'upcoming') {
            return $query
                ->orderBy('scheduled_at')
                ->get();
        }

        return $query
            ->orderByDesc('scheduled_at')
            ->get();
    }
}
