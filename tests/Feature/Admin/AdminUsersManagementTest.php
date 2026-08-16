<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminUsersManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_list_only_regular_students(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student');
        $company = $this->createUserWithRole('company');
        $supervisor = $this->createSupervisor();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?role=student');

        $response->assertOk();

        $ids = collect($response->json('data.users'))->pluck('id');

        $this->assertTrue($ids->contains($student->id));
        $this->assertFalse($ids->contains($company->id));
        $this->assertFalse($ids->contains($supervisor->id));
        $this->assertFalse($ids->contains($admin->id));
    }

    public function test_admin_can_list_only_companies(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student');
        $company = $this->createUserWithRole('company');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?role=company');

        $response->assertOk();

        $ids = collect($response->json('data.users'))->pluck('id');

        $this->assertTrue($ids->contains($company->id));
        $this->assertFalse($ids->contains($student->id));
        $this->assertFalse($ids->contains($admin->id));
    }

    public function test_admin_can_list_supervisors_and_supervisor_leads(): void
    {
        $admin = $this->createUserWithRole('admin');
        $supervisor = $this->createSupervisor();
        $lead = $this->createSupervisorLead();
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?role=supervisor');

        $response->assertOk();

        $ids = collect($response->json('data.users'))->pluck('id');

        $this->assertTrue($ids->contains($supervisor->id));
        $this->assertTrue($ids->contains($lead->id));
        $this->assertFalse($ids->contains($student->id));
    }

    public function test_invalid_role_filter_is_rejected(): void
    {
        $admin = $this->createUserWithRole('admin');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?role=invalid-role')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_admin_can_block_user_and_revoke_all_tokens(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student');

        $student->createToken('student-device-one');
        $student->createToken('student-device-two');

        $this->assertSame(2, $student->tokens()->count());

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$student->id}/block")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'is_active' => 0,
        ]);

        $this->assertSame(0, $student->tokens()->count());
    }

    public function test_blocked_user_cannot_login_again(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$student->id}/block")
            ->assertOk();

        $this->postJson('/api/login', [
            'email' => $student->email,
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_admin_can_unblock_user(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student', [
            'is_active' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$student->id}/unblock")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_block_supervisor_and_supervisor_lead(): void
    {
        $admin = $this->createUserWithRole('admin');
        $supervisor = $this->createSupervisor();
        $lead = $this->createSupervisorLead();

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$supervisor->id}/block")
            ->assertOk();

        $this->postJson("/api/admin/users/{$lead->id}/block")
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $supervisor->id,
            'is_active' => 0,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $lead->id,
            'is_active' => 0,
        ]);
    }

    public function test_admin_account_cannot_be_blocked_through_user_access_endpoint(): void
    {
        $actingAdmin = $this->createUserWithRole('admin');
        $targetAdmin = $this->createUserWithRole('admin');

        Sanctum::actingAs($actingAdmin);

        $this->postJson("/api/admin/users/{$targetAdmin->id}/block")
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $targetAdmin->id,
            'is_active' => 1,
        ]);
    }

    public function test_non_admin_user_cannot_use_admin_user_management_endpoints(): void
    {
        $student = $this->createUserWithRole('student');
        $target = $this->createUserWithRole('company');

        Sanctum::actingAs($student);

        $this->getJson('/api/admin/users?role=company')
            ->assertForbidden();

        $this->postJson("/api/admin/users/{$target->id}/block")
            ->assertForbidden();
    }

    public function test_guest_cannot_use_admin_user_management_endpoints(): void
    {
        $target = $this->createUserWithRole('student');

        $this->getJson('/api/admin/users?role=student')
            ->assertUnauthorized();

        $this->postJson("/api/admin/users/{$target->id}/block")
            ->assertUnauthorized();
    }

    private function createSupervisor(): User
    {
        $user = $this->createUserWithRole('student');

        Role::findOrCreate('supervisor', 'web');
        $user->assignRole('supervisor');

        return $user;
    }

    private function createSupervisorLead(): User
    {
        $user = $this->createSupervisor();

        Role::findOrCreate('supervisor_lead', 'web');
        $user->assignRole('supervisor_lead');

        return $user;
    }

    private function createUserWithRole(
        string $role,
        array $attributes = []
    ): User {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
