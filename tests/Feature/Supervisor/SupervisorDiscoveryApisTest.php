<?php

namespace Tests\Feature\Supervisor;

use App\Domains\Supervisor\Enums\EvaluationRevisionRequestSource;
use App\Domains\Supervisor\Enums\EvaluationRevisionRequestStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Enums\SupervisorSpecialization;
use App\Models\EvaluationRevisionRequest;
use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use App\Models\ProjectTemplate;
use App\Models\SupervisorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SupervisorDiscoveryApisTest extends TestCase
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

        Role::firstOrCreate([
            'name' => 'supervisor_lead',
            'guard_name' => 'web',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_lead_evaluations_are_scoped_to_specialization(): void
    {
        $lead = $this->makeSupervisor(
            'lead.frontend@test.com',
            SupervisorSpecialization::Frontend,
            true
        );

        $frontendSupervisor = $this->makeSupervisor(
            'frontend@test.com',
            SupervisorSpecialization::Frontend
        );

        $backendSupervisor = $this->makeSupervisor(
            'backend@test.com',
            SupervisorSpecialization::Backend
        );

        $student = $this->makeStudent('student@test.com');

        $frontendAssignment = $this->makeAssignment(
            $frontendSupervisor,
            ProjectAssignmentStatus::UNDER_REVIEW
        );

        $backendAssignment = $this->makeAssignment(
            $backendSupervisor,
            ProjectAssignmentStatus::UNDER_REVIEW
        );

        $frontendEvaluation = $this->makeEvaluation(
            $frontendAssignment,
            $frontendSupervisor,
            $student,
            ProjectEvaluationStatus::SUBMITTED
        );

        $this->makeEvaluation(
            $backendAssignment,
            $backendSupervisor,
            $student,
            ProjectEvaluationStatus::SUBMITTED
        );

        Sanctum::actingAs($lead);

        $response = $this->getJson(
            '/api/supervisor/project-evaluations?status=submitted&scope=specialization'
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.evaluations')
            ->assertJsonPath(
                'data.evaluations.0.id',
                $frontendEvaluation->id
            )
            ->assertJsonPath(
                'data.evaluations.0.actions.can_request_revision',
                true
            );
    }

    public function test_my_evaluations_returns_only_current_supervisor_and_latest_revision_reason(): void
    {
        $lead = $this->makeSupervisor(
            'lead@test.com',
            SupervisorSpecialization::Frontend,
            true
        );

        $supervisor = $this->makeSupervisor(
            'owner@test.com',
            SupervisorSpecialization::Frontend
        );

        $otherSupervisor = $this->makeSupervisor(
            'other@test.com',
            SupervisorSpecialization::Frontend
        );

        $student = $this->makeStudent('student2@test.com');

        $assignment = $this->makeAssignment(
            $supervisor,
            ProjectAssignmentStatus::UNDER_REVIEW
        );

        $otherAssignment = $this->makeAssignment(
            $otherSupervisor,
            ProjectAssignmentStatus::UNDER_REVIEW
        );

        $evaluation = $this->makeEvaluation(
            $assignment,
            $supervisor,
            $student,
            ProjectEvaluationStatus::NEEDS_REVISION
        );

        $this->makeEvaluation(
            $otherAssignment,
            $otherSupervisor,
            $student,
            ProjectEvaluationStatus::NEEDS_REVISION
        );

        EvaluationRevisionRequest::create([
            'project_evaluation_id' => $evaluation->id,
            'requested_by' => $lead->id,
            'assigned_to' => $supervisor->id,
            'source' => EvaluationRevisionRequestSource::LeadReview->value,
            'reason' => 'The score needs to be reviewed.',
            'status' => EvaluationRevisionRequestStatus::Pending->value,
        ]);

        Sanctum::actingAs($supervisor);

        $response = $this->getJson(
            '/api/supervisor/my-project-evaluations?status=needs_revision'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.evaluations')
            ->assertJsonPath(
                'data.evaluations.0.id',
                $evaluation->id
            )
            ->assertJsonPath(
                'data.evaluations.0.latest_revision_request.reason',
                'The score needs to be reviewed.'
            )
            ->assertJsonPath(
                'data.evaluations.0.actions.can_edit',
                true
            )
            ->assertJsonPath(
                'data.evaluations.0.actions.can_resubmit',
                true
            );
    }

    public function test_lead_assignments_returns_only_active_same_specialization_projects(): void
    {
        $lead = $this->makeSupervisor(
            'lead2@test.com',
            SupervisorSpecialization::Frontend,
            true
        );

        $frontendSupervisor = $this->makeSupervisor(
            'frontend2@test.com',
            SupervisorSpecialization::Frontend
        );

        $backendSupervisor = $this->makeSupervisor(
            'backend2@test.com',
            SupervisorSpecialization::Backend
        );

        $visible = $this->makeAssignment(
            $frontendSupervisor,
            ProjectAssignmentStatus::UNDER_REVIEW
        );

        $this->makeAssignment(
            $frontendSupervisor,
            ProjectAssignmentStatus::COMPLETED
        );

        $this->makeAssignment(
            $backendSupervisor,
            ProjectAssignmentStatus::UNDER_REVIEW
        );

        Sanctum::actingAs($lead);

        $response = $this->getJson(
            '/api/supervisor/lead/project-assignments?status=under_review'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data.assignments')
            ->assertJsonPath(
                'data.assignments.0.id',
                $visible->id
            )
            ->assertJsonPath(
                'data.assignments.0.supervisor.id',
                $frontendSupervisor->id
            )
            ->assertJsonPath(
                'data.assignments.0.actions.can_change_supervisor',
                true
            );
    }

    public function test_supervisor_list_includes_blocking_hints(): void
    {
        $lead = $this->makeSupervisor(
            'lead3@test.com',
            SupervisorSpecialization::Frontend,
            true
        );

        $freeSupervisor = $this->makeSupervisor(
            'free@test.com',
            SupervisorSpecialization::Frontend
        );

        $busySupervisor = $this->makeSupervisor(
            'busy@test.com',
            SupervisorSpecialization::Frontend
        );

        $this->makeAssignment(
            $busySupervisor,
            ProjectAssignmentStatus::IN_PROGRESS
        );

        Sanctum::actingAs($lead);

        $response = $this->getJson(
            '/api/supervisor/supervisors'
        );

        $response->assertOk();

        $rows = collect($response->json('data'));

        $free = $rows->firstWhere('id', $freeSupervisor->id);
        $busy = $rows->firstWhere('id', $busySupervisor->id);

        $this->assertSame(0, $free['active_projects_count']);
        $this->assertTrue($free['can_be_blocked']);
        $this->assertNull($free['blocking_reason']);

        $this->assertSame(1, $busy['active_projects_count']);
        $this->assertFalse($busy['can_be_blocked']);
        $this->assertNotNull($busy['blocking_reason']);
    }

    private function makeSupervisor(
        string $email,
        SupervisorSpecialization $specialization,
        bool $isLead = false
    ): User {
        $user = User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'password123',
            'is_active' => true,
        ]);

        $roles = ['supervisor'];

        if ($isLead) {
            $roles[] = 'supervisor_lead';
        }

        $user->syncRoles($roles);

        SupervisorProfile::create([
            'user_id' => $user->id,
            'specialization' => $specialization->value,
            'is_volunteer' => false,
        ]);

        return $user;
    }

    private function makeStudent(string $email): User
    {
        $student = User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'password123',
            'is_active' => true,
        ]);

        $student->assignRole('student');

        return $student;
    }

    private function makeAssignment(
        User $supervisor,
        ProjectAssignmentStatus $status
    ): ProjectAssignment {
        $template = ProjectTemplate::create([
            'title' => 'Project '.$supervisor->id.' '.uniqid(),
            'description' => 'Test project',
            'level' => 'Intermediate',
            'expected_outcome' => 'Test outcome',
            'max_students' => 3,
            'created_by_type' => 'supervisor',
            'created_by_id' => $supervisor->id,
        ]);

        return ProjectAssignment::create([
            'project_template_id' => $template->id,
            'supervisor_id' => $supervisor->id,
            'status' => $status->value,
            'progress_percentage' => 100,
            'assigned_at' => now(),
        ]);
    }

    private function makeEvaluation(
        ProjectAssignment $assignment,
        User $supervisor,
        User $student,
        ProjectEvaluationStatus $status
    ): ProjectEvaluation {
        return ProjectEvaluation::create([
            'project_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'total_score' => 80,
            'final_grade' => 80,
            'status' => $status->value,
            'general_comment' => 'Evaluation comment',
            'summary_metrics' => [
                'criteria_count' => 1,
                'total_weight' => 100,
            ],
            'evaluated_at' => now(),
            'appeal_started_at' => now(),
            'appeal_deadline_at' => now()->addHours(48),
        ]);
    }
}
