<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketAnalysisSkillDictionarySeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            [
                'name' => 'PHP',
                'category' => 'Programming Language',
                'aliases' => [
                    ['alias' => 'PHP', 'language' => 'en'],
                    ['alias' => 'لغة PHP', 'language' => 'ar'],
                    ['alias' => 'بي اتش بي', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Laravel',
                'category' => 'Framework',
                'aliases' => [
                    ['alias' => 'Laravel', 'language' => 'en'],
                    ['alias' => 'Laravel Framework', 'language' => 'en'],
                    ['alias' => 'PHP Laravel', 'language' => 'en'],
                    ['alias' => 'لارافيل', 'language' => 'ar'],
                    ['alias' => 'إطار لارافيل', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'MySQL',
                'category' => 'Database',
                'aliases' => [
                    ['alias' => 'MySQL', 'language' => 'en'],
                    ['alias' => 'mysql', 'language' => 'en'],
                    ['alias' => 'My SQL', 'language' => 'en'],
                    ['alias' => 'قاعدة بيانات MySQL', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'PostgreSQL',
                'category' => 'Database',
                'aliases' => [
                    ['alias' => 'PostgreSQL', 'language' => 'en'],
                    ['alias' => 'Postgres', 'language' => 'en'],
                    ['alias' => 'قاعدة بيانات PostgreSQL', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'REST API',
                'category' => 'Concept',
                'aliases' => [
                    ['alias' => 'REST API', 'language' => 'en'],
                    ['alias' => 'REST APIs', 'language' => 'en'],
                    ['alias' => 'RESTful API', 'language' => 'en'],
                    ['alias' => 'API Development', 'language' => 'en'],
                    ['alias' => 'واجهات برمجية', 'language' => 'ar'],
                    ['alias' => 'واجهات API', 'language' => 'ar'],
                    ['alias' => 'بناء API', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Git',
                'category' => 'Version Control',
                'aliases' => [
                    ['alias' => 'Git', 'language' => 'en'],
                    ['alias' => 'GitHub', 'language' => 'en'],
                    ['alias' => 'Version Control', 'language' => 'en'],
                    ['alias' => 'التحكم بالإصدارات', 'language' => 'ar'],
                    ['alias' => 'استخدام Git', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Docker',
                'category' => 'DevOps Tool',
                'aliases' => [
                    ['alias' => 'Docker', 'language' => 'en'],
                    ['alias' => 'Docker Compose', 'language' => 'en'],
                    ['alias' => 'Containers', 'language' => 'en'],
                    ['alias' => 'حاويات Docker', 'language' => 'ar'],
                    ['alias' => 'دوكر', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Testing',
                'category' => 'Engineering Practice',
                'aliases' => [
                    ['alias' => 'Testing', 'language' => 'en'],
                    ['alias' => 'Unit Testing', 'language' => 'en'],
                    ['alias' => 'Feature Testing', 'language' => 'en'],
                    ['alias' => 'Automated Testing', 'language' => 'en'],
                    ['alias' => 'اختبارات', 'language' => 'ar'],
                    ['alias' => 'اختبار الوحدات', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Authentication',
                'category' => 'Concept',
                'aliases' => [
                    ['alias' => 'Authentication', 'language' => 'en'],
                    ['alias' => 'Authorization', 'language' => 'en'],
                    ['alias' => 'Login System', 'language' => 'en'],
                    ['alias' => 'نظام تسجيل الدخول', 'language' => 'ar'],
                    ['alias' => 'المصادقة', 'language' => 'ar'],
                    ['alias' => 'الصلاحيات', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Teamwork',
                'category' => 'Soft Skill',
                'aliases' => [
                    ['alias' => 'Teamwork', 'language' => 'en'],
                    ['alias' => 'Team player', 'language' => 'en'],
                    ['alias' => 'Collaboration', 'language' => 'en'],
                    ['alias' => 'العمل ضمن فريق', 'language' => 'ar'],
                    ['alias' => 'التعاون', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Python',
                'category' => 'Programming Language',
                'aliases' => [
                    ['alias' => 'Python', 'language' => 'en'],
                    ['alias' => 'python', 'language' => 'en'],
                    ['alias' => 'بايثون', 'language' => 'ar'],
                    ['alias' => 'لغة بايثون', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Flask',
                'category' => 'Framework',
                'aliases' => [
                    ['alias' => 'Flask', 'language' => 'en'],
                    ['alias' => 'Python Flask', 'language' => 'en'],
                    ['alias' => 'فلاسك', 'language' => 'ar'],
                    ['alias' => 'إطار فلاسك', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'SQL',
                'category' => 'Database',
                'aliases' => [
                    ['alias' => 'SQL', 'language' => 'en'],
                    ['alias' => 'SQL Queries', 'language' => 'en'],
                    ['alias' => 'Database Queries', 'language' => 'en'],
                    ['alias' => 'استعلامات SQL', 'language' => 'ar'],
                    ['alias' => 'قواعد البيانات', 'language' => 'ar'],
                ],
            ],
            [
                'name' => 'Communication',
                'category' => 'Soft Skill',
                'aliases' => [
                    ['alias' => 'Communication', 'language' => 'en'],
                    ['alias' => 'Communication Skills', 'language' => 'en'],
                    ['alias' => 'مهارات التواصل', 'language' => 'ar'],
                    ['alias' => 'التواصل', 'language' => 'ar'],
                ],
            ],
        ];

        foreach ($skills as $skillData) {
            $skill = Skill::query()->firstOrCreate(
                ['normalized_name' => Str::slug($skillData['name'], '_')],
                [
                    'name' => $skillData['name'],
                    'category' => $skillData['category'],
                ]
            );

            foreach ($skillData['aliases'] as $aliasData) {
                $existingAlias = DB::table('skill_aliases')
                    ->where('Alias', $aliasData['alias'])
                    ->first();

                if ($existingAlias) {
                    DB::table('skill_aliases')
                        ->where('SkillAliasID', $existingAlias->SkillAliasID)
                        ->update([
                            'SkillID' => $skill->id,
                            'LanguageCode' => $aliasData['language'],
                            'updated_at' => now(),
                        ]);

                    continue;
                }

                DB::table('skill_aliases')->insert([
                    'SkillID' => $skill->id,
                    'Alias' => $aliasData['alias'],
                    'LanguageCode' => $aliasData['language'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
