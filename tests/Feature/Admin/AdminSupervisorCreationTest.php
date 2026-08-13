<?php

namespace Tests\Feature\Admin;

use App\Models\SupervisorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminSupervisorCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('student', 'web');
        Role::findOrCreate('company', 'web');
        Role::findOrCreate('supervisor', 'web');
    }

    public function test_admin_can_create_supervisor_account_without_issuing_token(): void
    {
        $admin = $this->createUserWithRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/supervisors', [
            'name' => 'Ahmad Supervisor',
            'email' => 'ahmad.supervisor@example.com',
            'password' => 'secret123',
            'specialization' => 'backend',
            'is_volunteer' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Ahmad Supervisor')
            ->assertJsonPath(
                'data.email',
                'ahmad.supervisor@example.com'
            )
            ->assertJsonPath('data.roles.0', 'supervisor')
            ->assertJsonPath(
                'data.supervisor_profile.specialization',
                'backend'
            )
            ->assertJsonPath(
                'data.supervisor_profile.is_volunteer',
                true
            )
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('token');

        $supervisor = User::query()
            ->where('email', 'ahmad.supervisor@example.com')
            ->firstOrFail();

        $this->assertTrue($supervisor->hasRole('supervisor'));
        $this->assertFalse($supervisor->hasRole('supervisor_lead'));
        $this->assertSame(0, $supervisor->tokens()->count());

        $this->assertDatabaseHas('supervisor_profiles', [
            'user_id' => $supervisor->id,
            'specialization' => 'backend',
            'is_volunteer' => true,
        ]);
    }

    public function test_is_volunteer_defaults_to_false(): void
    {
        $admin = $this->createUserWithRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/supervisors', [
            'name' => 'Non Volunteer Supervisor',
            'email' => 'non.volunteer@example.com',
            'password' => 'secret123',
            'specialization' => 'frontend',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.supervisor_profile.is_volunteer',
                false
            );

        $supervisor = User::query()
            ->where('email', 'non.volunteer@example.com')
            ->firstOrFail();

        $this->assertDatabaseHas('supervisor_profiles', [
            'user_id' => $supervisor->id,
            'is_volunteer' => false,
        ]);
    }

    public function test_invalid_supervisor_specialization_is_rejected(): void
    {
        $admin = $this->createUserWithRole('admin');

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/supervisors', [
            'name' => 'Invalid Supervisor',
            'email' => 'invalid.specialization@example.com',
            'password' => 'secret123',
            'specialization' => 'invalid-specialization',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('specialization');

        $this->assertDatabaseMissing('users', [
            'email' => 'invalid.specialization@example.com',
        ]);
    }

    public function test_duplicate_supervisor_email_is_rejected(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $admin = $this->createUserWithRole('admin');

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/supervisors', [
            'name' => 'Duplicate Supervisor',
            'email' => $existing->email,
            'password' => 'secret123',
            'specialization' => 'devops',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_public_registration_cannot_create_supervisor(): void
    {
        $this->postJson('/api/register', [
            'role' => 'supervisor',
            'name' => 'Public Supervisor',
            'email' => 'public.supervisor@example.com',
            'password' => 'secret123',
            'specialization' => 'backend',
            'is_volunteer' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseMissing('users', [
            'email' => 'public.supervisor@example.com',
        ]);
    }

    public function test_student_cannot_create_supervisor_account(): void
    {
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($student);

        $this->postJson('/api/admin/supervisors', [
            'name' => 'Forbidden Supervisor',
            'email' => 'forbidden.student@example.com',
            'password' => 'secret123',
            'specialization' => 'flutter',
        ])->assertForbidden();
    }

    public function test_company_cannot_create_supervisor_account(): void
    {
        $company = $this->createUserWithRole('company');
        $company->is_verified_by_admin = 'accepted';
        $company->save();

        Sanctum::actingAs($company);

        $this->postJson('/api/admin/supervisors', [
            'name' => 'Forbidden Supervisor',
            'email' => 'forbidden.company@example.com',
            'password' => 'secret123',
            'specialization' => 'ai',
        ])->assertForbidden();
    }

    public function test_guest_cannot_create_supervisor_account(): void
    {
        $this->postJson('/api/admin/supervisors', [
            'name' => 'Guest Supervisor',
            'email' => 'guest.supervisor@example.com',
            'password' => 'secret123',
            'specialization' => 'backend',
        ])->assertUnauthorized();
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
