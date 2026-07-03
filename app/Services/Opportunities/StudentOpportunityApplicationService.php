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
        int $studentUserId,
        int $opportunityId,
        array $data
    ): Application {
        return DB::transaction(function () use ($studentUserId, $opportunityId, $data): Application {
            $opportunity = $this->opportunityRepository
                ->findPublishedActiveOrFail($opportunityId);

            if ($this->applicationRepository->existsForStudent($studentUserId, $opportunity->id)) {
                throw ValidationException::withMessages([
                    'opportunity' => [
                        'لقد قمت بالتقديم على هذه الفرصة مسبقًا. | You have already applied to this opportunity.',
                    ],
                ]);
            }

            if (! empty($data['cv_id'])) {
                $this->ensureCvBelongsToStudent(
                    studentUserId: $studentUserId,
                    cvId: (int) $data['cv_id']
                );
            }

            $matchSnapshot = $this->recommendationService->calculateMatch(
                opportunity: $opportunity,
                studentUserId: $studentUserId
            );

            $application = $this->applicationRepository->create([
                'opportunity_id' => $opportunity->id,
                'user_id' => $studentUserId,
                'cv_id' => $data['cv_id'] ?? null,
                'cover_letter' => $data['cover_letter'] ?? null,
                'status' => 'pending',
                'match_score' => $matchSnapshot['score'],
                'match_reasons' => $matchSnapshot['reasons'],
                'applied_at' => now(),
            ]);

            // TODO: Dispatch notification to company when a new application is submitted.

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
                        'لا يمكن سحب الطلب بعد مراجعته. | You cannot withdraw an application after it has been reviewed.',
                    ],
                ]);
            }

            if ($application->interview !== null) {
                throw ValidationException::withMessages([
                    'application' => [
                        'لا يمكن سحب الطلب بعد جدولة مقابلة. | You cannot withdraw an application after an interview has been scheduled.',
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

    private function ensureCvBelongsToStudent(
        int $studentUserId,
        int $cvId
    ): void {
        $exists = Cv::query()
            ->where('CvID', $cvId)
            ->where('UserId', $studentUserId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'cv_id' => [
                    'السيرة الذاتية المختارة لا تعود لهذا الطالب. | Selected CV does not belong to this student.',
                ],
            ]);
        }
    }
}
