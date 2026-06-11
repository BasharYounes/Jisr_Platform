<?php

namespace Database\Seeders;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Models\EvaluationCriteria;
use App\Models\ProjectAssignment;
use App\Models\ProjectTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Log;

class SupervisorWorkflowTestSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('evaluation_items')->truncate();
        DB::table('project_evaluations')->truncate();
        DB::table('project_assignment_tasks')->truncate();
        DB::table('project_assignment_members')->truncate();
        DB::table('project_revision_requests')->truncate();
        DB::table('portfolio_projects')->truncate();
        DB::table('project_assignments')->truncate();
        DB::table('project_tasks')->truncate();
        DB::table('project_templates')->truncate();
        DB::table('evaluation_criteria')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $supervisor = User::updateOrCreate(
            ['email' => 'supervisor@test.com'],
            [
                'name' => 'Test Supervisor',
                'password' => Hash::make('password'),
            ]
        );

        $studentOne = User::updateOrCreate(
            ['email' => 'student.backend@test.com'],
            [
                'name' => 'Backend Student',
                'password' => Hash::make('password'),
            ]
        );

        $studentTwo = User::updateOrCreate(
            ['email' => 'student.frontend@test.com'],
            [
                'name' => 'Frontend Student',
                'password' => Hash::make('password'),
            ]
        );

        $studentThree = User::updateOrCreate(
            ['email' => 'student.qa@test.com'],
            [
                'name' => 'QA Student',
                'password' => Hash::make('password'),
            ]
        );

        Log::info('Student Backend Token: '.$studentOne->createToken('test-token')->plainTextToken);
        Log::info('Student Frontend Token: '.$studentTwo->createToken('test-token')->plainTextToken);
        Log::info('Student QA Token: '.$studentThree->createToken('test-token')->plainTextToken);

        if (method_exists($supervisor, 'assignRole')) {
            $supervisor->assignRole('supervisor');
            $studentOne->assignRole('student');
            $studentTwo->assignRole('student');
            $studentThree->assignRole('student');
        }

        $template = ProjectTemplate::create([
            'title' => 'Laravel Team REST API Project',
            'description' => 'A team-based Laravel project where students collaborate to build a clean REST API.',
            'level' => 'Intermediate',
            'expected_outcome' => 'A working API with authentication, CRUD, validation, and testing.',
            'created_by_type' => 'supervisor',
            'created_by_id' => $supervisor->id,
        ]);

        $taskOne = $template->tasks()->create([
            'title' => 'Build Authentication APIs',
            'description' => 'Create register, login, logout, and authenticated user endpoints.',
            'status' => 'todo',
            'estimated_hours' => 4,
            'github_branch_or_link' => 'feature/auth',
            'order_index' => 1,
        ]);

        $taskTwo = $template->tasks()->create([
            'title' => 'Build CRUD APIs',
            'description' => 'Create clean CRUD endpoints using Form Requests and API Resources.',
            'status' => 'todo',
            'estimated_hours' => 6,
            'github_branch_or_link' => 'feature/crud',
            'order_index' => 2,
        ]);

        $taskThree = $template->tasks()->create([
            'title' => 'Test APIs and Report Issues',
            'description' => 'Test all APIs and document bugs, edge cases, and missing validation.',
            'status' => 'todo',
            'estimated_hours' => 3,
            'github_branch_or_link' => 'feature/testing',
            'order_index' => 3,
        ]);

        $assignment = ProjectAssignment::create([
            'project_template_id' => $template->id,
            'supervisor_id' => $supervisor->id,
            'status' => ProjectAssignmentStatus::ASSIGNED->value,
            'progress_percentage' => 0,
            'assigned_at' => now(),
        ]);

        $assignment->members()->createMany([
            [
                'student_id' => $studentOne->id,
                'role' => 'Backend Developer',
                'status' => 'active',
            ],
            [
                'student_id' => $studentTwo->id,
                'role' => 'Frontend Developer',
                'status' => 'active',
            ],
            [
                'student_id' => $studentThree->id,
                'role' => 'QA Tester',
                'status' => 'active',
            ],
        ]);

        $assignment->assignmentTasks()->createMany([
            [
                'project_task_id' => $taskOne->id,
                'assigned_student_id' => $studentOne->id,
                'title' => $taskOne->title,
                'description' => $taskOne->description,
                'status' => ProjectAssignmentTaskStatus::TODO->value,
                'estimated_hours' => $taskOne->estimated_hours,
                'github_branch_or_link' => $taskOne->github_branch_or_link,
                'order_index' => $taskOne->order_index,
            ],
            [
                'project_task_id' => $taskTwo->id,
                'assigned_student_id' => $studentTwo->id,
                'title' => $taskTwo->title,
                'description' => $taskTwo->description,
                'status' => ProjectAssignmentTaskStatus::TODO->value,
                'estimated_hours' => $taskTwo->estimated_hours,
                'github_branch_or_link' => $taskTwo->github_branch_or_link,
                'order_index' => $taskTwo->order_index,
            ],
            [
                'project_task_id' => $taskThree->id,
                'assigned_student_id' => $studentThree->id,
                'title' => $taskThree->title,
                'description' => $taskThree->description,
                'status' => ProjectAssignmentTaskStatus::TODO->value,
                'estimated_hours' => $taskThree->estimated_hours,
                'github_branch_or_link' => $taskThree->github_branch_or_link,
                'order_index' => $taskThree->order_index,
            ],
        ]);

        EvaluationCriteria::create([
            'name' => 'Code Quality',
            'description' => 'Clean, readable, maintainable code.',
            'category' => 'technical',
            'max_score' => 5,
            'weight' => 40,
            'scoring_anchors' => [
                '1' => 'Poor structure and duplicated code.',
                '3' => 'Acceptable structure with some issues.',
                '5' => 'Clean architecture, clear naming, and maintainable code.',
            ],
            'is_active' => true,
            'is_required' => true,
        ]);

        EvaluationCriteria::create([
            'name' => 'Task Completion',
            'description' => 'Completeness and correctness of delivered tasks.',
            'category' => 'delivery',
            'max_score' => 5,
            'weight' => 30,
            'scoring_anchors' => [
                '1' => 'Most requirements missing.',
                '3' => 'Main requirements implemented with minor issues.',
                '5' => 'All requirements completed correctly.',
            ],
            'is_active' => true,
            'is_required' => true,
        ]);

        EvaluationCriteria::create([
            'name' => 'Communication & Commitment',
            'description' => 'Responsiveness, clarity, and commitment to delivery.',
            'category' => 'behavioral',
            'max_score' => 5,
            'weight' => 30,
            'scoring_anchors' => [
                '1' => 'Poor communication and weak commitment.',
                '3' => 'Acceptable communication and delivery.',
                '5' => 'Excellent communication and reliable commitment.',
            ],
            'is_active' => true,
            'is_required' => true,
        ]);
    }
}
