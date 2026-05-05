<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Domain\Matching\Queries\GetTopCandidatesForOpportunity;
use App\Domain\Matching\Handlers\GetTopCandidatesForOpportunityHandler;

class MatchingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidates_are_ranked_correctly()
    {
        $this->seed(\Database\Seeders\MatchingTestSeeder::class);

        // أحمد
        $ahmed = User::factory()->create(['UserID' => 1]);

        DB::table('UserSkill')->insert([
            ['UserId' => 1, 'SkillId' => 1, 'ProficiencyLevel' => 5],
            ['UserId' => 1, 'SkillId' => 2, 'ProficiencyLevel' => 5],
            ['UserId' => 1, 'SkillId' => 3, 'ProficiencyLevel' => 3],
        ]);

        // سارة
        $sara = User::factory()->create(['UserID' => 2]);

        DB::table('UserSkill')->insert([
            ['UserId' => 2, 'SkillId' => 1, 'ProficiencyLevel' => 4],
            ['UserId' => 2, 'SkillId' => 2, 'ProficiencyLevel' => 4],
        ]);

        // محمد
        $mohammed = User::factory()->create(['UserID' => 3]);

        DB::table('UserSkill')->insert([
            ['UserId' => 3, 'SkillId' => 1, 'ProficiencyLevel' => 5],
        ]);

        $handler = new GetTopCandidatesForOpportunityHandler();

        $result = $handler->handle(
            new GetTopCandidatesForOpportunity(1, 10)
        );

        $this->assertEquals(1, $result[0]['user_id']); // أحمد أولاً
        $this->assertEquals(2, $result[1]['user_id']); // سارة ثانيًا

        $ids = collect($result)->pluck('user_id')->toArray();

        $this->assertNotContains(3, $ids); // محمد مستبعد
    }
}
