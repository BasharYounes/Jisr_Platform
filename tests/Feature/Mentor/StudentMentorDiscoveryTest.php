<?php

namespace Tests\Feature\Mentor;

use App\Enums\MentorApplicationSource;
use App\Enums\MentorApplicationStatus;
use App\Models\AssessmentSession;
use App\Models\CareerPath;
use App\Models\MentorProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentMentorDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('student', 'web');
        Role::findOrCreate('company', 'web');
        Role::findOrCreate('admin', 'web');
    }

    public function test_student_sees_only_approved_mentors(): void
    {
        $student = $this->createUserWithRole('student');

        $approved = $this->createMentor([
            'status' => MentorApplicationStatus::Approved,
            'full_name' => 'Approved Mentor',
        ]);

        $this->createMentor([
            'status' => MentorApplicationStatus::Pending,
            'full_name' => 'Pending Mentor',
            'email' => 'pending@example.com',
        ]);

        $this->createMentor([
            'status' => MentorApplicationStatus::Rejected,
            'full_name' => 'Rejected Mentor',
            'email' => 'rejected@example.com',
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/student/mentors')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.mentors.0.id', $approved->id);
    }

    public function test_name_search_is_partial(): void
    {
        $student = $this->createUserWithRole('student');

        $mentor = $this->createMentor([
            'full_name' => 'Ahmad Khaled',
            'email' => 'ahmad@example.com',
        ]);

        $this->createMentor([
            'full_name' => 'Sara Ali',
            'email' => 'sara@example.com',
        ]);

        Sanctum::actingAs($student);

        $this->getJson('/api/student/mentors?search=Ahmad')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.mentors.0.id', $mentor->id);
    }

    public function test_email_search_requires_exact_email(): void
    {
        $student = $this->createUserWithRole('student');

        $mentor = $this->createMentor([
            'full_name' => 'Email Mentor',
            'email' => 'mentor.exact@example.com',
        ]);

        Sanctum::actingAs($student);

        $this->getJson(
            '/api/student/mentors?search=mentor.exact%40example.com'
        )
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.mentors.0.id', $mentor->id);

        $this->getJson(
            '/api/student/mentors?search=%40example.com'
        )
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_student_can_filter_by_specialization(): void
    {
        $student = $this->createUserWithRole('student');

        $backend = $this->createMentor([
            'specialization' => 'backend',
        ]);

        $this->createMentor([
            'specialization' => 'frontend',
            'email' => 'frontend@example.com',
        ]);

        Sanctum::actingAs($student);

        $this->getJson(
            '/api/student/mentors?specialization=backend'
        )
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.mentors.0.id', $backend->id);
    }

    public function test_default_list_prioritizes_matching_career_path_and_required_skills(): void
    {
        $student = $this->createUserWithRole('student');

        $laravel = $this->createSkill(
            'Laravel',
            'Framework',
            'laravel'
        );

        $careerPath = CareerPath::query()->create([
            'Name' => 'Backend Developer',
            'Description' => 'Backend career path.',
        ]);

        $careerPath->skills()->attach($laravel->id, [
            'RequiredLevel' => 3,
            'Weight' => 1,
            'IsCore' => true,
        ]);

        AssessmentSession::query()->create([
            'UserID' => $student->id,
            'CareerPathID' => $careerPath->CareerPathID,
            'Status' => AssessmentSession::STATUS_COMPLETED,
            'CompletedAt' => now(),
        ]);

        $matchingMentor = $this->createMentor([
            'full_name' => 'Matching Backend Mentor',
            'email' => 'matching@example.com',
            'specialization' => 'backend',
        ]);

        $matchingMentor->skills()->attach($laravel->id);

        $this->createMentor([
            'full_name' => 'Frontend Mentor',
            'email' => 'frontend.other@example.com',
            'specialization' => 'frontend',
        ]);

        Sanctum::actingAs($student);

        $response = $this->getJson('/api/student/mentors');

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.recommendation_context.career_path',
                'Backend Developer'
            )
            ->assertJsonPath(
                'data.recommendation_context.specialization',
                'backend'
            )
            ->assertJsonPath(
                'data.recommendation_context.skill_source',
                'career_path_required_skills'
            )
            ->assertJsonPath(
                'data.mentors.0.id',
                $matchingMentor->id
            )
            ->assertJsonPath(
                'data.mentors.0.recommendation.is_recommended',
                true
            )
            ->assertJsonPath(
                'data.mentors.0.recommendation.specialization_match',
                true
            )
            ->assertJsonPath(
                'data.mentors.0.recommendation.matching_skill_count',
                1
            )
            ->assertJsonPath(
                'data.mentors.0.recommendation.matching_skills.0.name',
                'Laravel'
            );
    }

    public function test_user_skills_are_used_when_student_has_no_career_path(): void
    {
        $student = $this->createUserWithRole('student');

        $react = $this->createSkill(
            'React',
            'Framework',
            'react'
        );

        $student->skills()->attach($react->id, [
            'ProficiencyLevel' => 3,
            'ConfidenceScore' => 0.90,
            'Source' => 'cv_analysis',
            'Verified' => false,
        ]);

        $mentor = $this->createMentor([
            'full_name' => 'React Mentor',
        ]);

        $mentor->skills()->attach($react->id);

        Sanctum::actingAs($student);

        $this->getJson('/api/student/mentors')
            ->assertOk()
            ->assertJsonPath(
                'data.recommendation_context.skill_source',
                'student_existing_skills'
            )
            ->assertJsonPath(
                'data.mentors.0.id',
                $mentor->id
            )
            ->assertJsonPath(
                'data.mentors.0.recommendation.matching_skill_count',
                1
            );
    }

    public function test_student_can_view_approved_mentor_contact_details_and_skills(): void
    {
        $student = $this->createUserWithRole('student');
        $skill = $this->createSkill(
            'Docker',
            'DevOps',
            'docker'
        );

        $mentor = $this->createMentor([
            'full_name' => 'Contact Mentor',
            'email' => 'contact.mentor@example.com',
            'whatsapp_number' => '+963955555555',
            'linkedin_url' => 'https://www.linkedin.com/in/contact-mentor',
            'github_or_portfolio_url' => 'https://github.com/contact-mentor',
        ]);

        $mentor->skills()->attach($skill->id);

        Sanctum::actingAs($student);

        $this->getJson(
            "/api/student/mentors/{$mentor->id}"
        )
            ->assertOk()
            ->assertJsonPath('data.email', 'contact.mentor@example.com')
            ->assertJsonPath('data.whatsapp_number', '+963955555555')
            ->assertJsonPath('data.skills.0.name', 'Docker')
            ->assertJsonMissingPath('data.cv_path')
            ->assertJsonMissingPath('data.rejection_reason')
            ->assertJsonMissingPath('data.reviewed_by');
    }

    public function test_pending_mentor_details_are_not_visible_to_student(): void
    {
        $student = $this->createUserWithRole('student');

        $mentor = $this->createMentor([
            'status' => MentorApplicationStatus::Pending,
        ]);

        Sanctum::actingAs($student);

        $this->getJson(
            "/api/student/mentors/{$mentor->id}"
        )->assertNotFound();
    }

    public function test_non_student_cannot_use_student_mentor_routes(): void
    {
        $company = $this->createUserWithRole('company');
        $company->is_verified_by_admin = 'accepted';
        $company->save();

        Sanctum::actingAs($company);

        $this->getJson('/api/student/mentors')
            ->assertForbidden();
    }

    public function test_guest_cannot_use_student_mentor_routes(): void
    {
        $this->getJson('/api/student/mentors')
            ->assertUnauthorized();
    }

    private function createMentor(
        array $overrides = []
    ): MentorProfile {
        static $counter = 0;
        $counter++;

        return MentorProfile::query()->create(array_merge([
            'user_id' => null,
            'submitted_by_user_id' => null,
            'company_id' => null,
            'source' => MentorApplicationSource::CompanyNomination,
            'status' => MentorApplicationStatus::Approved,
            'full_name' => "Mentor {$counter}",
            'email' => "mentor{$counter}@example.com",
            'whatsapp_number' => '+963944444444',
            'specialization' => 'backend',
            'professional_title' => 'Senior Engineer',
            'expertise' => 'Professional software engineering.',
            'bio' => 'Approved volunteer mentor.',
            'linkedin_url' => 'https://www.linkedin.com/in/mentor',
            'github_or_portfolio_url' => 'https://github.com/mentor',
            'cv_path' => 'mentor-applications/cvs/private.pdf',
            'mentoring_topics' => [
                'career_guidance',
                'project_review',
            ],
            'is_volunteer' => true,
            'hourly_rate' => null,
            'reviewed_at' => now(),
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
}
