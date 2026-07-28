<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketAnalysisFrontendSeeder extends Seeder
{
    public function run(): void
    {
        $careerPathId = $this->upsertCareerPath();

        $skills = [
            [
                'name' => 'HTML',
                'category' => 'Markup Language',
                'aliases' => [
                    ['alias' => 'HTML', 'language' => 'en'],
                    ['alias' => 'HTML5', 'language' => 'en'],
                    ['alias' => 'اتش تي ام ال', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'CSS',
                'category' => 'Styling Language',
                'aliases' => [
                    ['alias' => 'CSS', 'language' => 'en'],
                    ['alias' => 'CSS3', 'language' => 'en'],
                    ['alias' => 'سي اس اس', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'JavaScript',
                'category' => 'Programming Language',
                'aliases' => [
                    ['alias' => 'JavaScript', 'language' => 'en'],
                    ['alias' => 'Javascript', 'language' => 'en'],
                    ['alias' => 'JS', 'language' => 'en'],
                    ['alias' => 'جافاسكريبت', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'TypeScript',
                'category' => 'Programming Language',
                'aliases' => [
                    ['alias' => 'TypeScript', 'language' => 'en'],
                    ['alias' => 'Typescript', 'language' => 'en'],
                    ['alias' => 'TS', 'language' => 'en'],
                    ['alias' => 'تايب سكريبت', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'React',
                'category' => 'Frontend Framework',
                'aliases' => [
                    ['alias' => 'React', 'language' => 'en'],
                    ['alias' => 'React.js', 'language' => 'en'],
                    ['alias' => 'ReactJS', 'language' => 'en'],
                    ['alias' => 'رياكت', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Vue',
                'category' => 'Frontend Framework',
                'aliases' => [
                    ['alias' => 'Vue', 'language' => 'en'],
                    ['alias' => 'Vue.js', 'language' => 'en'],
                    ['alias' => 'VueJS', 'language' => 'en'],
                    ['alias' => 'فيو', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Tailwind CSS',
                'category' => 'CSS Framework',
                'aliases' => [
                    ['alias' => 'Tailwind', 'language' => 'en'],
                    ['alias' => 'Tailwind CSS', 'language' => 'en'],
                    ['alias' => 'تيلويند', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Responsive Design',
                'category' => 'Frontend Concept',
                'aliases' => [
                    ['alias' => 'Responsive Design', 'language' => 'en'],
                    ['alias' => 'Responsive UI', 'language' => 'en'],
                    ['alias' => 'Mobile First', 'language' => 'en'],
                    ['alias' => 'تصميم متجاوب', 'language' => 'ar'],
                    ['alias' => 'واجهات متجاوبة', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Figma',
                'category' => 'Design Tool',
                'aliases' => [
                    ['alias' => 'Figma', 'language' => 'en'],
                    ['alias' => 'فيغما', 'language' => 'ar'],
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
                    ['alias' => 'اختبار البرمجيات', 'language' => 'ar'],
                    ['alias' => 'اختبارات', 'language' => 'ar'],
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

        $this->command?->info("Frontend market analysis dictionary seeded successfully. CareerPathID: {$careerPathId}");
    }

    private function upsertCareerPath(): int
    {
        $existingCareerPath = DB::table('career_paths')
            ->where('Name', 'Frontend Developer')
            ->first();

        if ($existingCareerPath) {
            DB::table('career_paths')
                ->where('CareerPathID', $existingCareerPath->CareerPathID)
                ->update([
                    'Description' => 'Frontend development track focused on user interfaces, web technologies, responsive design, and client-side application development.',
                    'updated_at' => now(),
                ]);

            return (int) $existingCareerPath->CareerPathID;
        }

        return (int) DB::table('career_paths')->insertGetId([
            'Name' => 'Frontend Developer',
            'Description' => 'Frontend development track focused on user interfaces, web technologies, responsive design, and client-side application development.',
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
