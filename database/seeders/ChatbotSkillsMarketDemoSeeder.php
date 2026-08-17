<?php

namespace Database\Seeders;

use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CareerPath;
use App\Models\CareerPathSkill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChatbotSkillsMarketDemoSeeder extends Seeder
{
    private const SOURCE = 'chatbot_demo';

    public function run(): void
    {
        $this->ensureSafeEnvironment();

        $studentId = (int) env('CHATBOT_DEMO_STUDENT_ID', 2);
        $preferredCareerPathId = env('CHATBOT_DEMO_CAREER_PATH_ID');

        $student = User::query()->find($studentId);

        if ($student === null) {
            throw new RuntimeException(
                "Chatbot demo student with id {$studentId} was not found. ".
                'Set CHATBOT_DEMO_STUDENT_ID to an existing student user id.'
            );
        }

        $careerPath = $this->resolveCareerPath(
            $preferredCareerPathId !== null ? (int) $preferredCareerPathId : null,
        );

        $requiredSkills = CareerPathSkill::query()
            ->with('skill:id,name,category')
            ->where('CareerPathID', $careerPath->CareerPathID)
            ->orderByDesc('IsCore')
            ->orderByDesc('Weight')
            ->orderBy('CareerPathSkillID')
            ->get()
            ->filter(fn (CareerPathSkill $item) => $item->skill !== null)
            ->values();

        if ($requiredSkills->isEmpty()) {
            throw new RuntimeException(
                "Career path {$careerPath->Name} has no configured required skills."
            );
        }

        DB::transaction(function () use ($studentId, $careerPath, $requiredSkills): void {
            $now = now();
            $ownedSkills = $this->chooseOwnedSkills(
                careerPathId: (int) $careerPath->CareerPathID,
                requiredSkills: $requiredSkills,
            );

            $ownedLevels = $this->buildOwnedLevels($ownedSkills);
            $ownedSkillIds = $ownedSkills
                ->pluck('SkillID')
                ->map(fn ($id) => (int) $id)
                ->all();

            // Remove only old records created by this demo seeder.
            UserSkill::query()
                ->where('UserId', $studentId)
                ->where('Source', self::SOURCE)
                ->whereNotIn('SkillId', $ownedSkillIds)
                ->delete();

            foreach ($ownedSkills as $requiredSkill) {
                $skillId = (int) $requiredSkill->SkillID;
                $existing = UserSkill::query()
                    ->where('UserId', $studentId)
                    ->where('SkillId', $skillId)
                    ->first();

                // Never overwrite a real skill record created outside this demo seeder.
                if ($existing !== null && $existing->Source !== self::SOURCE) {
                    continue;
                }

                UserSkill::query()->updateOrCreate(
                    [
                        'UserId' => $studentId,
                        'SkillId' => $skillId,
                    ],
                    [
                        'ProficiencyLevel' => (int) round($ownedLevels[$skillId]),
                        'ConfidenceScore' => 0.85,
                        'Source' => self::SOURCE,
                        'Verified' => false,
                        'VerificationStatus' => UserSkill::STATUS_AI_ESTIMATED,
                        'VerifiedAt' => null,
                        'VerifiedBy' => null,
                    ],
                );
            }

            $session = $this->findDemoSession(
                studentId: $studentId,
                careerPathId: (int) $careerPath->CareerPathID,
            ) ?? new AssessmentSession;

            $session->fill([
                'UserID' => $studentId,
                'CvID' => null,
                'CareerPathID' => (int) $careerPath->CareerPathID,
                'Status' => AssessmentSession::STATUS_COMPLETED,
                'InitialSkillsSnapshotJson' => [
                    'source' => self::SOURCE,
                    'purpose' => 'Local chatbot skills and market analysis demo',
                    'student_id' => $studentId,
                    'career_path_id' => (int) $careerPath->CareerPathID,
                ],
                'StartedAt' => $now->copy()->subMinutes(30),
                'CompletedAt' => $now,
            ]);
            $session->save();

            $finalResults = [];
            $requiredSkillIds = [];

            foreach ($requiredSkills as $requiredSkill) {
                $skillId = (int) $requiredSkill->SkillID;
                $requiredSkillIds[] = $skillId;

                $actualLevel = $ownedLevels[$skillId] ?? 0.0;
                $requiredLevel = (float) $requiredSkill->RequiredLevel;
                $hasGap = $actualLevel < $requiredLevel;

                AssessmentSkillSession::query()->updateOrCreate(
                    [
                        'AssessmentSessionID' => (int) $session->AssessmentSessionID,
                        'SkillID' => $skillId,
                    ],
                    [
                        'InitialLevel' => $actualLevel,
                        'CurrentEstimatedLevel' => $actualLevel,
                        'FinalLevel' => $actualLevel,
                        'ConfidenceScore' => 0.85,
                        'QuestionCount' => 5,
                        'Status' => AssessmentSkillSession::STATUS_COMPLETED,
                        'CompletedAt' => $now,
                    ],
                );

                $finalResults[] = [
                    'skill_id' => $skillId,
                    'skill_name' => $requiredSkill->skill?->name,
                    'final_level' => $actualLevel,
                    'confidence_score' => 0.85,
                    'topic_coverage_ratio' => 0.75,
                    'tested_topics' => ['chatbot_demo'],
                    'improvement_topics' => $hasGap ? ['skill_gap_demo'] : [],
                ];
            }

            AssessmentSkillSession::query()
                ->where('AssessmentSessionID', $session->AssessmentSessionID)
                ->whereNotIn('SkillID', $requiredSkillIds)
                ->delete();

            $session->update([
                'FinalResultsJson' => $finalResults,
            ]);
        });

        $activeMarketPostings = DB::table('market_job_postings')
            ->where('career_path_id', $careerPath->CareerPathID)
            ->where('status', 'active')
            ->count();

        $this->command?->info('Chatbot skills/market demo data created successfully.');
        $this->command?->line("Student ID: {$studentId}");
        $this->command?->line("Career path: {$careerPath->Name} (#{$careerPath->CareerPathID})");
        $this->command?->line("Active classified market postings for this path: {$activeMarketPostings}");
        $this->command?->warn(
            'These records are local demo data. Remove them later with: '.
            'php artisan db:seed --class=ChatbotSkillsMarketDemoCleanupSeeder'
        );
    }

    private function ensureSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'ChatbotSkillsMarketDemoSeeder is allowed only in local or testing environments.'
            );
        }
    }

    private function resolveCareerPath(?int $preferredCareerPathId): CareerPath
    {
        if ($preferredCareerPathId !== null) {
            $preferred = CareerPath::query()->find($preferredCareerPathId);

            if ($preferred === null) {
                throw new RuntimeException(
                    "Configured career path id {$preferredCareerPathId} was not found."
                );
            }

            return $preferred;
        }

        // Prefer Backend Developer when it is available and configured.
        $backend = CareerPath::query()
            ->where('Name', 'Backend Developer')
            ->whereHas('careerPathSkills')
            ->first();

        if ($backend !== null) {
            return $backend;
        }

        // Otherwise choose the configured career path with the most active market data.
        $careerPathId = DB::table('career_paths as paths')
            ->join('career_path_skills as path_skills', 'path_skills.CareerPathID', '=', 'paths.CareerPathID')
            ->leftJoin('market_job_postings as postings', function ($join): void {
                $join->on('postings.career_path_id', '=', 'paths.CareerPathID')
                    ->where('postings.status', '=', 'active');
            })
            ->select('paths.CareerPathID')
            ->groupBy('paths.CareerPathID')
            ->orderByRaw('COUNT(DISTINCT postings.id) DESC')
            ->orderBy('paths.CareerPathID')
            ->value('paths.CareerPathID');

        $careerPath = $careerPathId !== null
            ? CareerPath::query()->find((int) $careerPathId)
            : null;

        if ($careerPath === null) {
            throw new RuntimeException(
                'No career path with configured skills is available for chatbot demo data.'
            );
        }

        return $careerPath;
    }

    private function chooseOwnedSkills(int $careerPathId, Collection $requiredSkills): Collection
    {
        $marketCounts = DB::table('market_job_posting_skill_occurrences as occurrences')
            ->join('market_job_postings as postings', 'postings.id', '=', 'occurrences.market_job_posting_id')
            ->where('postings.career_path_id', $careerPathId)
            ->where('postings.status', 'active')
            ->whereIn('occurrences.skill_id', $requiredSkills->pluck('SkillID'))
            ->select('occurrences.skill_id', DB::raw('COUNT(DISTINCT postings.id) as posting_count'))
            ->groupBy('occurrences.skill_id')
            ->pluck('posting_count', 'occurrences.skill_id');

        return $requiredSkills
            ->sortByDesc(function (CareerPathSkill $item) use ($marketCounts): float {
                $marketCount = (float) ($marketCounts[(int) $item->SkillID] ?? 0);
                $coreBoost = $item->IsCore ? 100000 : 0;
                $weightBoost = ((float) $item->Weight) * 1000;

                return $coreBoost + $weightBoost + $marketCount;
            })
            ->take(min(4, $requiredSkills->count()))
            ->values();
    }

    private function buildOwnedLevels(Collection $ownedSkills): array
    {
        $gapOffsets = [0.0, 0.5, 1.0, 1.5];
        $levels = [];

        foreach ($ownedSkills->values() as $index => $requiredSkill) {
            $requiredLevel = (float) $requiredSkill->RequiredLevel;
            $offset = $gapOffsets[$index] ?? 1.5;
            $levels[(int) $requiredSkill->SkillID] = max(1.0, $requiredLevel - $offset);
        }

        return $levels;
    }

    private function findDemoSession(int $studentId, int $careerPathId): ?AssessmentSession
    {
        return AssessmentSession::query()
            ->where('UserID', $studentId)
            ->where('CareerPathID', $careerPathId)
            ->get()
            ->first(function (AssessmentSession $session): bool {
                return ($session->InitialSkillsSnapshotJson['source'] ?? null) === self::SOURCE;
            });
    }
}
