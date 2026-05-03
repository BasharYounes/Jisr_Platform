<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SkillLevelDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'Python' => [
                1 => [
                    'title' => 'Beginner Basics',
                    'description' => 'Understands variables, conditions, loops, and basic functions.',
                    'indicators' => ['if statements', 'loops', 'basic functions', 'simple scripts'],
                ],
                2 => [
                    'title' => 'Practical Foundations',
                    'description' => 'Uses lists, dictionaries, files, modules, and writes small practical scripts.',
                    'indicators' => ['list/dict usage', 'file handling basics', 'imports', 'small utility scripts'],
                ],
                3 => [
                    'title' => 'Intermediate Development',
                    'description' => 'Understands exceptions, OOP basics, testing basics, and writes structured code.',
                    'indicators' => ['exception handling', 'classes', 'basic testing', 'modular code'],
                ],
                4 => [
                    'title' => 'Advanced Python',
                    'description' => 'Uses decorators, generators, profiling, and writes more optimized code.',
                    'indicators' => ['decorators', 'generators', 'performance awareness', 'clean abstractions'],
                ],
                5 => [
                    'title' => 'Expert Level',
                    'description' => 'Designs robust systems, contributes to advanced libraries, and handles advanced async patterns.',
                    'indicators' => ['system design', 'asyncio', 'advanced architecture', 'library design'],
                ],
            ],
            'Flask' => [
                1 => [
                    'title' => 'Basic Routing',
                    'description' => 'Can build a minimal Flask app with simple routes.',
                    'indicators' => ['app creation', 'route decorator', 'basic response'],
                ],
                2 => [
                    'title' => 'Simple API Development',
                    'description' => 'Builds simple GET/POST endpoints and handles request data.',
                    'indicators' => ['GET/POST', 'request parsing', 'JSON response'],
                ],
                3 => [
                    'title' => 'Structured Flask Development',
                    'description' => 'Uses blueprints, validation basics, and simple error handling.',
                    'indicators' => ['blueprints', 'validation basics', 'error handling'],
                ],
                4 => [
                    'title' => 'Production-minded Flask',
                    'description' => 'Handles authentication patterns, configuration separation, and app structure.',
                    'indicators' => ['auth basics', 'config separation', 'project structure'],
                ],
                5 => [
                    'title' => 'Advanced Flask Engineering',
                    'description' => 'Designs scalable Flask services with testing, deployment awareness, and architecture decisions.',
                    'indicators' => ['testing', 'scalability', 'service design', 'deployment readiness'],
                ],
            ],
            'SQL' => [
                1 => [
                    'title' => 'Basic Queries',
                    'description' => 'Can write simple SELECT statements and basic filtering.',
                    'indicators' => ['SELECT', 'WHERE', 'basic sorting'],
                ],
                2 => [
                    'title' => 'Intermediate Querying',
                    'description' => 'Uses JOIN, GROUP BY, and aggregate functions.',
                    'indicators' => ['JOIN', 'GROUP BY', 'COUNT/SUM', 'HAVING basics'],
                ],
                3 => [
                    'title' => 'Practical Data Handling',
                    'description' => 'Writes subqueries, handles more complex filters, and understands normalization basics.',
                    'indicators' => ['subqueries', 'normalization basics', 'multi-condition querying'],
                ],
                4 => [
                    'title' => 'Optimization Awareness',
                    'description' => 'Understands indexes, query optimization basics, and performance tradeoffs.',
                    'indicators' => ['indexes', 'query plans basics', 'performance awareness'],
                ],
                5 => [
                    'title' => 'Advanced Database Thinking',
                    'description' => 'Designs efficient schemas and optimizes complex data access patterns.',
                    'indicators' => ['schema design', 'optimization', 'advanced relational modeling'],
                ],
            ],
            'Git' => [
                1 => [
                    'title' => 'Basic Version Control',
                    'description' => 'Can init repositories, commit changes, and push code.',
                    'indicators' => ['git init', 'commit', 'push'],
                ],
                2 => [
                    'title' => 'Daily Collaboration Basics',
                    'description' => 'Uses branches, pull, merge, and resolves simple conflicts.',
                    'indicators' => ['branching', 'pull', 'merge', 'simple conflict resolution'],
                ],
                3 => [
                    'title' => 'Team Workflow Usage',
                    'description' => 'Understands pull requests, rebasing basics, and cleaner commit history.',
                    'indicators' => ['pull request workflow', 'rebase basics', 'history awareness'],
                ],
                4 => [
                    'title' => 'Advanced Collaboration',
                    'description' => 'Handles complex conflict resolution and structured team workflows.',
                    'indicators' => ['advanced conflicts', 'workflow strategy', 'release branching'],
                ],
                5 => [
                    'title' => 'Expert Git Usage',
                    'description' => 'Can guide repository strategy and recover from complex history issues safely.',
                    'indicators' => ['repository strategy', 'history recovery', 'advanced git internals'],
                ],
            ],
        ];

        foreach ($definitions as $skillName => $levels) {
            $skillId = DB::table('skills')->where('name', $skillName)->value('id');

            if (!$skillId) {
                continue;
            }

            foreach ($levels as $level => $definition) {
                DB::table('skill_level_definitions')->updateOrInsert(
                    [
                        'SkillID' => $skillId,
                        'Level' => $level,
                    ],
                    [
                        'Title' => $definition['title'],
                        'Description' => $definition['description'],
                        'BehavioralIndicatorsJson' => json_encode($definition['indicators']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
