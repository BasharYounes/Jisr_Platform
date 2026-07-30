<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyTask;
use App\Models\CompanyTaskApplication;
use App\Models\CompanyTaskAssignment;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoCompanyTaskWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $company = Company::query()->first();

            if (! $company) {
                throw new \RuntimeException('No company found. Please seed a company first.');
            }

            $companyUserId = $this->resolveCompanyUserId($company);
            $student = $this->resolveStudent();

            $skillsByName = Skill::query()
                ->whereIn('name', [
                    'Laravel',
                    'REST API',
                    'MySQL',
                    'Git',
                    'PHP',
                    'Postman',
                    'Authentication',
                    'Database Design',
                    'API Testing',
                ])
                ->get()
                ->keyBy('name');

            if ($skillsByName->isEmpty()) {
                throw new \RuntimeException('No matching skills found. Please run FullSkillsSeeder first.');
            }

            $tasks = [
                [
                    'title' => 'Build Laravel Tasks API',
                    'description' => 'Create a clean Laravel REST API for managing tasks, status updates, and basic validation.',
                    'difficulty_level' => 'intermediate',
                    'duration_days' => 5,
                    'submission_type' => 'github_link',
                    'deliverables' => [
                        'GitHub repository',
                        'README file',
                        'Postman collection',
                    ],
                    'acceptance_criteria' => [
                        'Use Laravel Form Requests for validation.',
                        'Return consistent JSON responses.',
                        'Keep controller logic clean.',
                    ],
                    'skills' => [
                        'Laravel' => ['required_level' => 3, 'weight' => 2.00, 'mandatory' => true],
                        'REST API' => ['required_level' => 3, 'weight' => 2.00, 'mandatory' => true],
                        'MySQL' => ['required_level' => 2, 'weight' => 1.50, 'mandatory' => true],
                        'Git' => ['required_level' => 2, 'weight' => 1.00, 'mandatory' => false],
                    ],
                    'application_message' => 'I can build this Laravel API using clean controllers, services, and repositories.',
                    'company_notes' => 'Good Laravel backend profile. Accepted for demo workflow.',
                    'match_score' => 92.50,
                ],
                [
                    'title' => 'Implement Student Progress Updates API',
                    'description' => 'Build endpoints that allow students to add progress updates for active company tasks.',
                    'difficulty_level' => 'beginner',
                    'duration_days' => 4,
                    'submission_type' => 'mixed',
                    'deliverables' => [
                        'API endpoints',
                        'Validation rules',
                        'Sample responses',
                    ],
                    'acceptance_criteria' => [
                        'Student can create progress updates.',
                        'Company can view updates.',
                        'Only assigned student can update progress.',
                    ],
                    'skills' => [
                        'Laravel' => ['required_level' => 2, 'weight' => 2.00, 'mandatory' => true],
                        'PHP' => ['required_level' => 2, 'weight' => 1.50, 'mandatory' => true],
                        'Postman' => ['required_level' => 2, 'weight' => 1.00, 'mandatory' => false],
                    ],
                    'application_message' => 'I am interested in implementing progress updates and testing them with Postman.',
                    'company_notes' => 'Accepted to test active task and progress flow.',
                    'match_score' => 88.00,
                ],
                [
                    'title' => 'Design Secure Task Submission Flow',
                    'description' => 'Implement a secure task submission API where students submit GitHub links or demo links.',
                    'difficulty_level' => 'advanced',
                    'duration_days' => 7,
                    'submission_type' => 'mixed',
                    'deliverables' => [
                        'Submission API',
                        'Authorization checks',
                        'Review-ready response structure',
                    ],
                    'acceptance_criteria' => [
                        'Only assigned students can submit.',
                        'Submission is linked to the correct assignment.',
                        'Company can review submitted work.',
                    ],
                    'skills' => [
                        'Laravel' => ['required_level' => 3, 'weight' => 2.00, 'mandatory' => true],
                        'Authentication' => ['required_level' => 3, 'weight' => 1.50, 'mandatory' => true],
                        'Database Design' => ['required_level' => 2, 'weight' => 1.00, 'mandatory' => false],
                        'API Testing' => ['required_level' => 2, 'weight' => 1.00, 'mandatory' => false],
                    ],
                    'application_message' => 'I want to work on this secure submission flow because I have backend API experience.',
                    'company_notes' => 'Accepted for demo conversation and task submission flow.',
                    'match_score' => 95.00,
                ],
            ];

            foreach ($tasks as $index => $taskData) {
                $task = CompanyTask::query()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'title' => $taskData['title'],
                    ],
                    [
                        'description' => $taskData['description'],
                        'difficulty_level' => $taskData['difficulty_level'],
                        'duration_days' => $taskData['duration_days'],
                        'deadline' => now()->addDays(10 + $index),
                        'max_applicants' => 20,
                        'max_accepted_students' => 1,
                        'deliverables' => $taskData['deliverables'],
                        'acceptance_criteria' => $taskData['acceptance_criteria'],
                        'submission_type' => $taskData['submission_type'],
                        'status' => 'in_progress',
                        'published_at' => now()->subDays(2),
                    ]
                );

                $syncSkills = [];

                foreach ($taskData['skills'] as $skillName => $pivotData) {
                    $skill = $skillsByName->get($skillName);

                    if (! $skill) {
                        continue;
                    }

                    $syncSkills[$skill->id] = [
                        'required_level' => $pivotData['required_level'],
                        'weight' => $pivotData['weight'],
                        'mandatory' => $pivotData['mandatory'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($syncSkills !== []) {
                    $task->skills()->sync($syncSkills);
                }

                $application = CompanyTaskApplication::query()->updateOrCreate(
                    [
                        'company_task_id' => $task->id,
                        'student_user_id' => $student->id,
                    ],
                    [
                        'message' => $taskData['application_message'],
                        'portfolio_url' => 'https://portfolio.example.com/batoul',
                        'github_url' => 'https://github.com/example/jisr-task-demo',
                        'status' => 'accepted',
                        'match_score' => $taskData['match_score'],
                        'match_reasons' => [
                            'Student has strong backend API skills.',
                            'Student skills match the required task skills.',
                            'Student is suitable for this demo task workflow.',
                        ],
                        'applied_at' => now()->subDays(2)->addMinutes($index * 10),
                        'reviewed_at' => now()->subDay(),
                        'company_notes' => $taskData['company_notes'],
                    ]
                );

                $assignment = CompanyTaskAssignment::query()->updateOrCreate(
                    [
                        'company_task_id' => $task->id,
                        'student_user_id' => $student->id,
                    ],
                    [
                        'company_task_application_id' => $application->id,
                        'status' => 'working',
                        'started_at' => now()->subDay(),
                    ]
                );

                $conversation = Conversation::query()->firstOrCreate(
                    [
                        'conversationable_type' => $assignment->getMorphClass(),
                        'conversationable_id' => $assignment->id,
                    ],
                    [
                        'status' => 'open',
                    ]
                );

                ConversationParticipant::query()->updateOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $companyUserId,
                    ],
                    [
                        'role' => 'company',
                        'last_read_at' => now()->subHours(2),
                    ]
                );

                ConversationParticipant::query()->updateOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $student->id,
                    ],
                    [
                        'role' => 'student',
                        'last_read_at' => now()->subHour(),
                    ]
                );
            }
        });
    }

    private function resolveCompanyUserId(Company $company): int
    {
        $companyUser = DB::table('company_users')
            ->where('company_id', $company->id)
            ->first();

        if ($companyUser) {
            return (int) $companyUser->user_id;
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'company.demo@jisr.test'],
            [
                'name' => 'Jisr Demo Company',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified' => true,
                'is_verified_by_admin' => 'accepted',
            ]
        );

        $this->assignRoleIfPossible($user, 'company');

        DB::table('company_users')->updateOrInsert(
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return (int) $user->id;
    }

    private function resolveStudent(): User
    {
        $student = User::query()
            ->whereHas('roles', function ($query): void {
                $query->where('name', 'student');
            })
            ->first();

        if ($student) {
            return $student;
        }

        $student = User::query()->firstOrCreate(
            ['email' => 'student.demo@jisr.test'],
            [
                'name' => 'Demo Student',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified' => true,
                'is_verified_by_admin' => 'accepted',
                'bio' => 'Demo student generated for company task conversations.',
            ]
        );

        $this->assignRoleIfPossible($student, 'student');

        DB::table('student_profiles')->updateOrInsert(
            ['user_id' => $student->id],
            [
                'university' => 'Jisr University',
                'major' => 'Software Engineering',
                'graduation_year' => now()->addYears(1)->year,
                'phone' => '+963900000000',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $student;
    }

    private function assignRoleIfPossible(User $user, string $roleName): void
    {
        if (! method_exists($user, 'assignRole')) {
            return;
        }

        // استخدام 'web' guard لأن User model فيها guard_name = 'web'
        $role = Role::findOrCreate($roleName, 'web');

        if (! $user->hasRole($roleName)) {
            $user->assignRole($role);
        }
    }
}
