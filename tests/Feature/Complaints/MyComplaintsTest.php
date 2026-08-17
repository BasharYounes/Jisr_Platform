<?php

namespace Tests\Feature\Complaints;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MyComplaintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'student',
            'company',
            'supervisor',
            'admin',
        ] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_student_can_list_only_own_complaints(): void
    {
        $student = $this->createUserWithRole('student');
        $otherStudent = $this->createUserWithRole('student');
        $reportedUser = $this->createUserWithRole('supervisor');

        $own = $this->createComplaint(
            complainantId: $student->id,
            reportedUserId: $reportedUser->id,
            contextType: 'project_assignment',
            contextId: 10,
        );

        $this->createComplaint(
            complainantId: $otherStudent->id,
            reportedUserId: $reportedUser->id,
            contextType: 'project_assignment',
            contextId: 11,
        );

        Sanctum::actingAs($student);

        $this->getJson('/api/complaints/mine')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.complaints.0.id', $own->id)
            ->assertJsonPath(
                'data.complaints.0.reported_user.id',
                $reportedUser->id
            );
    }

    public function test_company_can_list_only_own_complaints(): void
    {
        $company = $this->createUserWithRole('company');
        $otherCompany = $this->createUserWithRole('company');
        $student = $this->createUserWithRole('student');

        $own = $this->createComplaint(
            complainantId: $company->id,
            reportedUserId: $student->id,
            contextType: 'company_task_assignment',
            contextId: 20,
        );

        $this->createComplaint(
            complainantId: $otherCompany->id,
            reportedUserId: $student->id,
            contextType: 'company_task_assignment',
            contextId: 21,
        );

        Sanctum::actingAs($company);

        $this->getJson('/api/complaints/mine')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.complaints.0.id', $own->id);
    }

    public function test_supervisor_can_list_only_own_complaints(): void
    {
        $supervisor = $this->createUserWithRole('supervisor');
        $otherSupervisor = $this->createUserWithRole('supervisor');
        $student = $this->createUserWithRole('student');

        $own = $this->createComplaint(
            complainantId: $supervisor->id,
            reportedUserId: $student->id,
            contextType: 'project_assignment',
            contextId: 30,
        );

        $this->createComplaint(
            complainantId: $otherSupervisor->id,
            reportedUserId: $student->id,
            contextType: 'project_assignment',
            contextId: 31,
        );

        Sanctum::actingAs($supervisor);

        $this->getJson('/api/complaints/mine')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.complaints.0.id', $own->id);
    }

    public function test_user_can_filter_own_complaints_by_status_and_context(): void
    {
        $student = $this->createUserWithRole('student');
        $reportedUser = $this->createUserWithRole('supervisor');

        $matching = $this->createComplaint(
            complainantId: $student->id,
            reportedUserId: $reportedUser->id,
            contextType: 'project_assignment',
            contextId: 40,
            status: 'resolved',
        );

        $this->createComplaint(
            complainantId: $student->id,
            reportedUserId: $reportedUser->id,
            contextType: 'project_assignment',
            contextId: 41,
            status: 'pending',
        );

        $this->createComplaint(
            complainantId: $student->id,
            reportedUserId: $reportedUser->id,
            contextType: 'company_task_assignment',
            contextId: 42,
            status: 'resolved',
        );

        Sanctum::actingAs($student);

        $this->getJson(
            '/api/complaints/mine'
            .'?status=resolved'
            .'&context_type=project_assignment'
        )
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.complaints.0.id',
                $matching->id
            );
    }

    public function test_closed_complaint_exposes_resolution_information(): void
    {
        $student = $this->createUserWithRole('student');
        $reportedUser = $this->createUserWithRole('supervisor');

        $complaint = $this->createComplaint(
            complainantId: $student->id,
            reportedUserId: $reportedUser->id,
            contextType: 'project_assignment',
            contextId: 50,
            status: 'resolved',
            resolutionNotes: 'The complaint was reviewed and resolved.',
            resolvedAt: now(),
        );

        Sanctum::actingAs($student);

        $this->getJson('/api/complaints/mine')
            ->assertOk()
            ->assertJsonPath(
                'data.complaints.0.id',
                $complaint->id
            )
            ->assertJsonPath(
                'data.complaints.0.resolution_notes',
                'The complaint was reviewed and resolved.'
            )
            ->assertJsonPath(
                'data.complaints.0.status',
                'resolved'
            );
    }

    public function test_pagination_is_applied_to_my_complaints(): void
    {
        $student = $this->createUserWithRole('student');
        $reportedUser = $this->createUserWithRole('supervisor');

        $this->createComplaint(
            complainantId: $student->id,
            reportedUserId: $reportedUser->id,
            contextType: 'project_assignment',
            contextId: 60,
        );

        $this->createComplaint(
            complainantId: $student->id,
            reportedUserId: $reportedUser->id,
            contextType: 'project_assignment',
            contextId: 61,
        );

        Sanctum::actingAs($student);

        $this->getJson('/api/complaints/mine?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonCount(1, 'data.complaints');
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($student);

        $this->getJson(
            '/api/complaints/mine'
            .'?status=invalid'
            .'&context_type=invalid'
            .'&per_page=51'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'context_type',
                'per_page',
            ]);
    }

    public function test_admin_cannot_use_my_complaints_user_route(): void
    {
        $admin = $this->createUserWithRole('admin');

        Sanctum::actingAs($admin);

        $this->getJson('/api/complaints/mine')
            ->assertForbidden();
    }

    public function test_guest_cannot_list_my_complaints(): void
    {
        $this->getJson('/api/complaints/mine')
            ->assertUnauthorized();
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function createComplaint(
        int $complainantId,
        int $reportedUserId,
        string $contextType,
        int $contextId,
        string $status = 'pending',
        ?string $resolutionNotes = null,
        mixed $resolvedAt = null,
    ): Complaint {
        return Complaint::query()->create([
            'complainant_user_id' => $complainantId,
            'reported_user_id' => $reportedUserId,
            'reported_mentor_profile_id' => null,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'reason' => 'A sufficiently detailed complaint reason for testing.',
            'status' => $status,
            'resolved_at' => $resolvedAt,
            'resolution_notes' => $resolutionNotes,
            'deduplication_key' => null,
        ]);
    }
}
