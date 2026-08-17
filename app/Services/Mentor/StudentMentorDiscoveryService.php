<?php

namespace App\Services\Mentor;

use App\Enums\MentorApplicationStatus;
use App\Models\AssessmentSession;
use App\Models\MentorProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudentMentorDiscoveryService
{
    /**
     * @return array{
     *     paginator: LengthAwarePaginator,
     *     context: array<string, mixed>
     * }
     */
    public function discover(
        User $student,
        array $filters
    ): array {
        $context = $this->buildRecommendationContext($student);

        $targetSpecialization = $context['specialization'];
        $targetSkillIds = collect($context['target_skill_ids'])
            ->map(fn ($id) => (int) $id)
            ->values();

        $query = MentorProfile::query()
            ->select('mentor_profiles.*')
            ->where(
                'status',
                MentorApplicationStatus::Approved->value
            )
            ->with([
                'skills:id,name,category',
                'company:id,industry,website',
            ])
            ->when(
                isset($filters['specialization']),
                fn ($builder) => $builder->where(
                    'specialization',
                    $filters['specialization']
                )
            );

        $this->applySearch(
            $query,
            $filters['search'] ?? null
        );

        if ($targetSkillIds->isNotEmpty()) {
            $query->withCount([
                'skills as matching_skill_count' => fn ($builder) => $builder->whereIn(
                    'skills.id',
                    $targetSkillIds->all()
                ),
            ]);
        } else {
            $query->selectRaw('0 AS matching_skill_count');
        }

        if ($targetSpecialization !== null) {
            $query->selectRaw(
                'CASE WHEN specialization = ? THEN 1 ELSE 0 END '
                .'AS specialization_match',
                [$targetSpecialization]
            );
        } else {
            $query->selectRaw('0 AS specialization_match');
        }

        $query
            ->orderByDesc('specialization_match')
            ->orderByDesc('matching_skill_count')
            ->orderByDesc('mentor_profiles.id');

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $paginator = $query->paginate(
            $perPage,
            ['*'],
            'page',
            $page
        );

        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (MentorProfile $mentor) => $this->enrichMentor(
                    $mentor,
                    $targetSpecialization,
                    $targetSkillIds
                )
            )
        );

        unset($context['target_skill_ids']);

        return [
            'paginator' => $paginator,
            'context' => $context,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recommendationContext(
        User $student
    ): array {
        $context = $this->buildRecommendationContext($student);
        unset($context['target_skill_ids']);

        return $context;
    }

    public function enrichSingle(
        User $student,
        MentorProfile $mentor
    ): MentorProfile {
        $context = $this->buildRecommendationContext($student);

        return $this->enrichMentor(
            $mentor,
            $context['specialization'],
            collect($context['target_skill_ids'])
                ->map(fn ($id) => (int) $id)
        );
    }

    private function applySearch(
        $query,
        ?string $search
    ): void {
        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        /*
         * Email lookup is intentionally exact to avoid broad enumeration
         * such as searching only "@gmail.com".
         * Name lookup remains partial for normal discovery UX.
         */
        if (filter_var($search, FILTER_VALIDATE_EMAIL)) {
            $query->whereRaw(
                'LOWER(email) = ?',
                [mb_strtolower($search)]
            );

            return;
        }

        $query->where(
            'full_name',
            'like',
            '%'.$search.'%'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecommendationContext(
        User $student
    ): array {
        $latestAssessment = AssessmentSession::query()
            ->where('UserID', $student->id)
            ->with([
                'careerPath:CareerPathID,Name',
                'careerPath.skills:id,name,category',
            ])
            ->orderByDesc('AssessmentSessionID')
            ->first();

        $careerPath = $latestAssessment?->careerPath;

        $targetSkills = collect();
        $skillSource = 'none';

        if (
            $careerPath
            && $careerPath->relationLoaded('skills')
            && $careerPath->skills->isNotEmpty()
        ) {
            $targetSkills = $careerPath->skills;
            $skillSource = 'career_path_required_skills';
        } else {
            $targetSkills = $student->skills()
                ->select([
                    'skills.id',
                    'skills.name',
                    'skills.category',
                ])
                ->get();

            if ($targetSkills->isNotEmpty()) {
                $skillSource = 'student_existing_skills';
            }
        }

        return [
            'career_path' => $careerPath?->Name,
            'specialization' => $careerPath
                ? $this->specializationFromCareerPath(
                    $careerPath->Name
                )
                : null,
            'skill_source' => $skillSource,
            'target_skill_ids' => $targetSkills
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            'target_skills' => $targetSkills
                ->map(fn ($skill) => [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'category' => $skill->category,
                ])
                ->values()
                ->all(),
        ];
    }

    private function enrichMentor(
        MentorProfile $mentor,
        ?string $targetSpecialization,
        Collection $targetSkillIds
    ): MentorProfile {
        $specializationMatch = $targetSpecialization !== null
            && $mentor->specialization === $targetSpecialization;

        $matchingSkillIds = $mentor->skills
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->intersect($targetSkillIds)
            ->values();

        $matchingSkillCount = $matchingSkillIds->count();

        $mentor->setAttribute(
            'specialization_match',
            $specializationMatch
        );

        $mentor->setAttribute(
            'matching_skill_count',
            $matchingSkillCount
        );

        $mentor->setAttribute(
            'matching_skill_ids',
            $matchingSkillIds->all()
        );

        $mentor->setAttribute(
            'is_recommended',
            $specializationMatch || $matchingSkillCount > 0
        );

        return $mentor;
    }

    private function specializationFromCareerPath(
        string $careerPathName
    ): ?string {
        $name = Str::lower(trim($careerPathName));

        if (
            Str::contains($name, [
                'backend',
                'back-end',
                'server-side',
            ])
        ) {
            return 'backend';
        }

        if (
            Str::contains($name, [
                'frontend',
                'front-end',
            ])
        ) {
            return 'frontend';
        }

        if (
            Str::contains($name, [
                'flutter',
                'mobile developer',
                'mobile development',
            ])
        ) {
            return 'flutter';
        }

        if (
            Str::contains($name, [
                'devops',
                'dev ops',
            ])
        ) {
            return 'devops';
        }

        if (
            Str::contains($name, [
                'artificial intelligence',
                'machine learning',
                'data science',
            ])
            || preg_match('/\bai\b/u', $name) === 1
        ) {
            return 'ai';
        }

        return null;
    }
}
