<?php

namespace Tests\Feature\Student;

use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentProjectTemplateDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['student', 'supervisor', 'company'] as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_student_lists_only_supervisor_created_projects(): void
    {
        $student = $this->makeUser('student.discovery@test.com', 'student');
        $supervisor = $this->makeUser('supervisor.discovery@test.com', 'supervisor');

        $supervisor->supervisorProfile()->create([
            'specialization' => 'backend',
            'is_volunteer' => true,
        ]);

        $visible = $this->makeTemplate(
            creator: $supervisor,
            title: 'Laravel API Project',
            level: 'Intermediate'
        );

        $visible->tasks()->createMany([
            [
                'title' => 'Authentication',
                'description' => 'Build auth endpoints.',
                'estimated_hours' => 4,
                'order_index' => 1,
            ],
            [
                'title' => 'CRUD',
                'description' => 'Build CRUD endpoints.',
                'estimated_hours' => 6,
                'order_index' => 2,
            ],
        ]);

        ProjectTemplate::query()->create([
            'title' => 'Non-supervisor project',
            'description' => 'Must not be visible.',
            'level' => 'Beginner',
            'expected_outcome' => 'Hidden.',
            'max_students' => 1,
            'created_by_type' => 'company',
            'created_by_id' => 999,
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/student/project-templates?level=Intermediate');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonPath('data.projects.0.id', $visible->id)
            ->assertJsonPath('data.projects.0.supervisor.id', $supervisor->id)
            ->assertJsonPath('data.projects.0.supervisor.specialization', 'backend')
            ->assertJsonPath('data.projects.0.tasks_summary.count', 2)
            ->assertJsonPath('data.projects.0.tasks_summary.estimated_total_hours', 10)
            ->assertJsonPath('data.projects.0.actions.can_apply', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonMissingPath('data.projects.0.tasks');
    }

    public function test_project_details_load_tasks_only_after_selection(): void
    {
        $student = $this->makeUser('student.details@test.com', 'student');
        $supervisor = $this->makeUser('supervisor.details@test.com', 'supervisor');

        $template = $this->makeTemplate(
            creator: $supervisor,
            title: 'Detailed Project',
            level: 'Advanced'
        );

        $task = $template->tasks()->create([
            'title' => 'API contract',
            'description' => 'Implement the required API.',
            'estimated_hours' => 5,
            'github_branch_or_link' => 'private/implementation-link',
            'order_index' => 1,
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson(
            "/api/student/project-templates/{$template->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonPath('data.tasks.0.id', $task->id)
            ->assertJsonPath('data.tasks.0.title', 'API contract')
            ->assertJsonMissingPath('data.tasks.0.github_branch_or_link');
    }

    public function test_list_exposes_current_student_application_state_and_action_flags(): void
    {
        $student = $this->makeUser('student.application@test.com', 'student');
        $supervisor = $this->makeUser('supervisor.application@test.com', 'supervisor');

        $template = $this->makeTemplate(
            creator: $supervisor,
            title: 'Applied Project',
            level: 'Beginner'
        );

        $application = ProjectTemplateApplication::query()->create([
            'project_template_id' => $template->id,
            'student_user_id' => $student->id,
            'message' => 'Interested.',
            'status' => ProjectTemplateApplicationStatus::PENDING->value,
            'applied_at' => now(),
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/student/project-templates');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.projects.0.application.application_id',
                $application->id
            )
            ->assertJsonPath(
                'data.projects.0.application.status',
                ProjectTemplateApplicationStatus::PENDING->value
            )
            ->assertJsonPath('data.projects.0.actions.can_apply', false)
            ->assertJsonPath(
                'data.projects.0.actions.apply_block_reason',
                'already_applied'
            );
    }

    public function test_capacity_flag_and_apply_endpoint_use_the_same_rule(): void
    {
        $student = $this->makeUser('student.capacity@test.com', 'student');
        $otherStudent = $this->makeUser('other.capacity@test.com', 'student');
        $supervisor = $this->makeUser('supervisor.capacity@test.com', 'supervisor');

        $template = $this->makeTemplate(
            creator: $supervisor,
            title: 'Full Project',
            level: 'Intermediate',
            maxStudents: 1
        );

        ProjectTemplateApplication::query()->create([
            'project_template_id' => $template->id,
            'student_user_id' => $otherStudent->id,
            'status' => ProjectTemplateApplicationStatus::PENDING->value,
            'applied_at' => now(),
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/student/project-templates')
            ->assertOk()
            ->assertJsonPath('data.projects.0.capacity.is_full', true)
            ->assertJsonPath('data.projects.0.actions.can_apply', false)
            ->assertJsonPath(
                'data.projects.0.actions.apply_block_reason',
                'capacity_reached'
            );

        $this->postJson(
            "/api/student/project-templates/{$template->id}/apply",
            ['message' => 'Please accept me.']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors('project_template');
    }

    public function test_duplicate_application_returns_422_not_500(): void
    {
        $student = $this->makeUser('student.duplicate@test.com', 'student');
        $supervisor = $this->makeUser('supervisor.duplicate@test.com', 'supervisor');

        $template = $this->makeTemplate(
            creator: $supervisor,
            title: 'Duplicate Guard Project',
            level: 'Beginner'
        );

        ProjectTemplateApplication::query()->create([
            'project_template_id' => $template->id,
            'student_user_id' => $student->id,
            'status' => ProjectTemplateApplicationStatus::PENDING->value,
            'applied_at' => now(),
        ]);

        Sanctum::actingAs($student);

        $this->postJson(
            "/api/student/project-templates/{$template->id}/apply",
            ['message' => 'Second request.']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors('project_template');
    }

    public function test_non_student_cannot_use_student_project_discovery(): void
    {
        $supervisor = $this->makeUser('wrong.role@test.com', 'supervisor');

        Sanctum::actingAs($supervisor);

        $this->getJson('/api/student/project-templates')
            ->assertForbidden();
    }

    public function test_guest_cannot_use_student_project_discovery(): void
    {
        $this->getJson('/api/student/project-templates')
            ->assertUnauthorized();
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

    private function makeTemplate(
        User $creator,
        string $title,
        string $level,
        ?int $maxStudents = 3
    ): ProjectTemplate {
        return ProjectTemplate::query()->create([
            'title' => $title,
            'description' => 'Project description.',
            'level' => $level,
            'expected_outcome' => 'Working deliverable.',
            'max_students' => $maxStudents,
            'created_by_type' => 'supervisor',
            'created_by_id' => $creator->id,
        ]);
    }
}
