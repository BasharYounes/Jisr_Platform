<?php

namespace Tests\Feature\Student;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Models\ProjectAssignment;
use App\Models\ProjectAssignmentTask;
use App\Models\ProjectTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AssignedProjectTasksDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate([
            'name' => 'student',
            'guard_name' => 'web',
        ]);

        Role::firstOrCreate([
            'name' => 'supervisor',
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_student_sees_only_project_tasks_assigned_to_them(): void
    {
        $student = $this->makeUser('student.one@test.com', 'student');
        $otherStudent = $this->makeUser('student.two@test.com', 'student');
        $supervisor = $this->makeUser('supervisor@test.com', 'supervisor');

        [$assignment, $templateTask] = $this->makeAssignment($supervisor);

        $visible = $this->makeTask(
            assignment: $assignment,
            templateTaskId: $templateTask->id,
            student: $student,
            title: 'Visible project task',
            status: ProjectAssignmentTaskStatus::DONE,
        );

        $this->makeTask(
            assignment: $assignment,
            templateTaskId: $templateTask->id,
            student: $otherStudent,
            title: 'Other student task',
            status: ProjectAssignmentTaskStatus::DONE,
        );

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/student/project-assignment-tasks');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.id', $visible->id)
            ->assertJsonPath('data.tasks.0.source', 'project_assignment')
            ->assertJsonPath('data.tasks.0.project_assignment_id', $assignment->id)
            ->assertJsonPath('data.tasks.0.assignment.id', $assignment->id)
            ->assertJsonPath(
                'data.tasks.0.assignment.project_template.title',
                '[TEST] Student Project Tasks'
            )
            ->assertJsonPath(
                'data.tasks.0.assignment.supervisor.id',
                $supervisor->id
            )
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_student_can_filter_assigned_project_tasks_by_status_and_assignment(): void
    {
        $student = $this->makeUser('filter.student@test.com', 'student');
        $supervisor = $this->makeUser('filter.supervisor@test.com', 'supervisor');

        [$assignment, $templateTask] = $this->makeAssignment($supervisor);

        $doneTask = $this->makeTask(
            assignment: $assignment,
            templateTaskId: $templateTask->id,
            student: $student,
            title: 'Done task',
            status: ProjectAssignmentTaskStatus::DONE,
        );

        $this->makeTask(
            assignment: $assignment,
            templateTaskId: $templateTask->id,
            student: $student,
            title: 'Todo task',
            status: ProjectAssignmentTaskStatus::TODO,
        );

        Sanctum::actingAs($student);

        $response = $this->getJson(
            '/api/student/project-assignment-tasks'
            .'?status=done'
            .'&project_assignment_id='
            .$assignment->id
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.tasks.0.id', $doneTask->id)
            ->assertJsonPath('data.tasks.0.status', 'done');
    }

    public function test_student_with_no_project_tasks_receives_empty_list_not_error(): void
    {
        $student = $this->makeUser('empty.student@test.com', 'student');

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/student/project-assignment-tasks');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data.tasks')
            ->assertJsonPath('data.pagination.total', 0);
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::query()->create([
            'name' => $role.' test user',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function makeAssignment(User $supervisor): array
    {
        $template = ProjectTemplate::query()->create([
            'title' => '[TEST] Student Project Tasks',
            'description' => 'Test project template.',
            'level' => 'Intermediate',
            'expected_outcome' => 'Test outcome.',
            'max_students' => 2,
            'created_by_type' => 'supervisor',
            'created_by_id' => $supervisor->id,
        ]);

        $templateTask = $template->tasks()->create([
            'title' => 'Template task',
            'description' => 'Template task description.',
            'status' => 'todo',
            'estimated_hours' => 4,
            'order_index' => 1,
        ]);

        $assignment = ProjectAssignment::query()->create([
            'project_template_id' => $template->id,
            'supervisor_id' => $supervisor->id,
            'status' => ProjectAssignmentStatus::UNDER_REVIEW->value,
            'progress_percentage' => 100,
            'assigned_at' => now()->subDay(),
        ]);

        return [$assignment, $templateTask];
    }

    private function makeTask(
        ProjectAssignment $assignment,
        int $templateTaskId,
        User $student,
        string $title,
        ProjectAssignmentTaskStatus $status
    ): ProjectAssignmentTask {
        return ProjectAssignmentTask::query()->create([
            'project_assignment_id' => $assignment->id,
            'project_task_id' => $templateTaskId,
            'assigned_student_id' => $student->id,
            'title' => $title,
            'description' => 'Assigned project task.',
            'status' => $status->value,
            'estimated_hours' => 4,
            'order_index' => 1,
        ]);
    }
}
