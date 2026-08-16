<?php

namespace Tests\Feature\Mentor;

use App\Enums\MentorApplicationSource;
use App\Enums\MentorApplicationStatus;
use App\Models\Company;
use App\Models\MentorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MentorApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('student', 'web');
        Role::findOrCreate('supervisor', 'web');
        Role::findOrCreate('supervisor_lead', 'web');
        Role::findOrCreate('company', 'web');
        Role::findOrCreate('admin', 'web');

        Storage::fake('local');
    }

    public function test_student_can_submit_self_mentor_application_without_skill_extraction(): void
    {
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($student);

        $response = $this->post(
            '/api/mentor/application',
            $this->selfPayload(),
            ['Accept' => 'application/json']
        );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.source', 'self_application')
            ->assertJsonPath('data.full_name', $student->name)
            ->assertJsonPath('data.email', $student->email)
            ->assertJsonMissingPath('data.cv_path');

        $profile = MentorProfile::query()
            ->where('user_id', $student->id)
            ->firstOrFail();

        $this->assertSame($student->id, $profile->submitted_by_user_id);
        $this->assertNull($profile->company_id);
        $this->assertSame(
            MentorApplicationStatus::Pending,
            $profile->status
        );
        $this->assertSame(
            MentorApplicationSource::SelfApplication,
            $profile->source
        );
        $this->assertCount(0, $profile->skills);

        Storage::disk('local')->assertExists($profile->cv_path);
    }

    public function test_supervisor_can_submit_self_mentor_application(): void
    {
        $supervisor = $this->createUserWithRole('supervisor');

        Sanctum::actingAs($supervisor);

        $this->post(
            '/api/mentor/application',
            $this->selfPayload([
                'specialization' => 'devops',
                'professional_title' => 'Senior DevOps Engineer',
            ]),
            ['Accept' => 'application/json']
        )->assertCreated();

        $this->assertDatabaseHas('mentor_profiles', [
            'user_id' => $supervisor->id,
            'submitted_by_user_id' => $supervisor->id,
            'source' => 'self_application',
            'status' => 'pending',
            'specialization' => 'devops',
        ]);
    }

    public function test_supervisor_lead_is_treated_as_a_supervisor_for_self_application(): void
    {
        $lead = $this->createUserWithRole('supervisor_lead');

        Sanctum::actingAs($lead);

        $this->post(
            '/api/mentor/application',
            $this->selfPayload(),
            ['Accept' => 'application/json']
        )->assertCreated();

        $this->assertDatabaseHas('mentor_profiles', [
            'user_id' => $lead->id,
            'source' => 'self_application',
        ]);
    }

    public function test_company_cannot_use_self_application_endpoint(): void
    {
        $companyUser = $this->createVerifiedCompanyUser();

        Sanctum::actingAs($companyUser);

        $this->post(
            '/api/mentor/application',
            $this->selfPayload(),
            ['Accept' => 'application/json']
        )->assertForbidden();
    }

    public function test_user_cannot_submit_self_application_twice(): void
    {
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($student);

        $this->post(
            '/api/mentor/application',
            $this->selfPayload(),
            ['Accept' => 'application/json']
        )->assertCreated();

        $this->post(
            '/api/mentor/application',
            $this->selfPayload(),
            ['Accept' => 'application/json']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('application');

        $this->assertSame(
            1,
            MentorProfile::query()
                ->where('user_id', $student->id)
                ->count()
        );
    }

    public function test_company_can_nominate_employee_without_creating_user_or_skills(): void
    {
        $companyUser = $this->createVerifiedCompanyUser();

        $company = $companyUser->companies()->firstOrFail();

        Sanctum::actingAs($companyUser);

        $response = $this->post(
            '/api/company/mentor-nominations',
            $this->companyPayload(),
            ['Accept' => 'application/json']
        );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.source', 'company_nomination')
            ->assertJsonPath(
                'data.email',
                'employee.mentor@example.com'
            )
            ->assertJsonMissingPath('data.cv_path');

        $profile = MentorProfile::query()
            ->where('company_id', $company->id)
            ->where('email', 'employee.mentor@example.com')
            ->firstOrFail();

        $this->assertNull($profile->user_id);
        $this->assertSame(
            $companyUser->id,
            $profile->submitted_by_user_id
        );
        $this->assertCount(0, $profile->skills);

        $this->assertDatabaseMissing('users', [
            'email' => 'employee.mentor@example.com',
        ]);

        Storage::disk('local')->assertExists($profile->cv_path);
    }

    public function test_company_can_nominate_multiple_different_employees(): void
    {
        $companyUser = $this->createVerifiedCompanyUser();

        Sanctum::actingAs($companyUser);

        $this->post(
            '/api/company/mentor-nominations',
            $this->companyPayload(),
            ['Accept' => 'application/json']
        )->assertCreated();

        $this->post(
            '/api/company/mentor-nominations',
            $this->companyPayload([
                'full_name' => 'Second Employee',
                'email' => 'second.employee@example.com',
            ]),
            ['Accept' => 'application/json']
        )->assertCreated();

        $this->assertSame(
            2,
            MentorProfile::query()
                ->where('company_id', $companyUser->companies()->first()->id)
                ->count()
        );
    }

    public function test_company_cannot_nominate_same_email_twice(): void
    {
        $companyUser = $this->createVerifiedCompanyUser();

        Sanctum::actingAs($companyUser);

        $this->post(
            '/api/company/mentor-nominations',
            $this->companyPayload(),
            ['Accept' => 'application/json']
        )->assertCreated();

        $this->post(
            '/api/company/mentor-nominations',
            $this->companyPayload(),
            ['Accept' => 'application/json']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_student_cannot_nominate_employee_for_company(): void
    {
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($student);

        $this->post(
            '/api/company/mentor-nominations',
            $this->companyPayload(),
            ['Accept' => 'application/json']
        )->assertForbidden();
    }

    public function test_guest_cannot_submit_mentor_application(): void
    {
        $this->post(
            '/api/mentor/application',
            $this->selfPayload(),
            ['Accept' => 'application/json']
        )->assertUnauthorized();
    }

    public function test_application_requires_professional_evidence_and_valid_specialization(): void
    {
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($student);

        $this->post(
            '/api/mentor/application',
            [
                'specialization' => 'invalid-specialization',
                'professional_title' => '',
                'expertise' => '',
                'bio' => '',
                'linkedin_url' => '',
                'github_or_portfolio_url' => '',
                'whatsapp_number' => '',
                'mentoring_topics' => [],
            ],
            ['Accept' => 'application/json']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'specialization',
                'professional_title',
                'expertise',
                'bio',
                'linkedin_url',
                'github_or_portfolio_url',
                'whatsapp_number',
                'cv',
                'mentoring_topics',
            ]);
    }

    public function test_self_applicant_can_read_own_application_status(): void
    {
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($student);

        $this->post(
            '/api/mentor/application',
            $this->selfPayload(),
            ['Accept' => 'application/json']
        )->assertCreated();

        $this->getJson('/api/mentor/application/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.email', $student->email);
    }

    private function selfPayload(array $overrides = []): array
    {
        return array_merge([
            'specialization' => 'backend',
            'professional_title' => 'Backend Developer',
            'expertise' => 'Laravel, PHP, APIs, and databases.',
            'bio' => 'Software developer interested in mentoring students.',
            'linkedin_url' => 'https://www.linkedin.com/in/example',
            'github_or_portfolio_url' => 'https://github.com/example',
            'whatsapp_number' => '+963999999999',
            'cv' => UploadedFile::fake()->create(
                'mentor-cv.pdf',
                100,
                'application/pdf'
            ),
            'mentoring_topics' => [
                'career_guidance',
                'project_review',
            ],
        ], $overrides);
    }

    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Employee Mentor',
            'email' => 'employee.mentor@example.com',
            'specialization' => 'frontend',
            'professional_title' => 'Senior Frontend Engineer',
            'expertise' => 'React, JavaScript, frontend architecture.',
            'bio' => 'Senior employee nominated to mentor students.',
            'linkedin_url' => 'https://www.linkedin.com/in/employee-mentor',
            'github_or_portfolio_url' => 'https://github.com/employee-mentor',
            'whatsapp_number' => '+963988888888',
            'cv' => UploadedFile::fake()->create(
                'employee-cv.pdf',
                100,
                'application/pdf'
            ),
            'mentoring_topics' => [
                'career_guidance',
                'interview_preparation',
            ],
        ], $overrides);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function createVerifiedCompanyUser(): User
    {
        $user = $this->createUserWithRole('company');

        $user->is_verified_by_admin = 'accepted';
        $user->save();

        $company = Company::query()->create([
            'industry' => 'Software',
            'location' => 'Damascus',
        ]);

        $company->users()->attach($user->id, [
            'role' => 'owner',
        ]);

        return $user;
    }
}
