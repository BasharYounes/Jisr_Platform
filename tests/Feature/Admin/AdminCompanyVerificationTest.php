<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminCompanyVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_pending_list_returns_only_pending_company_entities(): void
    {
        $admin = $this->createUserWithRole('admin');
        [$pendingOwner, $pendingCompany] = $this->createCompanyOwner('pending');
        $this->createCompanyOwner('accepted');
        $this->createUserWithRole('student', [
            'is_verified_by_admin' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/CompanyUnverified');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pendingCompany->id)
            ->assertJsonPath('data.0.owner.id', $pendingOwner->id)
            ->assertJsonPath('data.0.verification_status', 'pending');
    }

    public function test_company_details_use_company_id_and_return_owner_data(): void
    {
        $admin = $this->createUserWithRole('admin');
        [$owner, $company] = $this->createCompanyOwner('pending');

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/companyDetails/{$company->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonPath('data.owner.id', $owner->id)
            ->assertJsonPath('data.verification_status', 'pending');
    }

    public function test_admin_can_accept_only_pending_company(): void
    {
        $admin = $this->createUserWithRole('admin');
        [$owner, $company] = $this->createCompanyOwner('pending');

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/companiesVerify/{$company->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonPath('data.verification_status', 'accepted');

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'is_verified_by_admin' => 'accepted',
            'deleted_at' => null,
        ]);

        $this->postJson("/api/admin/companiesVerify/{$company->id}")
            ->assertStatus(400);
    }

    public function test_rejecting_company_preserves_records_and_revokes_tokens(): void
    {
        $admin = $this->createUserWithRole('admin');
        [$owner, $company] = $this->createCompanyOwner('pending');

        $owner->createToken('company-test-token');
        $this->assertSame(1, $owner->tokens()->count());

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/companiesReject/{$company->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonPath('data.verification_status', 'rejected');

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'is_verified_by_admin' => 'rejected',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
        ]);
        $this->assertSame(0, $owner->tokens()->count());
    }

    public function test_non_pending_company_cannot_be_moved_to_another_verification_state(): void
    {
        $admin = $this->createUserWithRole('admin');
        [, $acceptedCompany] = $this->createCompanyOwner('accepted');
        [, $rejectedCompany] = $this->createCompanyOwner('rejected');

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/companiesReject/{$acceptedCompany->id}")
            ->assertStatus(400);

        $this->postJson("/api/admin/companiesVerify/{$rejectedCompany->id}")
            ->assertStatus(400);
    }

    public function test_pending_company_with_authentication_cannot_use_company_api(): void
    {
        [$owner] = $this->createCompanyOwner('pending');

        Sanctum::actingAs($owner);

        $this->getJson('/api/company/opportunities')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Your company account is not verified by admin.'
            );
    }

    public function test_rejected_company_with_authentication_cannot_use_company_api(): void
    {
        [$owner] = $this->createCompanyOwner('rejected');

        Sanctum::actingAs($owner);

        $this->getJson('/api/company/opportunities')
            ->assertForbidden();
    }

    public function test_accepted_company_can_use_company_api(): void
    {
        [$owner] = $this->createCompanyOwner('accepted');

        Sanctum::actingAs($owner);

        $this->getJson('/api/company/opportunities')
            ->assertOk();
    }

    public function test_non_admin_users_cannot_use_company_verification_endpoints(): void
    {
        $student = $this->createUserWithRole('student');
        [, $company] = $this->createCompanyOwner('pending');

        Sanctum::actingAs($student);

        $this->getJson('/api/admin/CompanyUnverified')
            ->assertForbidden();

        $this->postJson("/api/admin/companiesVerify/{$company->id}")
            ->assertForbidden();
    }

    public function test_guest_cannot_use_company_verification_endpoints(): void
    {
        $this->getJson('/api/admin/CompanyUnverified')
            ->assertUnauthorized();
    }

    private function createCompanyOwner(
        string $verificationStatus
    ): array {
        $owner = $this->createUserWithRole('company', [
            'is_verified_by_admin' => $verificationStatus,
        ]);

        $company = Company::query()->create([
            'industry' => 'Software',
            'location' => 'Remote',
            'website' => 'https://example.com',
            'documentation_file' => 'docs/company.pdf',
        ]);

        $owner->companies()->attach($company->id, [
            'role' => 'owner',
        ]);

        return [$owner, $company];
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
