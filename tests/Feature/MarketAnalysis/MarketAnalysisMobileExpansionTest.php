<?php

namespace Tests\Feature\MarketAnalysis;

use Database\Seeders\MarketAnalysisMobileSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketAnalysisMobileExpansionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_mobile_market_dictionary_is_seeded(): void
    {
        $this->seed(MarketAnalysisMobileSeeder::class);

        $careerPath = DB::table('career_paths')
            ->where('Name', 'Mobile Developer')
            ->first();

        $this->assertNotNull($careerPath);

        $expectedSkills = [
            'Flutter',
            'Dart',
            'Firebase',
            'State Management',
            'Android',
            'iOS',
        ];

        $actualSkills = DB::table('skills')
            ->whereIn('name', $expectedSkills)
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing($expectedSkills, $actualSkills);

        $flutterAliases = DB::table('skill_aliases')
            ->join('skills', 'skills.id', '=', 'skill_aliases.SkillID')
            ->where('skills.name', 'Flutter')
            ->pluck('skill_aliases.Alias')
            ->all();

        $this->assertContains('Flutter', $flutterAliases);
        $this->assertContains('فلاتر', $flutterAliases);

        $stateManagementAliases = DB::table('skill_aliases')
            ->join('skills', 'skills.id', '=', 'skill_aliases.SkillID')
            ->where('skills.name', 'State Management')
            ->pluck('skill_aliases.Alias')
            ->all();

        $this->assertContains('State Management', $stateManagementAliases);
        $this->assertContains('GetX', $stateManagementAliases);
        $this->assertContains('إدارة الحالة', $stateManagementAliases);

        $restApiAliases = DB::table('skill_aliases')
            ->join('skills', 'skills.id', '=', 'skill_aliases.SkillID')
            ->where('skills.name', 'REST API')
            ->pluck('skill_aliases.Alias')
            ->all();

        $this->assertContains('REST API', $restApiAliases);
        $this->assertContains('REST APIs', $restApiAliases);
        $this->assertContains('واجهات برمجية', $restApiAliases);

        $this->assertNotContains('APIs', $restApiAliases);
    }

    public function test_mobile_demo_dataset_file_is_valid(): void
    {
        $path = database_path('seeders/data/mobile_test_jobs.json');

        $this->assertFileExists($path);

        $records = json_decode(
            file_get_contents($path),
            true
        );

        $this->assertIsArray($records);
        $this->assertCount(10, $records);

        foreach ($records as $record) {
            $this->assertArrayHasKey('id', $record);
            $this->assertArrayHasKey('title', $record);
            $this->assertArrayHasKey('description', $record);
            $this->assertArrayHasKey('company_name', $record);
            $this->assertArrayHasKey('language', $record);
            $this->assertArrayHasKey('published_at', $record);

            $this->assertNotEmpty($record['id']);
            $this->assertNotEmpty($record['title']);
            $this->assertNotEmpty($record['description']);
        }
    }
}
