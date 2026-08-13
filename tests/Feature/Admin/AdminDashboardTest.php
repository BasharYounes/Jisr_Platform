<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_view_dashboard_statistics(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->createUserWithRole('student');
        $this->createUserWithRole('student', ['is_active' => false]);

        $firstCompanyUser = $this->createUserWithRole('company');
        $secondCompanyUser = $this->createUserWithRole('company');

        $supervisor = $this->createUserWithRole('supervisor');
        $lead = $this->createUserWithRoles([
            'supervisor',
            'supervisor_lead',
        ]);

        $firstCompany = $this->createCompany();
        $secondCompany = $this->createCompany();

        $this->createOpportunity($firstCompany, [
            'status' => 'published',
        ]);
        $this->createOpportunity($secondCompany, [
            'status' => 'published',
        ]);
        $this->createOpportunity($firstCompany, [
            'status' => 'draft',
        ]);
        $this->createOpportunity($secondCompany, [
            'status' => 'closed',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.published_opportunities', 2)
            ->assertJsonPath('data.active_users', 6)
            ->assertJsonPath('data.blocked_users', 1)
            ->assertJsonPath('data.total_companies', 2)
            ->assertJsonPath('data.total_supervisors', 2);

        $this->assertTrue($firstCompanyUser->hasRole('company'));
        $this->assertTrue($secondCompanyUser->hasRole('company'));
        $this->assertTrue($supervisor->hasRole('supervisor'));
        $this->assertTrue($lead->hasRole('supervisor_lead'));
    }

    public function test_dashboard_counts_company_entities_not_company_role_accounts(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->createUserWithRole('company');
        $this->createUserWithRole('company');
        $this->createUserWithRole('company');

        $this->createCompany();
        $this->createCompany();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_companies', 2);
    }

    public function test_supervisor_lead_is_counted_once_as_a_supervisor(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->createUserWithRole('supervisor');
        $this->createUserWithRoles([
            'supervisor',
            'supervisor_lead',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_supervisors', 2);
    }

    public function test_dashboard_counts_only_published_opportunities(): void
    {
        $admin = $this->createUserWithRole('admin');
        $company = $this->createCompany();

        $this->createOpportunity($company, [
            'status' => 'published',
        ]);
        $this->createOpportunity($company, [
            'status' => 'draft',
        ]);
        $this->createOpportunity($company, [
            'status' => 'closed',
        ]);
        $this->createOpportunity($company, [
            'status' => 'cancelled',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.published_opportunities', 1);
    }

    public function test_dashboard_separates_active_and_blocked_users(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->createUserWithRole('student');
        $this->createUserWithRole('student');
        $this->createUserWithRole('company', [
            'is_active' => false,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.active_users', 3)
            ->assertJsonPath('data.blocked_users', 1);
    }

    public function test_student_cannot_view_admin_dashboard(): void
    {
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($student);

        $this->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    public function test_company_cannot_view_admin_dashboard(): void
    {
        $company = $this->createUserWithRole('company');

        Sanctum::actingAs($company);

        $this->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    public function test_guest_cannot_view_admin_dashboard(): void
    {
        $this->getJson('/api/admin/dashboard')
            ->assertUnauthorized();
    }

    private function createCompany(array $attributes = []): Company
    {
        static $sequence = 1;

        $company = Company::query()->create([
            'industry' => 'Software',
            'location' => 'Remote',
            'website' => "https://company{$sequence}.example.com",
            ...$attributes,
        ]);

        $sequence++;

        return $company;
    }

    private function createOpportunity(
        Company $company,
        array $attributes = []
    ): Opportunity {
        static $sequence = 1;

        $opportunity = Opportunity::query()->create([
            'company_id' => $company->id,
            'title' => 'Dashboard Opportunity '.$sequence,
            'description' => 'Dashboard opportunity description '.$sequence,
            'type' => 'job',
            'location' => 'Remote',
            'salary_min' => 1000,
            'salary_max' => 2000,
            'status' => 'published',
            'deadline' => now()->addMonth(),
            'posted_at' => now(),
            ...$attributes,
        ]);

        $sequence++;

        return $opportunity;
    }

    private function createUserWithRole(
        string $role,
        array $attributes = []
    ): User {
        return $this->createUserWithRoles([$role], $attributes);
    }

    private function createUserWithRoles(
        array $roles,
        array $attributes = []
    ): User {
        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }

        $user = User::factory()->create($attributes);
        $user->assignRole($roles);

        return $user;
    }
}
