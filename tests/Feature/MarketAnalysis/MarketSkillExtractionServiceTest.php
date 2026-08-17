<?php

namespace Tests\Feature\MarketAnalysis;

use App\Models\MarketJobPosting;
use App\Services\MarketAnalysis\MarketSkillExtractionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketSkillExtractionServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_extracts_arabic_alias_with_attached_prefix(): void
    {
        $careerPathId = DB::table('career_paths')->insertGetId([
            'Name' => 'Test Backend Path',
            'Description' => 'Temporary test path.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $skillId = DB::table('skills')->insertGetId([
            'name' => 'Teamwork Test',
            'category' => 'Soft Skill',
            'normalized_name' => 'teamwork_test_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('skill_aliases')->insert([
            'SkillID' => $skillId,
            'Alias' => 'العمل ضمن فريق اختبار',
            'LanguageCode' => 'ar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $posting = MarketJobPosting::create([
            'source_type' => 'test',
            'source_name' => 'phpunit',
            'external_id' => 'arabic-prefix-'.uniqid(),
            'title' => 'مطلوب مطور Backend',
            'description' => 'نبحث عن مطور لديه خبرة جيدة والعمل ضمن فريق اختبار.',
            'company_name' => 'Test Company',
            'location' => 'Remote',
            'language' => 'ar',
            'career_path_id' => $careerPathId,
            'published_at' => now(),
            'imported_at' => now(),
            'status' => 'active',
            'content_hash' => hash('sha256', 'arabic-prefix-'.uniqid()),
        ]);

        app(MarketSkillExtractionService::class)->extractForJobPosting($posting);

        $this->assertDatabaseHas('market_job_posting_skill_occurrences', [
            'market_job_posting_id' => $posting->id,
            'skill_id' => $skillId,
            'matched_text' => 'العمل ضمن فريق اختبار',
            'language' => 'ar',
            'extraction_method' => 'alias_match',
        ]);
    }

    public function test_it_counts_each_skill_once_per_job_posting(): void
    {
        $careerPathId = DB::table('career_paths')->insertGetId([
            'Name' => 'Test Duplicate Path',
            'Description' => 'Temporary test path.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $skillId = DB::table('skills')->insertGetId([
            'name' => 'Laravel Duplicate Test',
            'category' => 'Framework',
            'normalized_name' => 'laravel_duplicate_test_'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('skill_aliases')->insert([
            'SkillID' => $skillId,
            'Alias' => 'LaravelDuplicateTest',
            'LanguageCode' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $posting = MarketJobPosting::create([
            'source_type' => 'test',
            'source_name' => 'phpunit',
            'external_id' => 'duplicate-skill-'.uniqid(),
            'title' => 'LaravelDuplicateTest Developer',
            'description' => 'LaravelDuplicateTest LaravelDuplicateTest LaravelDuplicateTest experience required.',
            'company_name' => 'Test Company',
            'location' => 'Remote',
            'language' => 'en',
            'career_path_id' => $careerPathId,
            'published_at' => now(),
            'imported_at' => now(),
            'status' => 'active',
            'content_hash' => hash('sha256', 'duplicate-skill-'.uniqid()),
        ]);

        app(MarketSkillExtractionService::class)->extractForJobPosting($posting);

        $count = DB::table('market_job_posting_skill_occurrences')
            ->where('market_job_posting_id', $posting->id)
            ->where('skill_id', $skillId)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_it_extracts_technical_skills_with_symbols(): void
    {
        $technicalSkills = [
            [
                'name' => 'C++ Regression Test',
                'normalized_name' => 'cpp_regression_'.uniqid(),
                'alias' => 'C++ Regression Skill',
            ],
            [
                'name' => 'C# Regression Test',
                'normalized_name' => 'csharp_regression_'.uniqid(),
                'alias' => 'C# Regression Skill',
            ],
            [
                'name' => '.NET Regression Test',
                'normalized_name' => 'dotnet_regression_'.uniqid(),
                'alias' => '.NET Regression Skill',
            ],
            [
                'name' => 'ASP.NET Regression Test',
                'normalized_name' => 'aspnet_regression_'.uniqid(),
                'alias' => 'ASP.NET Regression Skill',
            ],
        ];

        $skillIds = [];

        foreach ($technicalSkills as $technicalSkill) {
            $skillId = DB::table('skills')->insertGetId([
                'name' => $technicalSkill['name'],
                'category' => 'Technical Skill',
                'normalized_name' => $technicalSkill['normalized_name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('skill_aliases')->insert([
                'SkillID' => $skillId,
                'Alias' => $technicalSkill['alias'],
                'LanguageCode' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $skillIds[] = $skillId;
        }

        $externalId = 'technical-symbols-'.uniqid();

        $posting = MarketJobPosting::create([
            'source_type' => 'test',
            'source_name' => 'phpunit',
            'external_id' => $externalId,
            'title' => 'Backend Software Engineer',
            'description' => 'Required technologies: C++ Regression Skill, '
                .'C# Regression Skill, .NET Regression Skill, '
                .'and ASP.NET Regression Skill.',
            'company_name' => 'Test Company',
            'location' => 'Remote',
            'language' => 'en',
            'career_path_id' => null,
            'published_at' => now(),
            'imported_at' => now(),
            'status' => 'active',
            'content_hash' => hash('sha256', $externalId),
        ]);

        app(MarketSkillExtractionService::class)
            ->extractForJobPosting($posting);

        $extractedSkillIds = DB::table(
            'market_job_posting_skill_occurrences'
        )
            ->where('market_job_posting_id', $posting->id)
            ->pluck('skill_id')
            ->map(fn ($skillId): int => (int) $skillId)
            ->all();

        sort($skillIds);
        sort($extractedSkillIds);

        $this->assertSame($skillIds, $extractedSkillIds);
        $this->assertCount(4, $extractedSkillIds);
    }
}
