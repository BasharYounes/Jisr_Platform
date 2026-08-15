<?php

namespace App\Services\Mentor;

use App\Enums\MentorApplicationSource;
use App\Enums\MentorApplicationStatus;
use App\Exceptions\AIProviderException;
use App\Models\MentorProfile;
use App\Models\User;
use App\Services\AI\SkillExtractionService;
use App\Services\CV\CVTextExtractionService;
use App\Services\Skill\SkillNormalizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MentorApplicationReviewService
{
    public function __construct(
        private readonly CVTextExtractionService $cvTextExtraction,
        private readonly SkillExtractionService $skillExtraction,
        private readonly SkillNormalizationService $skillNormalization
    ) {}

    /**
     * @throws AIProviderException
     */
    public function approve(
        MentorProfile $application,
        User $admin
    ): MentorProfile {
        $this->ensurePending($application);

        /*
         * External/expensive work is intentionally completed BEFORE the
         * database transaction. This prevents holding DB locks while reading
         * a CV or waiting for Gemini.
         */
        $skillIds = $this->skillIdsForApproval($application);

        return DB::transaction(function () use (
            $application,
            $admin,
            $skillIds
        ): MentorProfile {
            $locked = MentorProfile::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            $this->ensurePending($locked);

            $locked->skills()->sync($skillIds);

            $locked->forceFill([
                'status' => MentorApplicationStatus::Approved,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            return $locked;
        });
    }

    public function reject(
        MentorProfile $application,
        User $admin,
        string $reason
    ): MentorProfile {
        $this->ensurePending($application);

        return DB::transaction(function () use (
            $application,
            $admin,
            $reason
        ): MentorProfile {
            $locked = MentorProfile::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            $this->ensurePending($locked);

            $locked->forceFill([
                'status' => MentorApplicationStatus::Rejected,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => trim($reason),
            ])->save();

            return $locked;
        });
    }

    /**
     * @return array<int>
     *
     * @throws AIProviderException
     */
    private function skillIdsForApproval(
        MentorProfile $application
    ): array {
        if (
            $application->source
            === MentorApplicationSource::SelfApplication
        ) {
            $user = $application->user;

            if (! $user) {
                throw ValidationException::withMessages([
                    'application' => [
                        'The self application is not linked to a user.',
                    ],
                ]);
            }

            $existingSkillIds = $this->existingUserSkillIds($user);

            /*
             * Student skills are already produced by the platform's existing
             * CV/assessment flow. Never spend another Gemini request on them.
             */
            if ($user->hasRole('student')) {
                if ($existingSkillIds === []) {
                    throw ValidationException::withMessages([
                        'skills' => [
                            'The student has no existing skills to reuse. '
                            .'Complete the existing student skill analysis first.',
                        ],
                    ]);
                }

                return $existingSkillIds;
            }

            /*
             * Supervisors may already have reusable user_skills. Reuse them
             * when present; otherwise extract once from the submitted CV.
             */
            if ($existingSkillIds !== []) {
                return $existingSkillIds;
            }
        }

        return $this->extractSkillIdsFromCv($application);
    }

    /**
     * @return array<int>
     */
    private function existingUserSkillIds(User $user): array
    {
        return $user->skills()
            ->distinct()
            ->pluck('skills.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int>
     *
     * @throws AIProviderException
     */
    private function extractSkillIdsFromCv(
        MentorProfile $application
    ): array {
        if (blank($application->cv_path)) {
            throw ValidationException::withMessages([
                'cv' => [
                    'The mentor application does not have a CV.',
                ],
            ]);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($application->cv_path)) {
            throw ValidationException::withMessages([
                'cv' => [
                    'The mentor application CV file is missing.',
                ],
            ]);
        }

        $resumeText = $this->cvTextExtraction->extractFromPath(
            $disk->path($application->cv_path)
        );

        if (blank($resumeText)) {
            throw ValidationException::withMessages([
                'cv' => [
                    'Could not extract readable text from the mentor CV.',
                ],
            ]);
        }

        $careerContext = filled($application->professional_title)
            ? $application->professional_title
            : (string) $application->specialization;

        $result = $this->skillExtraction->extractSkills(
            $resumeText,
            $careerContext
        );

        $rawSkills = $result['skills'] ?? [];

        $skillIds = collect(
            $this->skillNormalization->normalizeMany($rawSkills)
        )
            ->pluck('skill_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($skillIds === []) {
            throw ValidationException::withMessages([
                'skills' => [
                    'No recognized mentor skills were extracted from the CV.',
                ],
            ]);
        }

        return $skillIds;
    }

    private function ensurePending(
        MentorProfile $application
    ): void {
        if (
            $application->status
            !== MentorApplicationStatus::Pending
        ) {
            throw ValidationException::withMessages([
                'status' => [
                    'Only pending mentor applications can be reviewed.',
                ],
            ]);
        }
    }
}
