<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
          $tags = [
            // Programming Languages
            ['name' => 'PHP', 'type' => 'skill'],
            ['name' => 'JavaScript', 'type' => 'skill'],
            ['name' => 'TypeScript', 'type' => 'skill'],
            ['name' => 'Python', 'type' => 'skill'],
            ['name' => 'Java', 'type' => 'skill'],
            ['name' => 'C#', 'type' => 'skill'],

            // Backend
            ['name' => 'Laravel', 'type' => 'skill'],
            ['name' => 'REST API', 'type' => 'skill'],
            ['name' => 'Authentication', 'type' => 'skill'],
            ['name' => 'Authorization', 'type' => 'skill'],
            ['name' => 'API Documentation', 'type' => 'skill'],
            ['name' => 'Clean Code', 'type' => 'skill'],
            ['name' => 'Design Patterns', 'type' => 'skill'],

            // Frontend
            ['name' => 'HTML', 'type' => 'skill'],
            ['name' => 'CSS', 'type' => 'skill'],
            ['name' => 'React', 'type' => 'skill'],
            ['name' => 'Vue.js', 'type' => 'skill'],
            ['name' => 'Tailwind CSS', 'type' => 'tool'],

            // Database
            ['name' => 'MySQL', 'type' => 'tool'],
            ['name' => 'PostgreSQL', 'type' => 'tool'],
            ['name' => 'Database Design', 'type' => 'skill'],
            ['name' => 'Eloquent ORM', 'type' => 'skill'],

            // DevOps / Tools
            ['name' => 'Git', 'type' => 'tool'],
            ['name' => 'GitHub', 'type' => 'tool'],
            ['name' => 'Docker', 'type' => 'tool'],
            ['name' => 'Postman', 'type' => 'tool'],
            ['name' => 'Swagger', 'type' => 'tool'],

            // AI / Data
            ['name' => 'AI Integration', 'type' => 'skill'],
            ['name' => 'Prompt Engineering', 'type' => 'skill'],
            ['name' => 'Data Analysis', 'type' => 'skill'],

            // Soft Skills
            ['name' => 'Communication', 'type' => 'soft-skill'],
            ['name' => 'Teamwork', 'type' => 'soft-skill'],
            ['name' => 'Problem Solving', 'type' => 'soft-skill'],
            ['name' => 'Time Management', 'type' => 'soft-skill'],
            ['name' => 'Critical Thinking', 'type' => 'soft-skill'],

            // Roles
            ['name' => 'Backend Developer', 'type' => 'role'],
            ['name' => 'Frontend Developer', 'type' => 'role'],
            ['name' => 'Full Stack Developer', 'type' => 'role'],
            ['name' => 'Mobile Developer', 'type' => 'role'],
            ['name' => 'AI Engineer', 'type' => 'role'],
            ['name' => 'Data Analyst', 'type' => 'role'],
            ['name' => 'QA Engineer', 'type' => 'role'],
        ];

        foreach ($tags as $tag) {
            DB::table('tags')->updateOrInsert(
                [
                    'name' => $tag['name'],
                    'type' => $tag['type'],
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
