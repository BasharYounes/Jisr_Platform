<?php

namespace App\Repositories;

use App\Interfaces\OpportunityInterviewRepositoryInterface;
use App\Models\OpportunityInterview;

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
}