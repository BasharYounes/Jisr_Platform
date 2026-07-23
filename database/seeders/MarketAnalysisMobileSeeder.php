<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketAnalysisMobileSeeder extends Seeder
{
    public function run(): void
    {
        $careerPathId = $this->upsertCareerPath();

        $skills = [
            [
                'name' => 'Flutter',
                'category' => 'Mobile Framework',
                'aliases' => [
                    ['alias' => 'Flutter', 'language' => 'en'],
                    ['alias' => 'فلاتر', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Dart',
                'category' => 'Programming Language',
                'aliases' => [
                    ['alias' => 'Dart', 'language' => 'en'],
                    ['alias' => 'دارت', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Firebase',
                'category' => 'Backend Service',
                'aliases' => [
                    ['alias' => 'Firebase', 'language' => 'en'],
                    ['alias' => 'فايربيس', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'State Management',
                'category' => 'Mobile Concept',
                'aliases' => [
                    ['alias' => 'State Management', 'language' => 'en'],
                    ['alias' => 'Provider', 'language' => 'en'],
                    ['alias' => 'Bloc', 'language' => 'en'],
                    ['alias' => 'BLoC', 'language' => 'en'],
                    ['alias' => 'GetX', 'language' => 'en'],
                    ['alias' => 'إدارة الحالة', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Android',
                'category' => 'Mobile Platform',
                'aliases' => [
                    ['alias' => 'Android', 'language' => 'en'],
                    ['alias' => 'أندرويد', 'language' => 'ar'],
                    ['alias' => 'اندرويد', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'iOS',
                'category' => 'Mobile Platform',
                'aliases' => [
                    ['alias' => 'iOS', 'language' => 'en'],
                    ['alias' => 'IOS', 'language' => 'en'],
                    ['alias' => 'آي أو إس', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'REST API',
                'category' => 'Concept',
                'aliases' => [
                    ['alias' => 'REST API', 'language' => 'en'],
                    ['alias' => 'REST APIs', 'language' => 'en'],
                    ['alias' => 'واجهات برمجية', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Git',
                'category' => 'Version Control',
                'aliases' => [
                    ['alias' => 'Git', 'language' => 'en'],
                    ['alias' => 'جيت', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Testing',
                'category' => 'Engineering Practice',
                'aliases' => [
                    ['alias' => 'Testing', 'language' => 'en'],
                    ['alias' => 'Unit Testing', 'language' => 'en'],
                    ['alias' => 'Widget Testing', 'language' => 'en'],
                    ['alias' => 'اختبار البرمجيات', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Teamwork',
                'category' => 'Soft Skill',
                'aliases' => [
                    ['alias' => 'Teamwork', 'language' => 'en'],
                    ['alias' => 'Team Work', 'language' => 'en'],
                    ['alias' => 'العمل ضمن فريق', 'language' => 'ar'],
                    ['alias' => 'العمل الجماعي', 'language' => 'ar'],
                ],
            ],
        ];

        foreach ($skills as $skillData) {
            $skillId = $this->upsertSkill(
                name: $skillData['name'],
                category: $skillData['category']
            );

            foreach ($skillData['aliases'] as $aliasData) {
                $this->upsertAlias(
                    skillId: $skillId,
                    alias: $aliasData['alias'],
                    language: $aliasData['language']
                );
            }
        }

        $this->command?->info("Mobile market analysis dictionary seeded successfully. CareerPathID: {$careerPathId}");
    }

    private function upsertCareerPath(): int
    {
        $existingCareerPath = DB::table('career_paths')
            ->where('Name', 'Mobile Developer')
            ->first();

        if ($existingCareerPath) {
            DB::table('career_paths')
                ->where('CareerPathID', $existingCareerPath->CareerPathID)
                ->update([
                    'Description' => 'Mobile development track focused on Flutter, mobile platforms, APIs, Firebase, and application state management.',
                    'updated_at' => now(),
                ]);

            return (int) $existingCareerPath->CareerPathID;
        }

        return (int) DB::table('career_paths')->insertGetId([
            'Name' => 'Mobile Developer',
            'Description' => 'Mobile development track focused on Flutter, mobile platforms, APIs, Firebase, and application state management.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertSkill(string $name, string $category): int
    {
        $normalizedName = $this->normalizeSkillName($name);

        $existingSkill = DB::table('skills')
            ->where('normalized_name', $normalizedName)
            ->orWhere('name', $name)
            ->first();

        if ($existingSkill) {
            DB::table('skills')
                ->where('id', $existingSkill->id)
                ->update([
                    'name' => $name,
                    'category' => $category,
                    'normalized_name' => $normalizedName,
                    'updated_at' => now(),
                ]);

            return (int) $existingSkill->id;
        }

        return (int) DB::table('skills')->insertGetId([
            'name' => $name,
            'category' => $category,
            'normalized_name' => $normalizedName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertAlias(int $skillId, string $alias, string $language): void
    {
        $existingAlias = DB::table('skill_aliases')
            ->where('Alias', $alias)
            ->first();

        if ($existingAlias) {
            DB::table('skill_aliases')
                ->where('SkillAliasID', $existingAlias->SkillAliasID)
                ->update([
                    'SkillID' => $skillId,
                    'LanguageCode' => $language,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('skill_aliases')->insert([
            'SkillID' => $skillId,
            'Alias' => $alias,
            'LanguageCode' => $language,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeSkillName(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replace(['.', '/', '\\', '+', '#'], ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->replace(' ', '_')
            ->toString();
    }
}
