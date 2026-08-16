<?php

namespace Tests\Feature\Complaints;

use App\Models\CompanyTaskAssignment;
use App\Models\OpportunityInterview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ComplaintSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_student_can_complain_about_approved_external_mentor(): void
    {
        $student = $this->createUserWithRole('student');
        $mentorProfileId = $this->createMentorProfile();

        Sanctum::actingAs($student);

        $response = $this->postJson('/api/complaints', [
            'context_type' => 'mentor_profile',
            'context_id' => $mentorProfileId,
            'reason' => 'The mentor behaved unprofessionally during our external meeting.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.target_type', 'mentor')
            ->assertJsonPath('data.reported_mentor.id', $mentorProfileId)
            ->assertJsonPath('data.reported_user', null)
            ->assertJsonPath('data.context.type', 'mentor_profile');

        $this->assertDatabaseHas('complaints', [
            'complainant_user_id' => $student->id,
            'reported_user_id' => null,
            'reported_mentor_profile_id' => $mentorProfileId,
            'context_type' => 'mentor_profile',
            'context_id' => $mentorProfileId,
            'status' => 'pending',
        ]);
    }

    public function test_student_can_complain_about_internal_mentor_as_mentor_profile(): void
    {
        $student = $this->createUserWithRole('student');
        $mentorUser = $this->createUserWithRole('mentor');
        $mentorProfileId = $this->createMentorProfile($mentorUser->id);

        Sanctum::actingAs($student);

        $response = $this->postJson('/api/complaints', [
            'context_type' => 'mentor_profile',
            'context_id' => $mentorProfileId,
            'reason' => 'The mentor did not respect the agreed mentoring boundaries.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.target_type', 'mentor')
            ->assertJsonPath('data.reported_mentor.id', $mentorProfileId)
            ->assertJsonPath(
                'data.reported_mentor.internal_user_id',
                $mentorUser->id
            )
            ->assertJsonPath('data.reported_user', null);
    }

    public function test_student_cannot_complain_about_unapproved_mentor(): void
    {
        $student = $this->createUserWithRole('student');
        $mentorProfileId = $this->createMentorProfile(status: 'pending');

        Sanctum::actingAs($student);

        $this->postJson('/api/complaints', [
            'context_type' => 'mentor_profile',
            'context_id' => $mentorProfileId,
            'reason' => 'A mentor that is not approved must not be reportable through discovery.',
        ])->assertNotFound();
    }

    public function test_admin_can_filter_and_read_mentor_target_complaints(): void
    {
        $student = $this->createUserWithRole('student');
        $admin = $this->createUserWithRole('admin');
        $mentorProfileId = $this->createMentorProfile();

        Sanctum::actingAs($student);

        $this->postJson('/api/complaints', [
            'context_type' => 'mentor_profile',
            'context_id' => $mentorProfileId,
            'reason' => 'This mentor complaint should be visible in the admin target filter.',
        ])->assertCreated();

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/admin/complaints?target_type=mentor&context_type=mentor_profile'
        )
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.complaints.0.target_type', 'mentor')
            ->assertJsonPath(
                'data.complaints.0.reported_mentor.id',
                $mentorProfileId
            )
            ->assertJsonPath(
                'data.complaints.0.context.type',
                'mentor_profile'
            );
    }

    public function test_company_and_supervisor_cannot_complain_about_mentor_profile(): void
    {
        $mentorProfileId = $this->createMentorProfile();

        foreach (['company', 'supervisor'] as $role) {
            $user = $this->createUserWithRole($role);
            Sanctum::actingAs($user);

            $this->postJson('/api/complaints', [
                'context_type' => 'mentor_profile',
                'context_id' => $mentorProfileId,
                'reason' => 'This request should not be permitted for this role.',
            ])->assertForbidden();
        }
    }

    public function test_mentor_only_user_cannot_submit_any_complaint(): void
    {
        $mentor = $this->createUserWithRole('mentor');
        $postOwner = $this->createUserWithRole('student');
        $postId = $this->createPost($postOwner->id);

        Sanctum::actingAs($mentor);

        $this->postJson('/api/complaints', [
            'context_type' => 'community_post',
            'context_id' => $postId,
            'reason' => 'A mentor-only account must not be able to submit complaints.',
        ])->assertForbidden();
    }

    public function test_client_cannot_override_complaint_identity_or_target(): void
    {
        $student = $this->createUserWithRole('student');
        $postOwner = $this->createUserWithRole('company');
        $otherUser = $this->createUserWithRole('supervisor');
        $postId = $this->createPost($postOwner->id);

        Sanctum::actingAs($student);

        $this->postJson('/api/complaints', [
            'context_type' => 'community_post',
            'context_id' => $postId,
            'complainant_user_id' => $otherUser->id,
            'reported_user_id' => $otherUser->id,
            'status' => 'resolved',
            'reason' => 'The client must not be able to override protected complaint fields.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'complainant_user_id',
                'reported_user_id',
                'status',
            ]);
    }

    public function test_student_can_report_community_post_but_cannot_report_own_post(): void
    {
        $student = $this->createUserWithRole('student');
        $otherUser = $this->createUserWithRole('company');
        $otherPostId = $this->createPost($otherUser->id);
        $ownPostId = $this->createPost($student->id);

        Sanctum::actingAs($student);

        $this->postJson('/api/complaints', [
            'context_type' => 'community_post',
            'context_id' => $otherPostId,
            'reason' => 'This community post contains abusive and inappropriate content.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reported_user.id', $otherUser->id);

        $this->postJson('/api/complaints', [
            'context_type' => 'community_post',
            'context_id' => $ownPostId,
            'reason' => 'Trying to report my own community post should be rejected.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('complaint');
    }

    public function test_student_and_supervisor_can_report_each_other_only_inside_same_project(): void
    {
        $student = $this->createUserWithRole('student');
        $otherStudent = $this->createUserWithRole('student');
        $supervisor = $this->createUserWithRole('supervisor');
        $unrelatedSupervisor = $this->createUserWithRole('supervisor');

        $assignmentId = $this->createProjectAssignment(
            supervisorId: $supervisor->id,
            studentIds: [$student->id, $otherStudent->id],
        );

        Sanctum::actingAs($student);

        $this->postJson('/api/complaints', [
            'context_type' => 'project_assignment',
            'context_id' => $assignmentId,
            'reason' => 'The assigned supervisor repeatedly behaved inappropriately.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reported_user.id', $supervisor->id);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/complaints', [
            'context_type' => 'project_assignment',
            'context_id' => $assignmentId,
            'target_user_id' => $otherStudent->id,
            'reason' => 'The selected student repeatedly violated project collaboration rules.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reported_user.id', $otherStudent->id);

        Sanctum::actingAs($unrelatedSupervisor);

        $this->postJson('/api/complaints', [
            'context_type' => 'project_assignment',
            'context_id' => $assignmentId,
            'target_user_id' => $student->id,
            'reason' => 'An unrelated supervisor must not use this project context.',
        ])->assertForbidden();
    }

    public function test_supervisor_must_choose_a_student_from_the_project(): void
    {
        $student = $this->createUserWithRole('student');
        $outsider = $this->createUserWithRole('student');
        $supervisor = $this->createUserWithRole('supervisor');

        $assignmentId = $this->createProjectAssignment(
            supervisorId: $supervisor->id,
            studentIds: [$student->id],
        );

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/complaints', [
            'context_type' => 'project_assignment',
            'context_id' => $assignmentId,
            'reason' => 'The supervisor must identify which project student is being reported.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_user_id');

        $this->postJson('/api/complaints', [
            'context_type' => 'project_assignment',
            'context_id' => $assignmentId,
            'target_user_id' => $outsider->id,
            'reason' => 'An outsider must never be accepted as a target for this project.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_user_id');
    }

    public function test_student_and_company_can_report_each_other_through_task_assignment(): void
    {
        $student = $this->createUserWithRole('student');
        $companyUser = $this->createUserWithRole('company');

        [$companyId, $assignmentId] = $this->createCompanyTaskInteraction(
            $companyUser->id,
            $student->id,
        );

        Sanctum::actingAs($student);

        $this->postJson('/api/complaints', [
            'context_type' => 'company_task_assignment',
            'context_id' => $assignmentId,
            'reason' => 'The company representative behaved improperly during the assigned task.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reported_user.id', $companyUser->id);

        Sanctum::actingAs($companyUser);

        $this->postJson('/api/complaints', [
            'context_type' => 'company_task_assignment',
            'context_id' => $assignmentId,
            'reason' => 'The student seriously violated the agreed task collaboration rules.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reported_user.id', $student->id);

        $this->assertDatabaseHas('company_users', [
            'company_id' => $companyId,
            'user_id' => $companyUser->id,
        ]);
    }

    public function test_student_and_company_can_report_each_other_through_interview(): void
    {
        $student = $this->createUserWithRole('student');
        $companyUser = $this->createUserWithRole('company');

        $interviewId = $this->createOpportunityInterviewInteraction(
            $companyUser->id,
            $student->id,
        );

        Sanctum::actingAs($student);

        $this->postJson('/api/complaints', [
            'context_type' => 'opportunity_interview',
            'context_id' => $interviewId,
            'reason' => 'The company representative acted inappropriately during the interview process.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reported_user.id', $companyUser->id);

        Sanctum::actingAs($companyUser);

        $this->postJson('/api/complaints', [
            'context_type' => 'opportunity_interview',
            'context_id' => $interviewId,
            'reason' => 'The student seriously violated the interview communication rules.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reported_user.id', $student->id);
    }

    public function test_duplicate_active_complaint_is_rejected_and_new_one_is_allowed_after_resolution(): void
    {
        $student = $this->createUserWithRole('student');
        $admin = $this->createUserWithRole('admin');
        $postOwner = $this->createUserWithRole('company');
        $postId = $this->createPost($postOwner->id);

        $payload = [
            'context_type' => 'community_post',
            'context_id' => $postId,
            'reason' => 'This post contains repeated abusive behavior that should be reviewed.',
        ];

        Sanctum::actingAs($student);

        $first = $this->postJson('/api/complaints', $payload)
            ->assertCreated();

        $complaintId = $first->json('data.id');

        $this->postJson('/api/complaints', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('complaint');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/complaints/{$complaintId}", [
            'status' => 'resolved',
            'resolution_notes' => 'The first incident was reviewed and closed.',
        ])->assertOk();

        Sanctum::actingAs($student);

        $this->postJson('/api/complaints', $payload)
            ->assertCreated();
    }

    private function createUserWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function createMentorProfile(
        ?int $userId = null,
        string $status = 'approved',
    ): int {
        return DB::table('mentor_profiles')->insertGetId([
            'user_id' => $userId,
            'expertise' => 'Backend engineering and software architecture',
            'is_volunteer' => true,
            'hourly_rate' => null,
            'submitted_by_user_id' => $userId,
            'company_id' => null,
            'source' => $userId === null ? 'company_nomination' : 'self_application',
            'status' => $status,
            'full_name' => 'Approved Mentor',
            'email' => 'mentor'.uniqid().'@example.com',
            'whatsapp_number' => '+963900000000',
            'specialization' => 'backend',
            'professional_title' => 'Senior Engineer',
            'bio' => 'Experienced software mentor.',
            'linkedin_url' => null,
            'github_or_portfolio_url' => null,
            'cv_path' => null,
            'mentoring_topics' => json_encode(['backend']),
            'reviewed_by' => null,
            'reviewed_at' => now(),
            'rejection_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPost(int $userId): int
    {
        return DB::table('posts')->insertGetId([
            'User_id' => $userId,
            'Content' => 'Community content for complaint testing.',
            'Type' => 'post',
            'LikeCount' => 0,
            'CommentCount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProjectAssignment(
        int $supervisorId,
        array $studentIds,
    ): int {
        $templateId = DB::table('project_templates')->insertGetId([
            'title' => 'Complaint Test Project',
            'description' => 'Project used by complaint feature tests.',
            'level' => 'intermediate',
            'expected_outcome' => 'Working implementation',
            'created_by_type' => 'admin',
            'created_by_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assignmentId = DB::table('project_assignments')->insertGetId([
            'project_template_id' => $templateId,
            'supervisor_id' => $supervisorId,
            'status' => 'assigned',
            'progress_percentage' => 0,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($studentIds as $studentId) {
            DB::table('project_assignment_members')->insert([
                'project_assignment_id' => $assignmentId,
                'student_id' => $studentId,
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $assignmentId;
    }

    private function createCompanyTaskInteraction(
        int $companyUserId,
        int $studentId,
    ): array {
        $companyId = $this->createCompanyForUser($companyUserId);

        $taskId = DB::table('company_tasks')->insertGetId([
            'company_id' => $companyId,
            'title' => 'Complaint Test Task',
            'description' => 'Task used to verify complaint relationship security.',
            'difficulty_level' => 'intermediate',
            'duration_days' => 7,
            'deadline' => now()->addDays(7),
            'max_accepted_students' => 1,
            'submission_type' => 'github_link',
            'status' => 'in_progress',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $applicationId = DB::table('company_task_applications')->insertGetId([
            'company_task_id' => $taskId,
            'student_user_id' => $studentId,
            'status' => 'accepted',
            'applied_at' => now(),
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assignmentId = DB::table('company_task_assignments')->insertGetId([
            'company_task_id' => $taskId,
            'company_task_application_id' => $applicationId,
            'student_user_id' => $studentId,
            'status' => 'working',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createConversation(
            (new CompanyTaskAssignment())->getMorphClass(),
            $assignmentId,
            $companyUserId,
            $studentId,
        );

        return [$companyId, $assignmentId];
    }

    private function createOpportunityInterviewInteraction(
        int $companyUserId,
        int $studentId,
    ): int {
        $companyId = $this->createCompanyForUser($companyUserId);

        $opportunityId = DB::table('opportunities')->insertGetId([
            'company_id' => $companyId,
            'title' => 'Complaint Test Internship',
            'description' => 'Opportunity used by complaint feature tests.',
            'type' => 'internship',
            'status' => 'published',
            'posted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $applicationId = DB::table('applications')->insertGetId([
            'opportunity_id' => $opportunityId,
            'user_id' => $studentId,
            'status' => 'pending',
            'applied_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interviewId = DB::table('opportunity_interviews')->insertGetId([
            'application_id' => $applicationId,
            'opportunity_id' => $opportunityId,
            'company_id' => $companyId,
            'student_user_id' => $studentId,
            'scheduled_at' => now()->addDay(),
            'meeting_type' => 'online',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createConversation(
            (new OpportunityInterview())->getMorphClass(),
            $interviewId,
            $companyUserId,
            $studentId,
        );

        return $interviewId;
    }

    private function createCompanyForUser(int $companyUserId): int
    {
        $companyId = DB::table('companies')->insertGetId([
            'industry' => 'Software',
            'location' => 'Damascus',
            'website' => 'https://example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('company_users')->insert([
            'company_id' => $companyId,
            'user_id' => $companyUserId,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $companyId;
    }

    private function createConversation(
        string $conversationableType,
        int $conversationableId,
        int $companyUserId,
        int $studentId,
    ): void {
        $conversationId = DB::table('conversations')->insertGetId([
            'conversationable_type' => $conversationableType,
            'conversationable_id' => $conversationableId,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('conversation_participants')->insert([
            [
                'conversation_id' => $conversationId,
                'user_id' => $companyUserId,
                'role' => 'company',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'conversation_id' => $conversationId,
                'user_id' => $studentId,
                'role' => 'student',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
