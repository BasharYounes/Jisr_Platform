<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminComplaintsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_list_complaints_with_safe_user_data(): void
    {
        $admin = $this->createUserWithRole('admin');
        $complainant = $this->createUserWithRole('student');
        $reportedUser = $this->createUserWithRole('company');

        $complaint = $this->createComplaint(
            $complainant,
            $reportedUser,
            ['reason' => 'Misleading opportunity information.']
        );

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/complaints');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.complaints.0.id', $complaint->id)
            ->assertJsonPath(
                'data.complaints.0.complainant.id',
                $complainant->id
            )
            ->assertJsonPath(
                'data.complaints.0.reported_user.id',
                $reportedUser->id
            )
            ->assertJsonPath(
                'data.complaints.0.reason',
                'Misleading opportunity information.'
            );

        $this->assertArrayNotHasKey(
            'password',
            $response->json('data.complaints.0.complainant')
        );
        $this->assertArrayNotHasKey(
            'password',
            $response->json('data.complaints.0.reported_user')
        );
    }

    public function test_admin_can_filter_complaints_by_status(): void
    {
        $admin = $this->createUserWithRole('admin');
        $complainant = $this->createUserWithRole('student');
        $reportedUser = $this->createUserWithRole('company');

        $pending = $this->createComplaint(
            $complainant,
            $reportedUser,
            ['status' => 'pending']
        );
        $this->createComplaint(
            $complainant,
            $reportedUser,
            ['status' => 'resolved']
        );

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/complaints?status=pending'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.complaints.0.id',
                $pending->id
            )
            ->assertJsonPath(
                'data.complaints.0.status',
                'pending'
            );
    }

    public function test_admin_complaints_are_paginated(): void
    {
        $admin = $this->createUserWithRole('admin');
        $complainant = $this->createUserWithRole('student');
        $reportedUser = $this->createUserWithRole('company');

        $this->createComplaint($complainant, $reportedUser);
        $this->createComplaint($complainant, $reportedUser);
        $this->createComplaint($complainant, $reportedUser);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/complaints?page=1&per_page=2'
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.complaints')
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        $admin = $this->createUserWithRole('admin');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/complaints?status=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_admin_can_mark_complaint_under_review(): void
    {
        $admin = $this->createUserWithRole('admin');
        $complaint = $this->createBasicComplaint();

        Sanctum::actingAs($admin);

        $response = $this->patchJson(
            "/api/admin/complaints/{$complaint->id}",
            [
                'status' => 'under_review',
                'resolution_notes' => 'Review started.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'under_review')
            ->assertJsonPath(
                'data.resolution_notes',
                'Review started.'
            )
            ->assertJsonPath('data.resolved_at', null);

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'under_review',
            'resolution_notes' => 'Review started.',
            'resolved_at' => null,
        ]);
    }

    public function test_resolving_complaint_requires_notes_and_sets_resolved_at(): void
    {
        $admin = $this->createUserWithRole('admin');
        $complaint = $this->createBasicComplaint();

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/complaints/{$complaint->id}",
            ['status' => 'resolved']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolution_notes');

        $response = $this->patchJson(
            "/api/admin/complaints/{$complaint->id}",
            [
                'status' => 'resolved',
                'resolution_notes' => 'Issue was resolved.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath(
                'data.resolution_notes',
                'Issue was resolved.'
            );

        $this->assertNotNull(
            $response->json('data.resolved_at')
        );

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status' => 'resolved',
            'resolution_notes' => 'Issue was resolved.',
        ]);

        $this->assertNotNull(
            Complaint::query()
                ->findOrFail($complaint->id)
                ->resolved_at
        );
    }

    public function test_rejecting_complaint_requires_notes_and_sets_resolved_at(): void
    {
        $admin = $this->createUserWithRole('admin');
        $complaint = $this->createBasicComplaint();

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/complaints/{$complaint->id}",
            ['status' => 'rejected']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resolution_notes');

        $response = $this->patchJson(
            "/api/admin/complaints/{$complaint->id}",
            [
                'status' => 'rejected',
                'resolution_notes' => 'Insufficient evidence.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertNotNull(
            $response->json('data.resolved_at')
        );
    }

    public function test_admin_cannot_set_complaint_back_to_pending(): void
    {
        $admin = $this->createUserWithRole('admin');
        $complaint = $this->createBasicComplaint();

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/complaints/{$complaint->id}",
            ['status' => 'pending']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_ignores_fields_admin_is_not_allowed_to_change(): void
    {
        $admin = $this->createUserWithRole('admin');
        $complaint = $this->createBasicComplaint();

        $originalComplainantId = $complaint->complainant_user_id;
        $originalReportedUserId = $complaint->reported_user_id;
        $originalReason = $complaint->reason;

        $otherUser = $this->createUserWithRole('student');

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/complaints/{$complaint->id}",
            [
                'status' => 'under_review',
                'complainant_user_id' => $otherUser->id,
                'reported_user_id' => $otherUser->id,
                'reason' => 'Tampered reason.',
            ]
        )->assertOk();

        $complaint->refresh();

        $this->assertSame(
            $originalComplainantId,
            $complaint->complainant_user_id
        );
        $this->assertSame(
            $originalReportedUserId,
            $complaint->reported_user_id
        );
        $this->assertSame(
            $originalReason,
            $complaint->reason
        );
    }

    public function test_non_admin_users_cannot_use_admin_complaints_endpoints(): void
    {
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($student);

        $this->getJson('/api/admin/complaints')
            ->assertForbidden();
    }

    public function test_guest_cannot_use_admin_complaints_endpoints(): void
    {
        $this->getJson('/api/admin/complaints')
            ->assertUnauthorized();
    }

    private function createBasicComplaint(): Complaint
    {
        return $this->createComplaint(
            $this->createUserWithRole('student'),
            $this->createUserWithRole('company')
        );
    }

    private function createComplaint(
        User $complainant,
        User $reportedUser,
        array $attributes = []
    ): Complaint {
        return Complaint::query()->create([
            'complainant_user_id' => $complainant->id,
            'reported_user_id' => $reportedUser->id,
            'reason' => 'Complaint reason.',
            'status' => 'pending',
            'resolved_at' => null,
            'resolution_notes' => null,
            ...$attributes,
        ]);
    }

    private function createUserWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
