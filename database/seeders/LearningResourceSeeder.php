<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LearningResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('learning_resources')->insert([
            [
                'SkillID' => 1, // Python
                'Title' => 'Python Basics for Beginners',
                'Url' => 'https://youtube.com/...',
                'Type' => 'video',
                'Level' => 1,
                'EstimatedHours' => 2,
                'Provider' => 'YouTube',
            ],
            [
                'SkillID' => 1,
                'Title' => 'Intermediate Python Projects',
                'Url' => 'https://coursera.org/...',
                'Type' => 'course',
                'Level' => 3,
                'EstimatedHours' => 10,
                'Provider' => 'Coursera',
            ],
            // Flask
            [
                'SkillID' => 2,
                'Title' => 'Flask Web Development',
                'Url' => 'https://youtube.com/...',
                'Type' => 'video',
                'Level' => 2,
                'EstimatedHours' => 3,
                'Provider' => 'YouTube',
            ],
            [
                'SkillID' => 2,
                'Title' => 'Building Web Apps with Flask',
                'Url' => 'https://coursera.org/...',
                'Type' => 'course',
                'Level' => 4,
                'EstimatedHours' => 12,
                'Provider' => 'Coursera',
            ],
            // SQL
            [
                'SkillID' => 3,
                'Title' => 'SQL for Data Analysis',
                'Url' => 'https://youtube.com/...',
                'Type' => 'video',
                'Level' => 1,
                'EstimatedHours' => 2,
                'Provider' => 'YouTube',
            ],
            [
                'SkillID' => 3,
                'Title' => 'Advanced SQL Queries',
                'Url' => 'https://coursera.org/...',
                'Type' => 'course',
                'Level' => 4,
                'EstimatedHours' => 8,
                'Provider' => 'Coursera',
            ],
            // Git
            [
                'SkillID' => 4,
                'Title' => 'Git and GitHub for Beginners',
                'Url' => 'https://youtube.com/...',
                'Type' => 'video',
                'Level' => 1,
                'EstimatedHours' => 1.5,
                'Provider' => 'YouTube',
            ],
            [
                'SkillID' => 4,
                'Title' => 'Mastering Git and GitHub',
                'Url' => 'https://coursera.org/...',
                'Type' => 'course',
                'Level' => 3,
                'EstimatedHours' => 6,
                'Provider' => 'Coursera',
            ],
        ]);
    }
}
