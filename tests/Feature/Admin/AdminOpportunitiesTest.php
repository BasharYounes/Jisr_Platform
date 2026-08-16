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

class AdminOpportunitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_list_opportunities_from_all_companies(): void
    {
        $admin = $this->createUserWithRole('admin');
        $firstCompany = $this->createCompany();
        $secondCompany = $this->createCompany();

        $firstOpportunity = $this->createOpportunity($firstCompany, [
            'title' => 'Backend Laravel Internship',
        ]);
        $secondOpportunity = $this->createOpportunity($secondCompany, [
            'title' => 'Frontend React Job',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/opportunities');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.pagination.total', 2);

        $ids = collect($response->json('data.opportunities'))
            ->pluck('id_Resource');

        $this->assertTrue($ids->contains($firstOpportunity->id));
        $this->assertTrue($ids->contains($secondOpportunity->id));
    }

    public function test_admin_can_filter_opportunities_by_status(): void
    {
        $admin = $this->createUserWithRole('admin');
        $company = $this->createCompany();

        $published = $this->createOpportunity($company, [
            'status' => 'published',
        ]);
        $this->createOpportunity($company, [
            'status' => 'draft',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/opportunities?status=published'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.opportunities.0.id_Resource',
                $published->id
            )
            ->assertJsonPath(
                'data.opportunities.0.status',
                'published'
            );
    }

    public function test_admin_can_filter_opportunities_by_type(): void
    {
        $admin = $this->createUserWithRole('admin');
        $company = $this->createCompany();

        $internship = $this->createOpportunity($company, [
            'type' => 'internship',
        ]);
        $this->createOpportunity($company, [
            'type' => 'job',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/opportunities?type=internship'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.opportunities.0.id_Resource',
                $internship->id
            )
            ->assertJsonPath(
                'data.opportunities.0.type',
                'internship'
            );
    }

    public function test_admin_can_search_opportunities(): void
    {
        $admin = $this->createUserWithRole('admin');
        $company = $this->createCompany();

        $target = $this->createOpportunity($company, [
            'title' => 'Backend Engineer',
            'description' => 'Work with Laravel and REST APIs.',
            'location' => 'Remote',
        ]);
        $this->createOpportunity($company, [
            'title' => 'Frontend Engineer',
            'description' => 'Work with React.',
            'location' => 'Damascus',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/opportunities?search=Laravel'
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.opportunities.0.id_Resource',
                $target->id
            );
    }

    public function test_admin_opportunities_are_paginated(): void
    {
        $admin = $this->createUserWithRole('admin');
        $company = $this->createCompany();

        $this->createOpportunity($company);
        $this->createOpportunity($company);
        $this->createOpportunity($company);

        Sanctum::actingAs($admin);

        $response = $this->getJson(
            '/api/admin/opportunities?page=1&per_page=2'
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.opportunities')
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        $admin = $this->createUserWithRole('admin');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/opportunities?status=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_invalid_type_filter_is_rejected(): void
    {
        $admin = $this->createUserWithRole('admin');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/opportunities?type=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_per_page_cannot_exceed_one_hundred(): void
    {
        $admin = $this->createUserWithRole('admin');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/opportunities?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_company_cannot_use_admin_opportunities_endpoint(): void
    {
        $companyUser = $this->createUserWithRole('company');

        Sanctum::actingAs($companyUser);

        $this->getJson('/api/admin/opportunities')
            ->assertForbidden();
    }

    public function test_student_cannot_use_admin_opportunities_endpoint(): void
    {
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($student);

        $this->getJson('/api/admin/opportunities')
            ->assertForbidden();
    }

    public function test_guest_cannot_use_admin_opportunities_endpoint(): void
    {
        $this->getJson('/api/admin/opportunities')
            ->assertUnauthorized();
    }

    private function createCompany(array $attributes = []): Company
    {
        return Company::query()->create([
            'industry' => 'Software',
            'location' => 'Remote',
            'website' => 'https://example.com',
            ...$attributes,
        ]);
    }

    private function createOpportunity(
        Company $company,
        array $attributes = []
    ): Opportunity {
        static $sequence = 1;

        $opportunity = Opportunity::query()->create([
            'company_id' => $company->id,
            'title' => 'Opportunity '.$sequence,
            'description' => 'Opportunity description '.$sequence,
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

    private function createUserWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
