<?php

namespace Tests\Feature\Mentor;

use App\Enums\MentorApplicationSource;
use App\Enums\MentorApplicationStatus;
use App\Models\Company;
use App\Models\MentorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompanyMentorNominationListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('company', 'web');
        Role::findOrCreate('student', 'web');
        Role::findOrCreate('admin', 'web');
    }

    public function test_company_can_list_only_its_own_mentor_nominations(): void
    {
        [$companyUser, $company] = $this->createVerifiedCompanyAccount();
        [$otherUser, $otherCompany] = $this->createVerifiedCompanyAccount();

        $ownNomination = $this->createNomination(
            $companyUser,
            $company,
            [
                'full_name' => 'Own Mentor',
                'email' => 'own.mentor@example.com',
            ]
        );

        $this->createNomination(
            $otherUser,
            $otherCompany,
            [
                'full_name' => 'Other Company Mentor',
                'email' => 'other.mentor@example.com',
            ]
        );

        Sanctum::actingAs($companyUser);

        $this->getJson('/api/company/mentor-nominations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.nominations.0.id',
                $ownNomination->id
            )
            ->assertJsonMissing([
                'Other Company Mentor',
                'other.mentor@example.com',
            ]);
    }

    public function test_company_can_filter_its_nominations_by_status(): void
    {
        [$companyUser, $company] = $this->createVerifiedCompanyAccount();

        $pending = $this->createNomination(
            $companyUser,
            $company,
            [
                'status' => MentorApplicationStatus::Pending,
                'email' => 'pending.mentor@example.com',
            ]
        );

        $this->createNomination(
            $companyUser,
            $company,
            [
                'status' => MentorApplicationStatus::Approved,
                'email' => 'approved.mentor@example.com',
            ]
        );

        Sanctum::actingAs($companyUser);

        $this->getJson(
            '/api/company/mentor-nominations?status=pending'
        )
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.nominations.0.id',
                $pending->id
            )
            ->assertJsonPath(
                'data.nominations.0.status',
                'pending'
            );
    }

    public function test_company_can_see_rejection_reason_for_its_rejected_nomination(): void
    {
        [$companyUser, $company] = $this->createVerifiedCompanyAccount();

        $nomination = $this->createNomination(
            $companyUser,
            $company,
            [
                'status' => MentorApplicationStatus::Rejected,
                'rejection_reason' => 'CV evidence is insufficient.',
                'reviewed_at' => now(),
            ]
        );

        Sanctum::actingAs($companyUser);

        $this->getJson(
            '/api/company/mentor-nominations?status=rejected'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.nominations.0.id',
                $nomination->id
            )
            ->assertJsonPath(
                'data.nominations.0.rejection_reason',
                'CV evidence is insufficient.'
            )
            ->assertJsonMissingPath(
                'data.nominations.0.cv_path'
            )
            ->assertJsonMissingPath(
                'data.nominations.0.reviewed_by'
            );
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        [$companyUser] = $this->createVerifiedCompanyAccount();

        Sanctum::actingAs($companyUser);

        $this->getJson(
            '/api/company/mentor-nominations?status=invalid'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_pagination_is_applied_to_company_nomination_listing(): void
    {
        [$companyUser, $company] = $this->createVerifiedCompanyAccount();

        $this->createNomination(
            $companyUser,
            $company,
            ['email' => 'first.mentor@example.com']
        );

        $this->createNomination(
            $companyUser,
            $company,
            ['email' => 'second.mentor@example.com']
        );

        Sanctum::actingAs($companyUser);

        $this->getJson(
            '/api/company/mentor-nominations?per_page=1&page=1'
        )
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonCount(1, 'data.nominations');
    }

    public function test_student_cannot_list_company_mentor_nominations(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');

        Sanctum::actingAs($student);

        $this->getJson('/api/company/mentor-nominations')
            ->assertForbidden();
    }

    public function test_guest_cannot_list_company_mentor_nominations(): void
    {
        $this->getJson('/api/company/mentor-nominations')
            ->assertUnauthorized();
    }

    private function createVerifiedCompanyAccount(): array
    {
        $user = User::factory()->create([
            'is_verified_by_admin' => 'accepted',
        ]);

        $user->assignRole('company');

        $company = Company::query()->create([
            'industry' => 'Software',
            'location' => 'Damascus',
            'website' => 'https://example.com',
        ]);

        $company->users()->attach($user->id, [
            'role' => 'owner',
        ]);

        return [$user, $company];
    }

    private function createNomination(
        User $submittedBy,
        Company $company,
        array $overrides = []
    ): MentorProfile {
        static $counter = 0;
        $counter++;

        return MentorProfile::query()->create(array_merge([
            'user_id' => null,
            'submitted_by_user_id' => $submittedBy->id,
            'company_id' => $company->id,
            'source' => MentorApplicationSource::CompanyNomination,
            'status' => MentorApplicationStatus::Pending,
            'full_name' => "Employee Mentor {$counter}",
            'email' => "employee{$counter}@example.com",
            'whatsapp_number' => '+963988888888',
            'specialization' => 'backend',
            'professional_title' => 'Senior Backend Engineer',
            'expertise' => 'Backend engineering and mentoring.',
            'bio' => 'Company nominated mentor.',
            'linkedin_url' => 'https://www.linkedin.com/in/employee',
            'github_or_portfolio_url' => 'https://github.com/employee',
            'cv_path' => 'mentor-applications/cvs/private.pdf',
            'mentoring_topics' => [
                'career_guidance',
                'project_review',
            ],
            'is_volunteer' => true,
            'hourly_rate' => null,
        ], $overrides));
    }
}
