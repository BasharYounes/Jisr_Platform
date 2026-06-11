<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PortfolioProjectsSeeder extends Seeder
{
    public function run(): void
    {
        $student = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'student')
            ->select('users.id')
            ->orderBy('users.id')
           // ->skip(3)
            ->first();

        if (! $student) {
            throw new RuntimeException('No student user found. Please create a student first.');
        }

        $projects = [
            [
                'title' => 'Flask REST API Project',
                'description' => 'Built a clean REST API using Flask, SQL, and Git with structured endpoints.',
                'project_url' => 'https://github.com/demo/flask-rest-api',
                'grade' => 88.50,
            ],
            [
                'title' => 'SQL Database Design',
                'description' => 'Designed relational database schema with tables, relationships, and queries.',
                'project_url' => 'https://github.com/demo/sql-database-design',
                'grade' => 91.00,
            ],
        ];

        foreach ($projects as $project) {
            DB::table('portfolio_projects')->updateOrInsert(
                [
                    'user_id' => $student->id,
                    'title' => $project['title'],
                ],
                [
                    'portfolioable_type' => null,
                    'portfolioable_id' => null,
                    'source' => 'manual',
                    'description' => $project['description'],
                    'project_url' => $project['project_url'],
                    'completion_date' => now()->subDays(7),
                    'grade' => $project['grade'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Demo portfolio projects created for the first student successfully.');
    }
}
