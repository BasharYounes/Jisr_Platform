<?php

namespace Tests\Feature\Student;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationAppealStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use App\Models\ProjectEvaluationAppeal;
use App\Models\ProjectTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentEvaluationAppealGuardAndListTest extends TestCase
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

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }

    public function test_pending_appeal_makes_can_appeal_false_and_returns_business_validation_on_second_submission(): void
    {
        [$student, , $assignment, $evaluation] =
            $this->makeEvaluationFixture();

        ProjectEvaluationAppeal::query()->create([
            'project_evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'reason' => 'The first appeal is still waiting for review.',
            'status' => ProjectEvaluationAppealStatus::Pending->value,
            'evaluation_snapshot' => [],
        ]);

        Sanctum::actingAs($student);

        $this->getJson(
            '/api/student/project-assignments/'
            .$assignment->id
            .'/evaluation'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.can_appeal',
                false
            )
            ->assertJsonCount(
                1,
                'data.appeals'
            );

        $this->postJson(
            '/api/student/project-evaluations/'
            .$evaluation->id
            .'/appeals',
            [
                'reason' => 'This second appeal must be blocked while the first is pending.',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'appeal',
            ])
            ->assertJsonPath(
                'errors.appeal.0',
                'You already have a pending appeal for this evaluation. Wait until it is reviewed before submitting another appeal.'
            );

        $this->assertDatabaseCount(
            'project_evaluation_appeals',
            1
        );
    }

    public function test_other_student_cannot_submit_appeal_for_an_evaluation_they_do_not_own(): void
    {
        [, , , $evaluation] =
            $this->makeEvaluationFixture();

        $otherStudent = $this->makeUser(
            'unauthorized.student@test.com',
            'student'
        );

        Sanctum::actingAs($otherStudent);

        $this->postJson(
            '/api/student/project-evaluations/'
            .$evaluation->id
            .'/appeals',
            [
                'reason' => 'This evaluation belongs to a different student.',
            ]
        )->assertForbidden();

        $this->assertDatabaseCount(
            'project_evaluation_appeals',
            0
        );
    }

    public function test_rejected_appeal_allows_another_appeal_while_window_is_open(): void
    {
        [$student, , , $evaluation] =
            $this->makeEvaluationFixture();

        ProjectEvaluationAppeal::query()->create([
            'project_evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'reason' => 'Previously rejected appeal.',
            'status' => ProjectEvaluationAppealStatus::Rejected->value,
            'evaluation_snapshot' => [],
            'review_notes' => 'Rejected for insufficient evidence.',
            'reviewed_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($student);

        $this->postJson(
            '/api/student/project-evaluations/'
            .$evaluation->id
            .'/appeals',
            [
                'reason' => 'New evidence is available and this is a new appeal after rejection.',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'pending'
            );

        $this->assertDatabaseCount(
            'project_evaluation_appeals',
            2
        );
    }

    public function test_needs_revision_returns_business_validation_until_evaluation_is_resubmitted(): void
    {
        [$student, , , $evaluation] =
            $this->makeEvaluationFixture();

        ProjectEvaluationAppeal::query()->create([
            'project_evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'reason' => 'Accepted appeal.',
            'status' => ProjectEvaluationAppealStatus::Accepted->value,
            'evaluation_snapshot' => [],
            'review_notes' => 'Accepted.',
            'reviewed_at' => now()->subMinute(),
        ]);

        $evaluation->update([
            'status' => ProjectEvaluationStatus::NEEDS_REVISION->value,
        ]);

        Sanctum::actingAs($student);

        $this->postJson(
            '/api/student/project-evaluations/'
            .$evaluation->id
            .'/appeals',
            [
                'reason' => 'This must be blocked while the evaluation is being revised.',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ])
            ->assertJsonPath(
                'errors.status.0',
                'An appeal can be submitted only for a submitted evaluation.'
            );

        $evaluation->update([
            'status' => ProjectEvaluationStatus::SUBMITTED->value,
            'evaluated_at' => now(),
        ]);

        $this->postJson(
            '/api/student/project-evaluations/'
            .$evaluation->id
            .'/appeals',
            [
                'reason' => 'This appeal targets the newly resubmitted evaluation version.',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'pending'
            );
    }

    public function test_student_appeal_list_returns_only_authenticated_student_and_supports_status_filter(): void
    {
        [$student, $supervisor, $assignment, $evaluation] =
            $this->makeEvaluationFixture();

        $otherStudent = $this->makeUser(
            'other.student@test.com',
            'student'
        );

        $pending = ProjectEvaluationAppeal::query()->create([
            'project_evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'reason' => 'My pending appeal.',
            'status' => ProjectEvaluationAppealStatus::Pending->value,
            'evaluation_snapshot' => [],
        ]);

        ProjectEvaluationAppeal::query()->create([
            'project_evaluation_id' => $evaluation->id,
            'student_id' => $otherStudent->id,
            'reason' => 'Other student appeal.',
            'status' => ProjectEvaluationAppealStatus::Pending->value,
            'evaluation_snapshot' => [],
        ]);

        ProjectEvaluationAppeal::query()->create([
            'project_evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'reason' => 'My rejected appeal.',
            'status' => ProjectEvaluationAppealStatus::Rejected->value,
            'evaluation_snapshot' => [],
        ]);

        Sanctum::actingAs($student);

        $this->getJson(
            '/api/student/evaluation-appeals?status=pending'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.appeals')
            ->assertJsonPath(
                'data.appeals.0.id',
                $pending->id
            )
            ->assertJsonPath(
                'data.appeals.0.evaluation.assignment.id',
                $assignment->id
            )
            ->assertJsonPath(
                'data.appeals.0.evaluation.assignment.project_template.title',
                '[TEST] Appeal Flow Project'
            )
            ->assertJsonPath(
                'data.appeals.0.evaluation.supervisor.id',
                $supervisor->id
            )
            ->assertJsonPath(
                'data.pagination.total',
                1
            );
    }

    private function makeEvaluationFixture(): array
    {
        $student = $this->makeUser(
            'appeal.student@test.com',
            'student'
        );

        $supervisor = $this->makeUser(
            'appeal.supervisor@test.com',
            'supervisor'
        );

        $template = ProjectTemplate::query()->create([
            'title' => '[TEST] Appeal Flow Project',
            'description' => 'Project used to test appeal flow.',
            'level' => 'Intermediate',
            'expected_outcome' => 'Appeal flow test.',
            'max_students' => 1,
            'created_by_type' => 'supervisor',
            'created_by_id' => $supervisor->id,
        ]);

        $assignment = ProjectAssignment::query()->create([
            'project_template_id' => $template->id,
            'supervisor_id' => $supervisor->id,
            'status' => ProjectAssignmentStatus::UNDER_REVIEW->value,
            'progress_percentage' => 100,
            'assigned_at' => now()->subDay(),
        ]);

        $assignment->members()->create([
            'student_id' => $student->id,
            'role' => 'Developer',
            'status' => 'active',
        ]);

        $startedAt = now()->subHour();

        $evaluation = ProjectEvaluation::query()->create([
            'project_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'total_score' => 80,
            'final_grade' => 80,
            'status' => ProjectEvaluationStatus::SUBMITTED->value,
            'general_comment' => 'Test evaluation.',
            'summary_metrics' => [],
            'evaluated_at' => $startedAt,
            'appeal_started_at' => $startedAt,
            'appeal_deadline_at' => now()->addHours(20),
        ]);

        return [
            $student,
            $supervisor,
            $assignment,
            $evaluation,
        ];
    }

    private function makeUser(
        string $email,
        string $role
    ): User {
        $user = User::query()->create([
            'name' => ucfirst(
                str_replace(
                    ['.', '@test.com'],
                    [' ', ''],
                    $email
                )
            ),
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
