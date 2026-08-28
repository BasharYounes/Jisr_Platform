<?php

namespace Database\Seeders;

use App\Domains\Matching\Handler\GetTopCandidatesForOpportunityHandler;
use App\Domains\Matching\Queries\GetTopCandidatesForOpportunity;
use App\Models\Opportunity;
use App\Models\ProjectEvaluation;
use App\Models\ProjectTemplate;
use App\Models\Skill;
use App\Models\User;
use App\Services\Opportunities\CompanyOpportunityService;
use App\Services\Opportunities\OpportunityRecommendationService;
use App\Services\Opportunities\StudentOpportunityApplicationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class GraduationDemoOpportunityMatchingSeeder extends Seeder
{
    private const COMPANY_EMAIL = 'karamalah.kweader@gmail.com';
    private const STUDENT_EMAIL = 'leleen830@gmail.com';
    private const SUPERVISOR_EMAIL = 'ihsaskhatib35@gmail.com';
    private const GUIDED_PROJECT_TITLE = 'Inventory Management REST API';
    private const OPPORTUNITY_TITLE = 'Junior Backend Developer - Python & Flask';
    private const FIXTURE_PROJECT_TITLE = '[GRAD DEMO] Applicant Evidence Project';
    private const TAG_TYPE = 'graduation_demo_matching';
    private const POINT_DESCRIPTION = 'Graduation demo matching activity signal.';

    private const FIXTURE_USERS = [
        'maya' => ['name' => 'Maya Hassan', 'email' => 'grad.matching.maya@jisr.test'],
        'omar' => ['name' => 'Omar Khaled', 'email' => 'grad.matching.omar@jisr.test'],
        'sara' => ['name' => 'Sara Nasser', 'email' => 'grad.matching.sara@jisr.test'],
    ];

    public function run(): void
    {
        $this->ensureSafeEnvironment();

        $companyUser = $this->resolveUser(self::COMPANY_EMAIL, 'company', 'company');
        $student = $this->resolveUser(self::STUDENT_EMAIL, 'student', 'student');
        $supervisor = $this->resolveUser(self::SUPERVISOR_EMAIL, 'supervisor', 'supervisor');

        $company = $companyUser->companies()->first();
        if (! $company) {
            throw new RuntimeException('The graduation demo company account is not attached to a companies row: '.self::COMPANY_EMAIL);
        }

        $studentProjectScore = $this->resolveStudentGuidedProjectScore($student);

        /*
         * Important:
         * The production matching engine has NO minimum project-grade
         * eligibility threshold. It uses any submitted/approved project
         * evaluation as one signal worth 20% of the final ranking.
         *
         * Therefore the graduation fixture must preserve the supervisor's
         * real score instead of inventing a >= 70 business rule here.
         */

        $skills = $this->resolveSkills();
        $pointActionTypeId = $this->resolveCommunityPointActionTypeId();

        $dataset = DB::transaction(function () use ($company, $student, $supervisor, $skills, $pointActionTypeId): array {
            $this->cleanupPreviousFixture((int) $company->id, (int) $student->id, (int) $supervisor->id);

            $fixtureUsers = $this->createFixtureUsers();
            $opportunity = $this->createPublishedOpportunity((int) $company->id, $skills);
            $tags = $this->createTags();
            $this->attachOpportunityTags((int) $opportunity->id, $tags);
            $this->seedLeleenSignals($student, $tags, $pointActionTypeId);

            $fixtureProjectTemplateId = $this->createFixtureEvaluationTemplate((int) $supervisor->id);

            $this->seedFixtureCandidateSignals(
                $fixtureUsers['maya'], $skills,
                ['Python' => 3, 'SQL' => 3, 'Flask' => 1, 'Git' => 1],
                [$tags['Backend'], $tags['REST API']],
                60.00, 99, 5, $pointActionTypeId, $fixtureProjectTemplateId, (int) $supervisor->id
            );

            $this->seedFixtureCandidateSignals(
                $fixtureUsers['omar'], $skills,
                ['Python' => 2, 'SQL' => 3, 'Flask' => 2],
                [$tags['Backend']],
                82.00, 19, 12, $pointActionTypeId, $fixtureProjectTemplateId, (int) $supervisor->id
            );

            $this->seedFixtureCandidateSignals(
                $fixtureUsers['sara'], $skills,
                ['Python' => 3, 'SQL' => 2, 'Flask' => 1],
                [$tags['Teamwork']],
                90.00, 4, 20, $pointActionTypeId, $fixtureProjectTemplateId, (int) $supervisor->id
            );

            foreach (['maya', 'omar', 'sara'] as $key) {
                $this->createFixtureApplication($opportunity, $fixtureUsers[$key]);
            }

            return ['opportunity_id' => (int) $opportunity->id];
        });

        $studentApplication = app(StudentOpportunityApplicationService::class)->apply(
            studentId: (int) $student->id,
            opportunityId: $dataset['opportunity_id'],
            data: [
                'cover_letter' => 'I am applying after completing my guided Flask/SQL project and strengthening the backend skills identified through my Jisr learning journey.',
            ]
        );

        DB::table('users')->where('id', $student->id)->update(['updated_at' => now()]);

        $ranking = $this->verifyRanking((int) $company->id, $dataset['opportunity_id'], (int) $student->id);
        $this->verifyStudentApplication((int) $studentApplication->id, (int) $student->id, $dataset['opportunity_id']);

        $this->printSummary(
            $dataset['opportunity_id'],
            (int) $studentApplication->id,
            $studentProjectScore,
            (float) $studentApplication->match_score,
            $ranking
        );
    }

    private function ensureSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('GraduationDemoOpportunityMatchingSeeder is allowed only in local or testing environments.');
        }
    }

    private function resolveUser(string $email, string $role, string $label): User
    {
        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            throw new RuntimeException("Graduation demo {$label} was not found: {$email}");
        }
        if (method_exists($user, 'hasRole') && ! $user->hasRole($role)) {
            throw new RuntimeException("{$email} exists but does not have the required '{$role}' role.");
        }
        return $user;
    }

    private function resolveStudentGuidedProjectScore(User $student): float
    {
        $evaluation = ProjectEvaluation::query()
            ->join('project_assignments', 'project_assignments.id', '=', 'project_evaluations.project_assignment_id')
            ->join('project_templates', 'project_templates.id', '=', 'project_assignments.project_template_id')
            ->where('project_templates.title', self::GUIDED_PROJECT_TITLE)
            ->where('project_evaluations.student_id', $student->id)
            ->whereIn('project_evaluations.status', ['submitted', 'approved'])
            ->latest('project_evaluations.id')
            ->select('project_evaluations.*')
            ->first();

        if (! $evaluation) {
            throw new RuntimeException(
                'No submitted/approved final evaluation was found for '.self::STUDENT_EMAIL
                .' on project "'.self::GUIDED_PROJECT_TITLE.'". Complete the supervisor final evaluation first.'
            );
        }

        $score = $evaluation->final_grade ?? $evaluation->total_score;
        if ($score === null) {
            throw new RuntimeException('The guided-project evaluation exists but has no score.');
        }
        return (float) $score;
    }

    private function resolveSkills(): array
    {
        $resolved = [];
        foreach (['Python', 'SQL', 'Flask', 'Git'] as $skillName) {
            $skill = Skill::query()->where('name', $skillName)->first();
            if (! $skill) {
                throw new RuntimeException("Required matching skill {$skillName} was not found.");
            }
            $resolved[$skillName] = $skill;
        }
        return $resolved;
    }

    private function resolveCommunityPointActionTypeId(): int
    {
        $query = fn () => DB::table('point_action_types as pat')
            ->join('point_rules as pr', 'pr.id', '=', 'pat.point_rule_id')
            ->where('pr.action_type', 'community_post_created')
            ->value('pat.id');

        $id = $query();
        if ($id === null) {
            $this->call(PointRulesSeeder::class);
            $id = $query();
        }
        if ($id === null) {
            throw new RuntimeException('Unable to resolve point action type required for matching activity signals.');
        }
        return (int) $id;
    }

    private function cleanupPreviousFixture(int $companyId, int $leleenId, int $supervisorId): void
    {
        DB::table('opportunities')
            ->where('company_id', $companyId)
            ->where('title', self::OPPORTUNITY_TITLE)
            ->delete();

        ProjectTemplate::query()
            ->where('title', self::FIXTURE_PROJECT_TITLE)
            ->where('created_by_type', 'supervisor')
            ->where('created_by_id', $supervisorId)
            ->delete();

        $oldTagIds = DB::table('tags')->where('type', self::TAG_TYPE)->pluck('id');
        if ($oldTagIds->isNotEmpty()) {
            DB::table('user_tags')->whereIn('tag_id', $oldTagIds)->delete();
            DB::table('opportunity_tags')->whereIn('tag_id', $oldTagIds)->delete();
            DB::table('tags')->whereIn('id', $oldTagIds)->delete();
        }

        DB::table('point_transactions')
            ->where('user_id', $leleenId)
            ->where('description', self::POINT_DESCRIPTION)
            ->delete();

        $fixtureEmails = collect(self::FIXTURE_USERS)->pluck('email')->all();
        User::query()->whereIn('email', $fixtureEmails)->get()->each(function (User $user): void {
            DB::table('point_transactions')->where('user_id', $user->id)->delete();
            DB::table('user_tags')->where('user_id', $user->id)->delete();
            DB::table('user_skills')->where('UserId', $user->id)->delete();
            $user->tokens()->delete();
            $user->syncRoles([]);
            $user->delete();
        });
    }

    private function createFixtureUsers(): array
    {
        Role::findOrCreate('student', 'web');
        $users = [];
        foreach (self::FIXTURE_USERS as $key => $definition) {
            $user = User::query()->create([
                'name' => $definition['name'],
                'email' => $definition['email'],
                'password' => Hash::make(Str::random(64)),
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified' => true, 'is_verified_by_admin' => 'accepted'])->save();
            $user->assignRole('student');
            $users[$key] = $user;
        }
        return $users;
    }

    private function createPublishedOpportunity(int $companyId, array $skills): Opportunity
    {
        $service = app(CompanyOpportunityService::class);
        $opportunity = $service->createOpportunity(
            companyId: $companyId,
            data: [
                'title' => self::OPPORTUNITY_TITLE,
                'description' => 'NexaTech Solutions is looking for a junior backend developer to work on Python/Flask REST APIs, SQL data models, validation, and collaborative Git workflows. The role is designed for an early-career developer with practical project evidence and solid fundamentals.',
                'type' => 'job',
                'location' => 'Remote / Hybrid',
                'salary_min' => null,
                'salary_max' => null,
                'deadline' => now()->addDays(30)->toDateTimeString(),
                'skills' => [
                    ['skill_id' => $skills['Python']->id, 'required_level' => 3, 'mandatory' => true, 'weight' => 4.00],
                    ['skill_id' => $skills['SQL']->id, 'required_level' => 3, 'mandatory' => true, 'weight' => 3.00],
                    ['skill_id' => $skills['Flask']->id, 'required_level' => 2, 'mandatory' => true, 'weight' => 2.00],
                    ['skill_id' => $skills['Git']->id, 'required_level' => 1, 'mandatory' => false, 'weight' => 1.00],
                ],
            ]
        );
        $service->publishOpportunity(companyId: $companyId, opportunityId: (int) $opportunity->id);
        return Opportunity::query()->with('skills')->findOrFail($opportunity->id);
    }

    private function createTags(): array
    {
        $tags = [];
        foreach (['Backend', 'REST API', 'Teamwork'] as $name) {
            $tags[$name] = (int) DB::table('tags')->insertGetId([
                'name' => $name,
                'type' => self::TAG_TYPE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return $tags;
    }

    private function attachOpportunityTags(int $opportunityId, array $tags): void
    {
        $now = now();
        DB::table('opportunity_tags')->insert([
            ['opportunity_id' => $opportunityId, 'tag_id' => $tags['Backend'], 'weight' => 2.00, 'mandatory' => true, 'created_at' => $now, 'updated_at' => $now],
            ['opportunity_id' => $opportunityId, 'tag_id' => $tags['REST API'], 'weight' => 1.00, 'mandatory' => false, 'created_at' => $now, 'updated_at' => $now],
            ['opportunity_id' => $opportunityId, 'tag_id' => $tags['Teamwork'], 'weight' => 1.00, 'mandatory' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function seedLeleenSignals(User $student, array $tags, int $pointActionTypeId): void
    {
        foreach (['Python' => 3, 'SQL' => 3, 'Flask' => 2, 'Git' => 1] as $skillName => $minimum) {
            $skillId = DB::table('skills')->where('name', $skillName)->value('id');
            $level = DB::table('user_skills')->where('UserId', $student->id)->where('SkillId', $skillId)->value('ProficiencyLevel');
            if ($level === null || (int) $level < $minimum) {
                throw new RuntimeException(
                    "Leleen's {$skillName} UserSkill is missing or below the demo opportunity minimum {$minimum}. Verify the assessment/CV journey first."
                );
            }
        }

        foreach ($tags as $tagId) {
            DB::table('user_tags')->insert([
                'user_id' => $student->id,
                'tag_id' => $tagId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('point_transactions')->insert([
            'user_id' => $student->id,
            'points' => 999,
            'point_action_type_id' => $pointActionTypeId,
            'reference_type' => User::class,
            'reference_id' => $student->id,
            'description' => self::POINT_DESCRIPTION,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createFixtureEvaluationTemplate(int $supervisorId): int
    {
        return (int) DB::table('project_templates')->insertGetId([
            'title' => self::FIXTURE_PROJECT_TITLE,
            'description' => 'Internal graduation-demo fixture used only to provide comparable project-evaluation signals for secondary matching candidates.',
            'level' => 'Intermediate',
            'expected_outcome' => 'Comparable backend project evidence for smart-ranking demonstration.',
            'max_students' => 1,
            'created_by_type' => 'supervisor',
            'created_by_id' => $supervisorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedFixtureCandidateSignals(
        User $user,
        array $skills,
        array $skillLevels,
        array $tagIds,
        float $projectScore,
        int $activityPoints,
        int $freshnessDays,
        int $pointActionTypeId,
        int $projectTemplateId,
        int $supervisorId
    ): void {
        $now = now();
        foreach ($skillLevels as $skillName => $level) {
            DB::table('user_skills')->insert([
                'UserId' => $user->id,
                'SkillId' => $skills[$skillName]->id,
                'ProficiencyLevel' => $level,
                'ConfidenceScore' => 0.90,
                'Source' => 'graduation_demo_matching',
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
            'description' => self::POINT_DESCRIPTION,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $assignmentId = (int) DB::table('project_assignments')->insertGetId([
            'project_template_id' => $projectTemplateId,
            'supervisor_id' => $supervisorId,
            'status' => 'completed',
            'progress_percentage' => 100,
            'submission_url' => null,
            'github_link' => null,
            'assigned_at' => $now->copy()->subMonth(),
            'submitted_at' => $now->copy()->subWeeks(2),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('project_assignment_members')->insert([
            'project_assignment_id' => $assignmentId,
            'student_id' => $user->id,
            'role' => 'Backend Developer',
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
            'general_comment' => 'Prepared graduation-demo project evaluation used only as a ranking comparison signal.',
            'summary_metrics' => json_encode(['source' => 'graduation_demo_matching', 'prepared_fixture' => true], JSON_THROW_ON_ERROR),
            'evaluated_at' => $now->copy()->subWeek(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('users')->where('id', $user->id)->update(['updated_at' => $now->copy()->subDays($freshnessDays)]);
    }

    private function createFixtureApplication(Opportunity $opportunity, User $user): void
    {
        $match = app(OpportunityRecommendationService::class)->calculateMatch(
            opportunity: $opportunity,
            studentUserId: (int) $user->id
        );

        DB::table('applications')->insert([
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
            'cv_id' => null,
            'cover_letter' => 'Prepared secondary applicant for the graduation smart-ranking demonstration.',
            'status' => 'pending',
            'match_score' => $match['score'],
            'match_reasons' => json_encode($match['reasons'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'applied_at' => now()->subHours(3),
            'reviewed_at' => null,
            'reviewer_notes' => null,
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);
    }

    private function verifyRanking(int $companyId, int $opportunityId, int $leleenId): array
    {
        $result = app(GetTopCandidatesForOpportunityHandler::class)->handle(
            new GetTopCandidatesForOpportunity(companyId: $companyId, opportunityId: $opportunityId, limit: 20)
        );

        if ($result->count() !== 4) {
            throw new RuntimeException('Smart-ranking verification expected exactly 4 pending applicants, found '.$result->count().'.');
        }

        $top = $result->first();
        if (! $top || (int) $top['user_id'] !== $leleenId) {
            throw new RuntimeException('Smart-ranking verification failed: Leleen is not ranked #1. Current order: '.$result->pluck('student.email')->implode(', '));
        }

        foreach ($result as $candidate) {
            if (($candidate['application_status'] ?? null) !== 'pending') {
                throw new RuntimeException('Smart-ranking pool contains a non-pending application.');
            }
        }

        return $result->all();
    }

    private function verifyStudentApplication(int $applicationId, int $studentId, int $opportunityId): void
    {
        $application = DB::table('applications')->where('id', $applicationId)->first();
        if (
            ! $application
            || (int) $application->user_id !== $studentId
            || (int) $application->opportunity_id !== $opportunityId
            || $application->status !== 'pending'
            || $application->cv_id === null
        ) {
            throw new RuntimeException('Leleen application verification failed. The real application must be pending and linked to her CV.');
        }
    }

    private function printSummary(
        int $opportunityId,
        int $applicationId,
        float $studentProjectScore,
        float $applicationMatchScore,
        array $ranking
    ): void {
        if ($this->command === null) {
            return;
        }

        $this->command->newLine();
        $this->command->info('Graduation company opportunity + smart matching dataset seeded successfully.');
        $this->command->line('Company account: '.self::COMPANY_EMAIL);
        $this->command->line('Opportunity: '.self::OPPORTUNITY_TITLE.' | Opportunity #'.$opportunityId);
        $this->command->line('Leleen Application #'.$applicationId.' | CV linked: YES | skill match snapshot: '.number_format($applicationMatchScore, 2).'%');
        $this->command->line('Leleen real guided-project evaluation signal: '.number_format($studentProjectScore, 2).'%');

        $rows = collect($ranking)->map(fn (array $candidate): array => [
            $candidate['rank'],
            $candidate['student']['name'] ?? $candidate['student']['email'],
            number_format((float) $candidate['final_score'], 2),
            number_format((float) $candidate['scores']['skill_score'], 2),
            number_format((float) $candidate['scores']['project_score'], 2),
            number_format((float) $candidate['scores']['tag_score'], 2),
            number_format((float) $candidate['scores']['activity_score'], 2),
            number_format((float) $candidate['scores']['freshness_score'], 2),
        ])->all();

        $this->command->table(['Rank', 'Candidate', 'Final', 'Skills', 'Projects', 'Tags', 'Activity', 'Freshness'], $rows);
        $this->command->info('Verified: Leleen is ranked #1 by the real GetTopCandidatesForOpportunityHandler.');
        $this->command->line('Matching weights: Skills 55% | Projects 20% | Tags 10% | Activity 10% | Freshness 5%.');
        $this->command->warn('The ranking is decision support only. It does not automatically hire or accept any applicant.');
        $this->command->line('Company live ranking endpoint: GET /api/opportunities/'.$opportunityId.'/top-candidates');
    }
}
