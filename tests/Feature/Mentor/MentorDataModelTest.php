<?php

namespace Tests\Feature\Mentor;

use App\Enums\MentorApplicationSource;
use App\Enums\MentorApplicationStatus;
use App\Enums\MentoringTopic;
use App\Models\Company;
use App\Models\MentorProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentorDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_application_relations_and_casts_are_configured(): void
    {
        $student = User::factory()->create();
        $admin = User::factory()->create();

        $profile = MentorProfile::query()->create([
            'user_id' => $student->id,
            'submitted_by_user_id' => $student->id,
            'source' => MentorApplicationSource::SelfApplication,
            'status' => MentorApplicationStatus::Pending,
            'full_name' => $student->name,
            'email' => $student->email,
            'whatsapp_number' => '+963999999999',
            'specialization' => 'backend',
            'professional_title' => 'Backend Developer',
            'expertise' => 'Laravel and REST APIs',
            'bio' => 'Backend developer interested in mentoring students.',
            'linkedin_url' => 'https://www.linkedin.com/in/example',
            'github_or_portfolio_url' => 'https://github.com/example',
            'cv_path' => 'mentor-cvs/example.pdf',
            'mentoring_topics' => [
                MentoringTopic::CareerGuidance->value,
                MentoringTopic::ProjectReview->value,
            ],
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'is_volunteer' => true,
        ]);

        $profile->refresh();

        $this->assertTrue($profile->user->is($student));
        $this->assertTrue($profile->submittedBy->is($student));
        $this->assertTrue($profile->reviewedBy->is($admin));

        $this->assertSame(
            MentorApplicationSource::SelfApplication,
            $profile->source
        );

        $this->assertSame(
            MentorApplicationStatus::Pending,
            $profile->status
        );

        $this->assertSame([
            MentoringTopic::CareerGuidance->value,
            MentoringTopic::ProjectReview->value,
        ], $profile->mentoring_topics);

        $this->assertTrue($profile->is_volunteer);
        $this->assertNotNull($profile->reviewed_at);
    }

    public function test_company_nomination_can_exist_without_a_mentor_user_account(): void
    {
        $companyRepresentative = User::factory()->create();

        $company = Company::query()->create([
            'industry' => 'Software',
            'location' => 'Damascus',
        ]);

        $company->users()->attach($companyRepresentative->id, [
            'role' => 'representative',
        ]);

        $profile = MentorProfile::query()->create([
            'user_id' => null,
            'submitted_by_user_id' => $companyRepresentative->id,
            'company_id' => $company->id,
            'source' => MentorApplicationSource::CompanyNomination,
            'status' => MentorApplicationStatus::Pending,
            'full_name' => 'Ahmad Ali',
            'email' => 'ahmad.mentor@example.com',
            'whatsapp_number' => '+963988888888',
            'specialization' => 'devops',
            'professional_title' => 'Senior DevOps Engineer',
            'expertise' => 'Docker, CI/CD, and cloud infrastructure',
            'bio' => 'Senior engineer nominated by the company.',
            'linkedin_url' => 'https://www.linkedin.com/in/ahmad-example',
            'github_or_portfolio_url' => 'https://github.com/ahmad-example',
            'cv_path' => 'mentor-cvs/ahmad.pdf',
            'mentoring_topics' => [
                MentoringTopic::CareerGuidance->value,
                MentoringTopic::InterviewPreparation->value,
            ],
            'is_volunteer' => true,
        ]);

        $profile->refresh();

        $this->assertNull($profile->user);
        $this->assertTrue(
            $profile->submittedBy->is($companyRepresentative)
        );
        $this->assertTrue($profile->company->is($company));
        $this->assertSame(
            MentorApplicationSource::CompanyNomination,
            $profile->source
        );
    }

    public function test_mentor_profile_can_be_linked_to_existing_skills(): void
    {
        $student = User::factory()->create();

        $profile = MentorProfile::query()->create([
            'user_id' => $student->id,
            'submitted_by_user_id' => $student->id,
            'source' => MentorApplicationSource::SelfApplication,
            'full_name' => $student->name,
            'email' => $student->email,
            'expertise' => 'Backend development',
            'is_volunteer' => true,
        ]);

        $laravel = Skill::query()->create([
            'name' => 'Laravel',
            'category' => 'Framework',
            'normalized_name' => 'laravel',
        ]);

        $php = Skill::query()->create([
            'name' => 'PHP',
            'category' => 'Programming Language',
            'normalized_name' => 'php',
        ]);

        $profile->skills()->attach([
            $laravel->id,
            $php->id,
        ]);

        $this->assertCount(2, $profile->fresh()->skills);

        $this->assertDatabaseHas('mentor_profile_skills', [
            'mentor_profile_id' => $profile->id,
            'skill_id' => $laravel->id,
        ]);

        $this->assertDatabaseHas('mentor_profile_skills', [
            'mentor_profile_id' => $profile->id,
            'skill_id' => $php->id,
        ]);
    }

    public function test_new_profiles_default_to_pending(): void
    {
        $student = User::factory()->create();

        $profile = MentorProfile::query()->create([
            'user_id' => $student->id,
            'submitted_by_user_id' => $student->id,
            'source' => MentorApplicationSource::SelfApplication,
            'full_name' => $student->name,
            'email' => $student->email,
            'expertise' => 'Backend development',
            'is_volunteer' => true,
        ]);

        $profile->refresh();

        $this->assertSame(
            MentorApplicationStatus::Pending,
            $profile->status
        );
    }

    public function test_user_cannot_have_more_than_one_mentor_profile(): void
    {
        $student = User::factory()->create();

        MentorProfile::query()->create([
            'user_id' => $student->id,
            'submitted_by_user_id' => $student->id,
            'source' => MentorApplicationSource::SelfApplication,
            'full_name' => $student->name,
            'email' => $student->email,
            'expertise' => 'Backend development',
            'is_volunteer' => true,
        ]);

        $this->expectException(QueryException::class);

        MentorProfile::query()->create([
            'user_id' => $student->id,
            'submitted_by_user_id' => $student->id,
            'source' => MentorApplicationSource::SelfApplication,
            'full_name' => $student->name,
            'email' => 'second-'.$student->email,
            'expertise' => 'Frontend development',
            'is_volunteer' => true,
        ]);
    }

    public function test_same_company_cannot_nominate_same_email_twice(): void
    {
        $companyRepresentative = User::factory()->create();

        $company = Company::query()->create([
            'industry' => 'Software',
        ]);

        $baseData = [
            'user_id' => null,
            'submitted_by_user_id' => $companyRepresentative->id,
            'company_id' => $company->id,
            'source' => MentorApplicationSource::CompanyNomination,
            'full_name' => 'Repeated Mentor',
            'email' => 'repeated.mentor@example.com',
            'expertise' => 'AI engineering',
            'is_volunteer' => true,
        ];

        MentorProfile::query()->create($baseData);

        $this->expectException(QueryException::class);

        MentorProfile::query()->create($baseData);
    }
}
