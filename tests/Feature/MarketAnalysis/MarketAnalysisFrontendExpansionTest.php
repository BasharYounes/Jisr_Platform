<?php

namespace Tests\Feature\MarketAnalysis;

use Database\Seeders\MarketAnalysisFrontendSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketAnalysisFrontendExpansionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_frontend_market_dictionary_is_seeded(): void
    {
        $this->seed(MarketAnalysisFrontendSeeder::class);

        $careerPath = DB::table('career_paths')
            ->where('Name', 'Frontend Developer')
            ->first();

        $this->assertNotNull($careerPath);

        $expectedSkills = [
            'HTML',
            'CSS',
            'JavaScript',
            'TypeScript',
            'React',
            'Vue',
            'Tailwind CSS',
            'Responsive Design',
            'Figma',
        ];

        $actualSkills = DB::table('skills')
            ->whereIn('name', $expectedSkills)
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing($expectedSkills, $actualSkills);

        $reactAliases = DB::table('skill_aliases')
            ->join('skills', 'skills.id', '=', 'skill_aliases.SkillID')
            ->where('skills.name', 'React')
            ->pluck('skill_aliases.Alias')
            ->all();

        $this->assertContains('ReactJS', $reactAliases);
        $this->assertContains('رياكت', $reactAliases);

        $responsiveAliases = DB::table('skill_aliases')
            ->join('skills', 'skills.id', '=', 'skill_aliases.SkillID')
            ->where('skills.name', 'Responsive Design')
            ->pluck('skill_aliases.Alias')
            ->all();

        $this->assertContains('Responsive Design', $responsiveAliases);
        $this->assertContains('تصميم متجاوب', $responsiveAliases);
    }

    public function test_frontend_demo_dataset_file_is_valid(): void
    {
        $path = database_path('seeders/data/frontend_test_jobs.json');

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
