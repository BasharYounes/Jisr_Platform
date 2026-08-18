<?php

namespace Tests\Feature;

use App\Domains\Matching\Handler\GetTopCandidatesForOpportunityHandler;
use App\Domains\Matching\Queries\GetTopCandidatesForOpportunity;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\MatchingTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MatchingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_real_applicants_are_ranked_and_sorted_by_smart_score(): void
    {
        $this->seed(MatchingTestSeeder::class);

        $opportunity = Opportunity::query()
            ->where('title', 'Junior Backend Developer - Matching Test')
            ->firstOrFail();

        $skillIds = DB::table('skills')
            ->whereIn('name', ['Laravel', 'SQL', 'Docker'])
            ->pluck('id', 'name');

        $ahmed = User::factory()->create(['name' => 'Ahmed Applicant']);
        $sara = User::factory()->create(['name' => 'Sara Applicant']);
        $notApplicant = User::factory()->create(['name' => 'Not Applicant']);

        DB::table('applications')->insert([
            [
                'opportunity_id' => $opportunity->id,
                'user_id' => $ahmed->id,
                'cv_id' => null,
                'status' => 'pending',
                'match_score' => null,
                'match_reasons' => null,
                'applied_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'opportunity_id' => $opportunity->id,
                'user_id' => $sara->id,
                'cv_id' => null,
                'status' => 'pending',
                'match_score' => null,
                'match_reasons' => null,
                'applied_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('user_skills')->insert([
            [
                'UserId' => $ahmed->id,
                'SkillId' => $skillIds['Laravel'],
                'ProficiencyLevel' => 5,
                'ConfidenceScore' => 1,
                'Source' => 'ai_assessment',
                'Verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'UserId' => $ahmed->id,
                'SkillId' => $skillIds['SQL'],
                'ProficiencyLevel' => 5,
                'ConfidenceScore' => 1,
                'Source' => 'ai_assessment',
                'Verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'UserId' => $ahmed->id,
                'SkillId' => $skillIds['Docker'],
                'ProficiencyLevel' => 3,
                'ConfidenceScore' => 1,
                'Source' => 'ai_assessment',
                'Verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'UserId' => $sara->id,
                'SkillId' => $skillIds['Laravel'],
                'ProficiencyLevel' => 4,
                'ConfidenceScore' => 1,
                'Source' => 'ai_assessment',
                'Verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'UserId' => $sara->id,
                'SkillId' => $skillIds['SQL'],
                'ProficiencyLevel' => 4,
                'ConfidenceScore' => 1,
                'Source' => 'ai_assessment',
                'Verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'UserId' => $notApplicant->id,
                'SkillId' => $skillIds['Laravel'],
                'ProficiencyLevel' => 5,
                'ConfidenceScore' => 1,
                'Source' => 'ai_assessment',
                'Verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'UserId' => $notApplicant->id,
                'SkillId' => $skillIds['SQL'],
                'ProficiencyLevel' => 5,
                'ConfidenceScore' => 1,
                'Source' => 'ai_assessment',
                'Verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'UserId' => $notApplicant->id,
                'SkillId' => $skillIds['Docker'],
                'ProficiencyLevel' => 5,
                'ConfidenceScore' => 1,
                'Source' => 'ai_assessment',
                'Verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $handler = $this->app->make(
            GetTopCandidatesForOpportunityHandler::class
        );

        $result = $handler->handle(
            new GetTopCandidatesForOpportunity(
                companyId: (int) $opportunity->company_id,
                opportunityId: (int) $opportunity->id,
                limit: 20
            )
        );

        $this->assertCount(2, $result);
        $this->assertSame($ahmed->id, $result[0]['user_id']);
        $this->assertSame($sara->id, $result[1]['user_id']);
        $this->assertSame(1, $result[0]['rank']);
        $this->assertSame(2, $result[1]['rank']);

        $rankedIds = $result->pluck('user_id')->all();
        $this->assertNotContains($notApplicant->id, $rankedIds);

        $this->assertArrayHasKey('explanation', $result[0]);
        $this->assertArrayHasKey('scores', $result[0]);
        $this->assertGreaterThan($result[1]['final_score'], $result[0]['final_score']);
    }

    public function test_only_pending_applications_are_included_in_ranking(): void
    {
        $this->seed(MatchingTestSeeder::class);

        $opportunity = Opportunity::query()
            ->where('title', 'Junior Backend Developer - Matching Test')
            ->firstOrFail();

        $pending = User::factory()->create(['name' => 'Pending Applicant']);
        $withdrawn = User::factory()->create(['name' => 'Withdrawn Applicant']);
        $rejected = User::factory()->create(['name' => 'Rejected Applicant']);
        $accepted = User::factory()->create(['name' => 'Accepted Applicant']);

        DB::table('applications')->insert([
            $this->applicationRow($opportunity->id, $pending->id, 'pending'),
            $this->applicationRow($opportunity->id, $withdrawn->id, 'withdrawn'),
            $this->applicationRow($opportunity->id, $rejected->id, 'rejected'),
            $this->applicationRow($opportunity->id, $accepted->id, 'accepted'),
        ]);

        $handler = $this->app->make(
            GetTopCandidatesForOpportunityHandler::class
        );

        $result = $handler->handle(
            new GetTopCandidatesForOpportunity(
                companyId: (int) $opportunity->company_id,
                opportunityId: (int) $opportunity->id,
                limit: 20
            )
        );

        $this->assertCount(1, $result);
        $this->assertSame($pending->id, $result[0]['user_id']);
        $this->assertSame('pending', $result[0]['application_status']);

        $rankedIds = $result->pluck('user_id')->all();
        $this->assertNotContains($withdrawn->id, $rankedIds);
        $this->assertNotContains($rejected->id, $rankedIds);
        $this->assertNotContains($accepted->id, $rankedIds);
    }

    public function test_final_score_uses_the_55_20_10_10_5_formula(): void
    {
        $this->seed(MatchingTestSeeder::class);

        $opportunity = Opportunity::query()
            ->where('title', 'Junior Backend Developer - Matching Test')
            ->firstOrFail();

        $skillIds = DB::table('skills')
            ->whereIn('name', ['Laravel', 'SQL', 'Docker'])
            ->pluck('id', 'name');

        $student = User::factory()->create(['name' => 'Formula Applicant']);

        DB::table('applications')->insert(
            $this->applicationRow($opportunity->id, $student->id, 'pending')
        );

        DB::table('user_skills')->insert([
            [
                'UserId' => $student->id,
                'SkillId' => $skillIds['Laravel'],
                'ProficiencyLevel' => 5,
                'ConfidenceScore' => 1,
                'Source' => 'ai_assessment',
                'Verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'UserId' => $student->id,
                'SkillId' => $skillIds['SQL'],
                'ProficiencyLevel' => 4,
                'ConfidenceScore' => 1,
                'Source' => 'ai_assessment',
                'Verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'UserId' => $student->id,
                'SkillId' => $skillIds['Docker'],
                'ProficiencyLevel' => 2,
                'ConfidenceScore' => 1,
                'Source' => 'ai_assessment',
                'Verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $handler = $this->app->make(
            GetTopCandidatesForOpportunityHandler::class
        );

        $candidate = $handler->handle(
            new GetTopCandidatesForOpportunity(
                companyId: (int) $opportunity->company_id,
                opportunityId: (int) $opportunity->id,
                limit: 20
            )
        )->firstOrFail();

        $expectedFinalScore = round(
            ($candidate['skill_score'] * 0.55)
            + ($candidate['project_score'] * 0.20)
            + ($candidate['tag_score'] * 0.10)
            + ($candidate['activity_score'] * 0.10)
            + ($candidate['freshness'] * 0.05),
            2
        );

        $this->assertSame($expectedFinalScore, $candidate['final_score']);
        $this->assertSame(
            $candidate['scores']['freshness_score'],
            $candidate['freshness']
        );
        $this->assertGreaterThanOrEqual(0, $candidate['freshness']);
        $this->assertLessThanOrEqual(100, $candidate['freshness']);
        $this->assertSame(55, GetTopCandidatesForOpportunityHandler::weights()['skills']);
        $this->assertSame(20, GetTopCandidatesForOpportunityHandler::weights()['projects']);
        $this->assertSame(10, GetTopCandidatesForOpportunityHandler::weights()['tags']);
        $this->assertSame(10, GetTopCandidatesForOpportunityHandler::weights()['activity']);
        $this->assertSame(5, GetTopCandidatesForOpportunityHandler::weights()['freshness']);
    }

    public function test_company_can_access_ranking_only_for_its_own_opportunity(): void
    {
        $this->seed(MatchingTestSeeder::class);

        $opportunity = Opportunity::query()
            ->where('title', 'Junior Backend Developer - Matching Test')
            ->firstOrFail();

        $companyUser = User::factory()->create();
        Role::findOrCreate('company', 'web');
        $companyUser->assignRole('company');

        $companyUser->companies()->attach(
            $opportunity->company_id,
            ['role' => 'owner']
        );

        Sanctum::actingAs($companyUser);

        $this->getJson(
            "/api/opportunities/{$opportunity->id}/top-candidates?limit=10"
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.weights.skills', 55)
            ->assertJsonPath('meta.weights.projects', 20)
            ->assertJsonPath('meta.weights.tags', 10)
            ->assertJsonPath('meta.weights.activity', 10)
            ->assertJsonPath('meta.weights.freshness', 5);

        $otherCompany = Company::query()->create([
            'industry' => 'Other',
            'location' => 'Damascus',
        ]);

        $otherOpportunity = Opportunity::query()->create([
            'company_id' => $otherCompany->id,
            'title' => 'Other Company Opportunity',
            'description' => null,
            'type' => 'job',
            'location' => null,
            'salary_min' => null,
            'salary_max' => null,
            'status' => 'published',
            'deadline' => now()->addWeek(),
            'posted_at' => now(),
        ]);

        $this->getJson(
            "/api/opportunities/{$otherOpportunity->id}/top-candidates"
        )->assertNotFound();
    }

    public function test_student_cannot_access_matching_endpoint(): void
    {
        $this->seed(MatchingTestSeeder::class);

        $opportunity = Opportunity::query()
            ->where('title', 'Junior Backend Developer - Matching Test')
            ->firstOrFail();

        $student = User::factory()->create();
        Role::findOrCreate('student', 'web');
        $student->assignRole('student');

        Sanctum::actingAs($student);

        $this->getJson(
            "/api/opportunities/{$opportunity->id}/top-candidates"
        )->assertForbidden();
    }

    public function test_matching_endpoint_requires_authentication(): void
    {
        $this->seed(MatchingTestSeeder::class);

        $opportunity = Opportunity::query()
            ->where('title', 'Junior Backend Developer - Matching Test')
            ->firstOrFail();

        $this->getJson(
            "/api/opportunities/{$opportunity->id}/top-candidates"
        )->assertUnauthorized();
    }

    private function applicationRow(
        int $opportunityId,
        int $userId,
        string $status
    ): array {
        return [
            'opportunity_id' => $opportunityId,
            'user_id' => $userId,
            'cv_id' => null,
            'status' => $status,
            'match_score' => null,
            'match_reasons' => null,
            'applied_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
