<?php

namespace Database\Seeders;

use App\Domains\Matching\Handler\GetTopCandidatesForOpportunityHandler;
use App\Domains\Matching\Queries\GetTopCandidatesForOpportunity;
use App\Models\Company;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class MatchingE2ESeeder extends Seeder
{
    private const PASSWORD = 'Matching@123';

    private const OPPORTUNITY_TITLE = 'Matching E2E - Backend Developer';

    private const PROJECT_TEMPLATE_TITLE = 'Matching E2E - Evaluated Backend Project';

    private const TAG_TYPE = 'matching_e2e';

    private const COMPANY_WEBSITE = 'https://matching-e2e.jisr.test';

    private const OTHER_COMPANY_WEBSITE = 'https://matching-e2e-other.jisr.test';

    /**
     * Stable fixture users. These addresses are used only by this dedicated local E2E dataset.
     */
    private const USERS = [
        'company' => [
            'name' => 'Matching E2E Company',
            'email' => 'matching.company@jisr.test',
            'role' => 'company',
        ],
        'other_company' => [
            'name' => 'Matching E2E Other Company',
            'email' => 'matching.other.company@jisr.test',
            'role' => 'company',
        ],
        'supervisor' => [
            'name' => 'Matching E2E Supervisor',
            'email' => 'matching.supervisor@jisr.test',
            'role' => 'supervisor',
        ],
        'rank_1' => [
            'name' => 'Ahmad Matching E2E',
            'email' => 'matching.ahmad@jisr.test',
            'role' => 'student',
        ],
        'rank_2' => [
            'name' => 'Sara Matching E2E',
            'email' => 'matching.sara@jisr.test',
            'role' => 'student',
        ],
        'rank_3' => [
            'name' => 'Omar Matching E2E',
            'email' => 'matching.omar@jisr.test',
            'role' => 'student',
        ],
        'withdrawn' => [
            'name' => 'Withdrawn Perfect Candidate',
            'email' => 'matching.withdrawn@jisr.test',
            'role' => 'student',
        ],
        'rejected' => [
            'name' => 'Rejected Perfect Candidate',
            'email' => 'matching.rejected@jisr.test',
            'role' => 'student',
        ],
        'accepted' => [
            'name' => 'Accepted Perfect Candidate',
            'email' => 'matching.accepted@jisr.test',
            'role' => 'student',
        ],
        'non_applicant' => [
            'name' => 'Non Applicant Perfect Candidate',
            'email' => 'matching.nonapplicant@jisr.test',
            'role' => 'student',
        ],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'MatchingE2ESeeder is a local verification fixture and must not run in production.'
            );
        }

        $dataset = DB::transaction(function (): array {
            $this->cleanupPreviousFixture();
            $this->ensureRoles();

            $pointActionTypeId = $this->resolveCommunityPointActionTypeId();

            $company = Company::query()->create([
                'industry' => 'Software Engineering',
                'location' => 'Damascus',
                'website' => self::COMPANY_WEBSITE,
                'documentation_file' => null,
            ]);

            $otherCompany = Company::query()->create([
                'industry' => 'Software Engineering',
                'location' => 'Damascus',
                'website' => self::OTHER_COMPANY_WEBSITE,
                'documentation_file' => null,
            ]);

            $users = [];

            foreach (self::USERS as $key => $definition) {
                $users[$key] = $this->createFixtureUser(
                    name: $definition['name'],
                    email: $definition['email'],
                    role: $definition['role']
                );
            }

            $users['company']->companies()->attach($company->id, [
                'role' => 'owner',
            ]);

            $users['other_company']->companies()->attach($otherCompany->id, [
                'role' => 'owner',
            ]);

            $skills = $this->ensureSkills();

            $now = now();

            $opportunityId = DB::table('opportunities')->insertGetId([
                'company_id' => $company->id,
                'title' => self::OPPORTUNITY_TITLE,
                'description' => 'Deterministic local dataset used to verify the Jisr smart applicant ranking engine.',
                'type' => 'job',
                'location' => 'Damascus',
                'salary_min' => null,
                'salary_max' => null,
                'status' => 'published',
                'deadline' => $now->copy()->addMonth(),
                'posted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('opportunity_skills')->insert([
                $this->opportunitySkillRow($opportunityId, $skills['laravel']->id, 4, true, 5.00, $now),
                $this->opportunitySkillRow($opportunityId, $skills['sql']->id, 3, true, 3.00, $now),
                $this->opportunitySkillRow($opportunityId, $skills['docker']->id, 2, false, 2.00, $now),
            ]);

            $tags = $this->createFixtureTags($now);

            DB::table('opportunity_tags')->insert([
                $this->opportunityTagRow($opportunityId, $tags['backend'], 2.00, true, $now),
                $this->opportunityTagRow($opportunityId, $tags['rest_api'], 1.00, false, $now),
                $this->opportunityTagRow($opportunityId, $tags['teamwork'], 1.00, false, $now),
            ]);

            $projectTemplateId = DB::table('project_templates')->insertGetId([
                'title' => self::PROJECT_TEMPLATE_TITLE,
                'description' => 'Deterministic evaluated project fixture for Matching E2E verification.',
                'level' => 'intermediate',
                'expected_outcome' => 'A complete backend API implementation.',
                'max_students' => 1,
                'created_by_type' => 'supervisor',
                'created_by_id' => $users['supervisor']->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Pending candidates: deliberately chosen values yield exact, explainable scores.
            $this->seedStudentSignals(
                user: $users['rank_1'],
                skills: $skills,
                skillLevels: ['laravel' => 5, 'sql' => 4, 'docker' => 3],
                tagIds: [$tags['backend'], $tags['rest_api'], $tags['teamwork']],
                projectScore: 90.00,
                activityPoints: 999,
                freshnessDays: 0,
                pointActionTypeId: $pointActionTypeId,
                projectTemplateId: $projectTemplateId,
                supervisorId: $users['supervisor']->id,
                now: $now
            );

            $this->seedStudentSignals(
                user: $users['rank_2'],
                skills: $skills,
                skillLevels: ['laravel' => 5, 'sql' => 4],
                tagIds: [$tags['backend'], $tags['rest_api']],
                projectScore: 95.00,
                activityPoints: 99,
                freshnessDays: 10,
                pointActionTypeId: $pointActionTypeId,
                projectTemplateId: $projectTemplateId,
                supervisorId: $users['supervisor']->id,
                now: $now
            );

            $this->seedStudentSignals(
                user: $users['rank_3'],
                skills: $skills,
                skillLevels: ['laravel' => 2, 'sql' => 3, 'docker' => 2],
                tagIds: [$tags['teamwork']],
                projectScore: 100.00,
                activityPoints: 9,
                freshnessDays: 20,
                pointActionTypeId: $pointActionTypeId,
                projectTemplateId: $projectTemplateId,
                supervisorId: $users['supervisor']->id,
                now: $now
            );

            // Excluded candidates are intentionally perfect. If any of them appears,
            // the pending/application-pool business rule is broken.
            $excludedDefinitions = [
                'withdrawn' => ['points' => 999999, 'freshness_days' => 0],
                'rejected' => ['points' => 888888, 'freshness_days' => 0],
                'accepted' => ['points' => 777777, 'freshness_days' => 0],
                'non_applicant' => ['points' => 666666, 'freshness_days' => 0],
            ];

            foreach ($excludedDefinitions as $key => $signal) {
                $this->seedStudentSignals(
                    user: $users[$key],
                    skills: $skills,
                    skillLevels: ['laravel' => 5, 'sql' => 5, 'docker' => 5],
                    tagIds: [$tags['backend'], $tags['rest_api'], $tags['teamwork']],
                    projectScore: 100.00,
                    activityPoints: $signal['points'],
                    freshnessDays: $signal['freshness_days'],
                    pointActionTypeId: $pointActionTypeId,
                    projectTemplateId: $projectTemplateId,
                    supervisorId: $users['supervisor']->id,
                    now: $now
                );
            }

            $applicationIds = [];

            $applicationIds['rank_1'] = DB::table('applications')->insertGetId(
                $this->applicationRow($opportunityId, $users['rank_1']->id, 'pending', $now->copy()->subHours(3))
            );
            $applicationIds['rank_2'] = DB::table('applications')->insertGetId(
                $this->applicationRow($opportunityId, $users['rank_2']->id, 'pending', $now->copy()->subHours(2))
            );
            $applicationIds['rank_3'] = DB::table('applications')->insertGetId(
                $this->applicationRow($opportunityId, $users['rank_3']->id, 'pending', $now->copy()->subHour())
            );

            DB::table('applications')->insert([
                $this->applicationRow($opportunityId, $users['withdrawn']->id, 'withdrawn', $now->copy()->subHours(6)),
                $this->applicationRow($opportunityId, $users['rejected']->id, 'rejected', $now->copy()->subHours(5)),
                $this->applicationRow($opportunityId, $users['accepted']->id, 'accepted', $now->copy()->subHours(4)),
            ]);

            // Restore exact user freshness timestamps after all Eloquent role/pivot work.
            DB::table('users')->where('id', $users['rank_1']->id)->update(['updated_at' => $now]);
            DB::table('users')->where('id', $users['rank_2']->id)->update(['updated_at' => $now->copy()->subDays(10)]);
            DB::table('users')->where('id', $users['rank_3']->id)->update(['updated_at' => $now->copy()->subDays(20)]);
            DB::table('users')->whereIn('id', [
                $users['withdrawn']->id,
                $users['rejected']->id,
                $users['accepted']->id,
                $users['non_applicant']->id,
            ])->update(['updated_at' => $now]);

            return [
                'company_id' => (int) $company->id,
                'other_company_id' => (int) $otherCompany->id,
                'opportunity_id' => (int) $opportunityId,
                'application_ids' => $applicationIds,
                'user_ids' => collect($users)->map(fn (User $user): int => (int) $user->id)->all(),
            ];
        });

        $tokens = $this->createPostmanTokens($dataset['user_ids']);
        $ranking = $this->verifyFixtureRanking(
            companyId: $dataset['company_id'],
            opportunityId: $dataset['opportunity_id']
        );

        $environmentPath = $this->writeGeneratedPostmanEnvironment(
            opportunityId: $dataset['opportunity_id'],
            tokens: $tokens
        );

        $this->printSummary(
            opportunityId: $dataset['opportunity_id'],
            environmentPath: $environmentPath,
            ranking: $ranking
        );
    }

    private function cleanupPreviousFixture(): void
    {
        DB::table('project_templates')
            ->where('title', self::PROJECT_TEMPLATE_TITLE)
            ->delete();

        DB::table('opportunities')
            ->where('title', self::OPPORTUNITY_TITLE)
            ->delete();

        DB::table('companies')
            ->whereIn('website', [
                self::COMPANY_WEBSITE,
                self::OTHER_COMPANY_WEBSITE,
            ])
            ->delete();

        $emails = collect(self::USERS)->pluck('email')->all();

        User::query()
            ->whereIn('email', $emails)
            ->get()
            ->each(function (User $user): void {
                $user->tokens()->delete();
                $user->syncRoles([]);
                $user->delete();
            });

        DB::table('tags')
            ->where('type', self::TAG_TYPE)
            ->delete();
    }

    private function ensureRoles(): void
    {
        foreach (['company', 'student', 'supervisor'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function resolveCommunityPointActionTypeId(): int
    {
        $query = fn () => DB::table('point_action_types as pat')
            ->join('point_rules as pr', 'pr.id', '=', 'pat.point_rule_id')
            ->where('pr.action_type', 'community_post_created')
            ->value('pat.id');

        $pointActionTypeId = $query();

        if ($pointActionTypeId === null) {
            $this->call(PointRulesSeeder::class);
            $pointActionTypeId = $query();
        }

        if ($pointActionTypeId === null) {
            throw new RuntimeException('Unable to resolve a point action type for Matching E2E activity fixtures.');
        }

        return (int) $pointActionTypeId;
    }

    private function createFixtureUser(string $name, string $email, string $role): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'is_active' => true,
        ]);

        $user->forceFill([
            'email_verified' => true,
            'is_verified_by_admin' => 'accepted',
        ])->save();

        $user->assignRole($role);

        return $user;
    }

    /**
     * Reuse canonical project skills when they already exist; otherwise create them.
     *
     * @return array{laravel: Skill, sql: Skill, docker: Skill}
     */
    private function ensureSkills(): array
    {
        return [
            'laravel' => Skill::query()->firstOrCreate(
                ['normalized_name' => 'laravel'],
                ['name' => 'Laravel', 'category' => 'Framework']
            ),
            'sql' => Skill::query()->firstOrCreate(
                ['normalized_name' => 'sql'],
                ['name' => 'SQL', 'category' => 'Database']
            ),
            'docker' => Skill::query()->firstOrCreate(
                ['normalized_name' => 'docker'],
                ['name' => 'Docker', 'category' => 'DevOps']
            ),
        ];
    }

    /**
     * @return array{backend:int, rest_api:int, teamwork:int}
     */
    private function createFixtureTags($now): array
    {
        return [
            'backend' => DB::table('tags')->insertGetId([
                'name' => 'Backend',
                'type' => self::TAG_TYPE,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'rest_api' => DB::table('tags')->insertGetId([
                'name' => 'REST API',
                'type' => self::TAG_TYPE,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'teamwork' => DB::table('tags')->insertGetId([
                'name' => 'Teamwork',
                'type' => self::TAG_TYPE,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];
    }

    /**
     * @param array{laravel: Skill, sql: Skill, docker: Skill} $skills
     * @param array<string, int> $skillLevels
     * @param array<int, int> $tagIds
     */
    private function seedStudentSignals(
        User $user,
        array $skills,
        array $skillLevels,
        array $tagIds,
        float $projectScore,
        int $activityPoints,
        int $freshnessDays,
        int $pointActionTypeId,
        int $projectTemplateId,
        int $supervisorId,
        $now
    ): void {
        foreach ($skillLevels as $skillKey => $level) {
            DB::table('user_skills')->insert([
                'UserId' => $user->id,
                'SkillId' => $skills[$skillKey]->id,
                'ProficiencyLevel' => $level,
                'ConfidenceScore' => 1.00,
                'Source' => 'matching_e2e',
                'Verified' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($tagIds as $tagId) {
            DB::table('user_tags')->insert([
                'user_id' => $user->id,
                'tag_id' => $tagId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('point_transactions')->insert([
            'user_id' => $user->id,
            'points' => $activityPoints,
            'point_action_type_id' => $pointActionTypeId,
            'reference_type' => User::class,
            'reference_id' => $user->id,
            'description' => 'Matching E2E deterministic activity fixture.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $assignmentId = DB::table('project_assignments')->insertGetId([
            'project_template_id' => $projectTemplateId,
            'supervisor_id' => $supervisorId,
            'status' => 'completed',
            'progress_percentage' => 100,
            'submission_url' => null,
            'github_link' => 'https://github.com/example/matching-e2e-project',
            'assigned_at' => $now->copy()->subMonth(),
            'submitted_at' => $now->copy()->subWeeks(2),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('project_assignment_members')->insert([
            'project_assignment_id' => $assignmentId,
            'student_id' => $user->id,
            'role' => 'developer',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('project_evaluations')->insert([
            'project_assignment_id' => $assignmentId,
            'supervisor_id' => $supervisorId,
            'student_id' => $user->id,
            'total_score' => $projectScore,
            'final_grade' => $projectScore,
            'status' => 'approved',
            'general_comment' => 'Matching E2E deterministic project evaluation.',
            'summary_metrics' => json_encode([
                'source' => 'matching_e2e',
                'deterministic' => true,
            ], JSON_THROW_ON_ERROR),
            'evaluated_at' => $now->copy()->subWeek(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')
            ->where('id', $user->id)
            ->update(['updated_at' => $now->copy()->subDays($freshnessDays)]);
    }

    private function opportunitySkillRow(
        int $opportunityId,
        int $skillId,
        int $requiredLevel,
        bool $mandatory,
        float $weight,
        $now
    ): array {
        return [
            'opportunity_id' => $opportunityId,
            'skill_id' => $skillId,
            'required_level' => $requiredLevel,
            'mandatory' => $mandatory,
            'weight' => $weight,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function opportunityTagRow(
        int $opportunityId,
        int $tagId,
        float $weight,
        bool $mandatory,
        $now
    ): array {
        return [
            'opportunity_id' => $opportunityId,
            'tag_id' => $tagId,
            'weight' => $weight,
            'mandatory' => $mandatory,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function applicationRow(
        int $opportunityId,
        int $userId,
        string $status,
        $appliedAt
    ): array {
        return [
            'opportunity_id' => $opportunityId,
            'user_id' => $userId,
            'cv_id' => null,
            'cover_letter' => 'Matching E2E deterministic application.',
            'status' => $status,
            'match_score' => null,
            'match_reasons' => null,
            'applied_at' => $appliedAt,
            'reviewed_at' => null,
            'reviewer_notes' => null,
            'created_at' => $appliedAt,
            'updated_at' => $appliedAt,
        ];
    }

    /**
     * @param array<string, int> $userIds
     * @return array<string, string>
     */
    private function createPostmanTokens(array $userIds): array
    {
        $keys = ['company', 'other_company', 'rank_1', 'rank_2', 'rank_3'];
        $tokens = [];

        foreach ($keys as $key) {
            $user = User::query()->findOrFail($userIds[$key]);
            $user->tokens()->where('name', 'matching-e2e-postman')->delete();

            $tokens[$key] = $user
                ->createToken('matching-e2e-postman')
                ->plainTextToken;
        }

        return $tokens;
    }

    private function verifyFixtureRanking(int $companyId, int $opportunityId): array
    {
        $result = app(GetTopCandidatesForOpportunityHandler::class)->handle(
            new GetTopCandidatesForOpportunity(
                companyId: $companyId,
                opportunityId: $opportunityId,
                limit: 20
            )
        );

        $expected = [
            self::USERS['rank_1']['email'] => 98.00,
            self::USERS['rank_2']['email'] => 80.50,
            self::USERS['rank_3']['email'] => 68.75,
        ];

        $actualEmails = $result
            ->pluck('student.email')
            ->values()
            ->all();

        if ($actualEmails !== array_keys($expected)) {
            throw new RuntimeException(
                'Matching E2E fixture verification failed: candidate order/pool differs from the deterministic contract.'
            );
        }

        foreach ($result as $candidate) {
            $email = $candidate['student']['email'];
            $expectedScore = $expected[$email];
            $actualScore = (float) $candidate['final_score'];

            if (abs($actualScore - $expectedScore) > 0.01) {
                throw new RuntimeException(
                    "Matching E2E fixture verification failed for {$email}: expected {$expectedScore}, got {$actualScore}."
                );
            }
        }

        return $result->all();
    }

    /**
     * @param array<string, string> $tokens
     */
    private function writeGeneratedPostmanEnvironment(int $opportunityId, array $tokens): string
    {
        $path = storage_path('app/postman/Jisr_Matching_E2E.generated.postman_environment.json');

        File::ensureDirectoryExists(dirname($path));

        $environment = [
            'id' => (string) Str::uuid(),
            'name' => 'Jisr Matching E2E - Generated Local',
            'values' => [
                $this->environmentVariable('base_url', 'http://127.0.0.1:8000'),
                $this->environmentVariable('opportunity_id', (string) $opportunityId),
                $this->environmentVariable('company_token', $tokens['company'], 'secret'),
                $this->environmentVariable('other_company_token', $tokens['other_company'], 'secret'),
                $this->environmentVariable('student_1_token', $tokens['rank_1'], 'secret'),
                $this->environmentVariable('student_2_token', $tokens['rank_2'], 'secret'),
                $this->environmentVariable('student_3_token', $tokens['rank_3'], 'secret'),
                $this->environmentVariable('expected_rank_1_email', self::USERS['rank_1']['email']),
                $this->environmentVariable('expected_rank_2_email', self::USERS['rank_2']['email']),
                $this->environmentVariable('expected_rank_3_email', self::USERS['rank_3']['email']),
                $this->environmentVariable('expected_rank_1_score', '98.00'),
                $this->environmentVariable('expected_rank_2_score', '80.50'),
                $this->environmentVariable('expected_rank_3_score', '68.75'),
                $this->environmentVariable('excluded_withdrawn_email', self::USERS['withdrawn']['email']),
                $this->environmentVariable('excluded_rejected_email', self::USERS['rejected']['email']),
                $this->environmentVariable('excluded_accepted_email', self::USERS['accepted']['email']),
                $this->environmentVariable('excluded_non_applicant_email', self::USERS['non_applicant']['email']),
            ],
            '_postman_variable_scope' => 'environment',
            '_postman_exported_at' => now()->toIso8601String(),
            '_postman_exported_using' => 'Jisr MatchingE2ESeeder',
        ];

        File::put(
            $path,
            json_encode($environment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        return $path;
    }

    private function environmentVariable(string $key, string $value, string $type = 'default'): array
    {
        return [
            'key' => $key,
            'value' => $value,
            'type' => $type,
            'enabled' => true,
        ];
    }

    private function printSummary(int $opportunityId, string $environmentPath, array $ranking): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->newLine();
        $this->command->info('Matching E2E dataset is ready.');
        $this->command->line("Opportunity ID: {$opportunityId}");
        $this->command->line("Generated Postman environment: {$environmentPath}");
        $this->command->line('Expected ranking:');

        foreach ($ranking as $candidate) {
            $this->command->line(sprintf(
                '  #%d  %s  final_score=%.2f  [skills=%.2f, projects=%.2f, tags=%.2f, activity=%.2f, freshness=%.2f]',
                $candidate['rank'],
                $candidate['student']['email'],
                $candidate['final_score'],
                $candidate['scores']['skill_score'],
                $candidate['scores']['project_score'],
                $candidate['scores']['tag_score'],
                $candidate['scores']['activity_score'],
                $candidate['scores']['freshness_score']
            ));
        }

        $this->command->warn('The generated Postman environment contains local Sanctum tokens. Do not commit or share it.');
    }
}
