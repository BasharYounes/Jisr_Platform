<?php

namespace App\Services\Opportunities;

use App\Interfaces\OpportunityApplicationRepositoryInterface;
use App\Interfaces\OpportunityRepositoryInterface;
use App\Models\Application;
use App\Models\Cv;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentOpportunityApplicationService
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository,
        private readonly OpportunityApplicationRepositoryInterface $applicationRepository,
        private readonly OpportunityRecommendationService $recommendationService,
    ) {}

    public function apply(
        int $studentId,
        int $opportunityId,
        array $data
    ): Application {
        return DB::transaction(function () use ($studentId, $opportunityId, $data): Application {
            $opportunity = $this->opportunityRepository
                ->findPublishedActiveOrFail($opportunityId);

            if ($this->applicationRepository->existsForStudent($studentId, $opportunity->id)) {
                throw ValidationException::withMessages([
                    'opportunity' => [
                        'لقد قمت بالتقديم على هذه الفرصة مسبقًا.',
                    ],
                ]);
            }

            $cv = $this->getStudentCvOrFail($studentId);

            $matchSnapshot = $this->recommendationService->calculateMatch(
                opportunity: $opportunity,
                studentUserId: $studentId
            );

            $application = $this->applicationRepository->create([
                'opportunity_id' => $opportunity->id,
                'user_id' => $studentId,
                'cv_id' => $cv->CvID,
                'cover_letter' => $data['cover_letter'] ?? null,
                'status' => 'pending',
                'match_score' => $matchSnapshot['score'],
                'match_reasons' => $matchSnapshot['reasons'],
                'applied_at' => now(),
            ]);

            // TODO: Dispatch notification to company when a new opportunity application is submitted.

            return $application;
        });
    }

    public function getStudentApplications(int $studentUserId): Collection
    {
        return $this->applicationRepository
            ->getStudentApplications($studentUserId);
    }

    public function getStudentApplicationDetails(
        int $studentUserId,
        int $applicationId
    ): Application {
        return $this->applicationRepository
            ->findStudentApplicationOrFail(
                studentUserId: $studentUserId,
                applicationId: $applicationId
            );
    }

    public function withdraw(
        int $studentUserId,
        int $applicationId
    ): Application {
        return DB::transaction(function () use ($studentUserId, $applicationId): Application {
            $application = $this->applicationRepository
                ->findStudentApplicationOrFail(
                    studentUserId: $studentUserId,
                    applicationId: $applicationId
                );

            if ($application->status !== 'pending') {
                throw ValidationException::withMessages([
                    'application' => [
                        'لا يمكن سحب الطلب بعد مراجعته.',
                    ],
                ]);
            }

            if ($application->interview !== null) {
                throw ValidationException::withMessages([
                    'application' => [
                        'لا يمكن سحب الطلب بعد جدولة مقابلة.',
                    ],
                ]);
            }

            $application = $this->applicationRepository->update($application, [
                'status' => 'withdrawn',
            ]);

            // TODO: Dispatch notification to company when student withdraws application.

            return $application;
        });
    }

    private function getStudentCvOrFail(int $studentUserId): Cv
    {
        $cv = Cv::query()
            ->where('UserId', $studentUserId)
            ->orderByDesc('IsPrimary')
            ->orderByDesc('UploadedAt')
            ->first();

        if (! $cv) {
            throw ValidationException::withMessages([
                'cv' => [
                    'يجب رفع سيرة ذاتية قبل التقديم على الفرصة.',
                ],
            ]);
        }

        return $cv;
    }
}
