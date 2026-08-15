<?php

namespace Tests\Feature\Mentor;

use App\Enums\MentorApplicationSource;
use App\Enums\MentorApplicationStatus;
use App\Exceptions\AIProviderException;
use App\Models\Company;
use App\Models\MentorProfile;
use App\Models\Skill;
use App\Models\User;
use App\Services\AI\SkillExtractionService;
use App\Services\CV\CVTextExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminMentorApplicationReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('student', 'web');
        Role::findOrCreate('supervisor', 'web');
        Role::findOrCreate('supervisor_lead', 'web');
        Role::findOrCreate('company', 'web');

        Storage::fake('local');
    }

    public function test_admin_can_list_and_filter_mentor_applications(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student');

        $pending = $this->createSelfApplication($student);

        $companyUser = $this->createVerifiedCompanyUser();
        $company = $companyUser->companies()->firstOrFail();

        $approved = $this->createCompanyNomination(
            $companyUser,
            $company,
            [
                'status' => MentorApplicationStatus::Approved,
                'full_name' => 'Approved Employee',
                'email' => 'approved.employee@example.com',
            ]
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/admin/mentor-applications?status=pending'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.applications.0.id',
                $pending->id
            )
            ->assertJsonPath(
                'data.applications.0.status',
                'pending'
            );

        $this->getJson(
            '/api/admin/mentor-applications'
            .'?search=Approved%20Employee'
        )
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath(
                'data.applications.0.id',
                $approved->id
            );
    }

    public function test_non_admin_cannot_access_admin_mentor_review_routes(): void
    {
        $student = $this->createUserWithRole('student');

        Sanctum::actingAs($student);

        $this->getJson('/api/admin/mentor-applications')
            ->assertForbidden();
    }

    public function test_admin_can_view_application_details_without_exposing_cv_path(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student');

        $application = $this->createSelfApplication($student);

        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/admin/mentor-applications/{$application->id}"
        )
            ->assertOk()
            ->assertJsonPath('data.id', $application->id)
            ->assertJsonPath('data.cv_available', true)
            ->assertJsonPath(
                'data.cv_download_endpoint',
                "/api/admin/mentor-applications/{$application->id}/cv"
            )
            ->assertJsonMissingPath('data.cv_path');
    }

    public function test_admin_can_download_private_mentor_cv(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student');

        $application = $this->createSelfApplication($student);

        Sanctum::actingAs($admin);

        $this->get(
            "/api/admin/mentor-applications/{$application->id}/cv"
        )->assertOk();
    }

    public function test_reject_requires_reason_and_never_extracts_skills(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student');

        $application = $this->createSelfApplication($student);

        $this->mock(
            SkillExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive('extractSkills');
            }
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/mentor-applications/{$application->id}/reject",
            []
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->patchJson(
            "/api/admin/mentor-applications/{$application->id}/reject",
            ['reason' => 'Professional evidence is insufficient.']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath(
                'data.rejection_reason',
                'Professional evidence is insufficient.'
            );

        $application->refresh();

        $this->assertSame(
            MentorApplicationStatus::Rejected,
            $application->status
        );
        $this->assertSame($admin->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);
        $this->assertCount(0, $application->skills);
    }

    public function test_student_approval_reuses_existing_user_skills_without_gemini(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student');
        $skill = $this->createSkill(
            'Laravel',
            'Framework',
            'laravel'
        );

        $student->skills()->attach($skill->id, [
            'ProficiencyLevel' => 3,
            'ConfidenceScore' => 0.90,
            'Source' => 'cv_analysis',
            'Verified' => false,
        ]);

        $application = $this->createSelfApplication($student);

        $this->mock(
            CVTextExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive('extractFromPath');
            }
        );

        $this->mock(
            SkillExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive('extractSkills');
            }
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/mentor-applications/{$application->id}/approve"
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.skills.0.id', $skill->id);

        $this->assertDatabaseHas('mentor_profile_skills', [
            'mentor_profile_id' => $application->id,
            'skill_id' => $skill->id,
        ]);
    }

    public function test_student_without_existing_skills_is_not_reanalyzed_and_stays_pending(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student');
        $application = $this->createSelfApplication($student);

        $this->mock(
            CVTextExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive('extractFromPath');
            }
        );

        $this->mock(
            SkillExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive('extractSkills');
            }
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/mentor-applications/{$application->id}/approve"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('skills');

        $this->assertSame(
            MentorApplicationStatus::Pending,
            $application->fresh()->status
        );
    }

    public function test_company_nominee_skills_are_extracted_only_when_admin_approves(): void
    {
        $admin = $this->createUserWithRole('admin');
        $companyUser = $this->createVerifiedCompanyUser();
        $company = $companyUser->companies()->firstOrFail();

        $application = $this->createCompanyNomination(
            $companyUser,
            $company
        );

        $skill = $this->createSkill(
            'React',
            'Framework',
            'react'
        );

        $this->mock(
            CVTextExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('extractFromPath')
                    ->once()
                    ->andReturn(
                        'Senior frontend engineer with React experience.'
                    );
            }
        );

        $this->mock(
            SkillExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('extractSkills')
                    ->once()
                    ->with(
                        'Senior frontend engineer with React experience.',
                        'Senior Frontend Engineer'
                    )
                    ->andReturn([
                        'skills' => [
                            [
                                'skill_name' => 'React',
                                'evidence' => 'React experience',
                                'initial_level' => 4,
                                'confidence' => 0.97,
                            ],
                        ],
                    ]);
            }
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/mentor-applications/{$application->id}/approve"
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.skills.0.id', $skill->id);

        $application->refresh();

        $this->assertSame(
            MentorApplicationStatus::Approved,
            $application->status
        );
        $this->assertSame($admin->id, $application->reviewed_by);

        $this->assertDatabaseHas('mentor_profile_skills', [
            'mentor_profile_id' => $application->id,
            'skill_id' => $skill->id,
        ]);
    }

    public function test_supervisor_with_existing_skills_reuses_them_without_gemini(): void
    {
        $admin = $this->createUserWithRole('admin');
        $supervisor = $this->createUserWithRole('supervisor');
        $skill = $this->createSkill(
            'Docker',
            'DevOps',
            'docker'
        );

        $supervisor->skills()->attach($skill->id, [
            'ProficiencyLevel' => 4,
            'ConfidenceScore' => 0.95,
            'Source' => 'manual',
            'Verified' => true,
        ]);

        $application = $this->createSelfApplication(
            $supervisor,
            [
                'specialization' => 'devops',
                'professional_title' => 'DevOps Engineer',
            ]
        );

        $this->mock(
            SkillExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive('extractSkills');
            }
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/mentor-applications/{$application->id}/approve"
        )
            ->assertOk()
            ->assertJsonPath('data.skills.0.id', $skill->id);
    }

    public function test_supervisor_without_existing_skills_is_extracted_on_approval(): void
    {
        $admin = $this->createUserWithRole('admin');
        $supervisor = $this->createUserWithRole('supervisor');
        $application = $this->createSelfApplication(
            $supervisor,
            [
                'specialization' => 'devops',
                'professional_title' => 'Senior DevOps Engineer',
            ]
        );

        $skill = $this->createSkill(
            'Docker',
            'DevOps',
            'docker'
        );

        $this->mock(
            CVTextExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('extractFromPath')
                    ->once()
                    ->andReturn('Managed Docker-based deployments.');
            }
        );

        $this->mock(
            SkillExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('extractSkills')
                    ->once()
                    ->with(
                        'Managed Docker-based deployments.',
                        'Senior DevOps Engineer'
                    )
                    ->andReturn([
                        'skills' => [
                            [
                                'skill_name' => 'Docker',
                                'evidence' => 'Docker-based deployments',
                                'initial_level' => 4,
                                'confidence' => 0.96,
                            ],
                        ],
                    ]);
            }
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/mentor-applications/{$application->id}/approve"
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.skills.0.id', $skill->id);
    }

    public function test_ai_failure_keeps_application_pending_and_does_not_store_skills(): void
    {
        $admin = $this->createUserWithRole('admin');
        $companyUser = $this->createVerifiedCompanyUser();
        $company = $companyUser->companies()->firstOrFail();

        $application = $this->createCompanyNomination(
            $companyUser,
            $company
        );

        $this->mock(
            CVTextExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('extractFromPath')
                    ->once()
                    ->andReturn('Valid CV content.');
            }
        );

        $this->mock(
            SkillExtractionService::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('extractSkills')
                    ->once()
                    ->andThrow(
                        new AIProviderException(
                            'Gemini unavailable.'
                        )
                    );
            }
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/mentor-applications/{$application->id}/approve"
        )
            ->assertStatus(502)
            ->assertJsonPath('success', false);

        $application->refresh();

        $this->assertSame(
            MentorApplicationStatus::Pending,
            $application->status
        );
        $this->assertNull($application->reviewed_by);
        $this->assertNull($application->reviewed_at);
        $this->assertCount(0, $application->skills);
    }

    public function test_reviewed_application_cannot_be_reviewed_again(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->createUserWithRole('student');
        $application = $this->createSelfApplication(
            $student,
            [
                'status' => MentorApplicationStatus::Rejected,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => 'Already reviewed.',
            ]
        );

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/admin/mentor-applications/{$application->id}/reject",
            ['reason' => 'Second review attempt.']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(
            MentorApplicationStatus::Rejected,
            $application->fresh()->status
        );
    }

    private function createSelfApplication(
        User $user,
        array $overrides = []
    ): MentorProfile {
        $path = 'mentor-applications/cvs/self-'.$user->id.'.pdf';
        Storage::disk('local')->put($path, 'fake-private-cv');

        return MentorProfile::query()->create(array_merge([
            'user_id' => $user->id,
            'submitted_by_user_id' => $user->id,
            'company_id' => null,
            'source' => MentorApplicationSource::SelfApplication,
            'status' => MentorApplicationStatus::Pending,
            'full_name' => $user->name,
            'email' => $user->email,
            'whatsapp_number' => '+963999999999',
            'specialization' => 'backend',
            'professional_title' => 'Backend Developer',
            'expertise' => 'Professional software development.',
            'bio' => 'Volunteer mentor application.',
            'linkedin_url' => 'https://www.linkedin.com/in/example',
            'github_or_portfolio_url' => 'https://github.com/example',
            'cv_path' => $path,
            'mentoring_topics' => [
                'career_guidance',
                'project_review',
            ],
            'is_volunteer' => true,
            'hourly_rate' => null,
        ], $overrides));
    }

    private function createCompanyNomination(
        User $companyUser,
        Company $company,
        array $overrides = []
    ): MentorProfile {
        $path = 'mentor-applications/cvs/company-'
            .$company->id
            .'-'
            .$companyUser->id
            .'-'
            .uniqid('', true)
            .'.pdf';

        Storage::disk('local')->put($path, 'fake-private-cv');

        return MentorProfile::query()->create(array_merge([
            'user_id' => null,
            'submitted_by_user_id' => $companyUser->id,
            'company_id' => $company->id,
            'source' => MentorApplicationSource::CompanyNomination,
            'status' => MentorApplicationStatus::Pending,
            'full_name' => 'Employee Mentor',
            'email' => 'employee.'.uniqid().'@example.com',
            'whatsapp_number' => '+963988888888',
            'specialization' => 'frontend',
            'professional_title' => 'Senior Frontend Engineer',
            'expertise' => 'Frontend architecture and mentoring.',
            'bio' => 'Company nominated employee.',
            'linkedin_url' => 'https://www.linkedin.com/in/employee',
            'github_or_portfolio_url' => 'https://github.com/employee',
            'cv_path' => $path,
            'mentoring_topics' => [
                'career_guidance',
                'interview_preparation',
            ],
            'is_volunteer' => true,
            'hourly_rate' => null,
        ], $overrides));
    }

    private function createSkill(
        string $name,
        string $category,
        string $normalizedName
    ): Skill {
        return Skill::query()->create([
            'name' => $name,
            'category' => $category,
            'normalized_name' => $normalizedName,
        ]);
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
            'website' => 'https://example.com',
        ]);

        $company->users()->attach($user->id, [
            'role' => 'owner',
        ]);

        return $user;
    }
}
