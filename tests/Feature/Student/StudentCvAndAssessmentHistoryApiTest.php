<?php

namespace Tests\Feature\Student;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentQuestionAttempt;
use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CareerPath;
use App\Models\CV;
use App\Models\CVAnalysis;
use App\Models\CVExtractedSkill;
use App\Models\QuestionBank;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentCvAndAssessmentHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('student', 'web');
    }

    public function test_student_can_list_only_their_cvs_with_latest_analysis_metadata(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();

        $olderCv = $this->createCv($student, [
            'FileUrl' => 'cvs/older.pdf',
            'UploadedAt' => now()->subDay(),
        ]);

        $newerCv = $this->createCv($student, [
            'FileUrl' => 'cvs/newer.pdf',
            'UploadedAt' => now(),
        ]);

        $otherCv = $this->createCv($otherStudent);

        CVAnalysis::query()->create([
            'CvId' => $olderCv->CvID,
            'ExtractedSkillsJson' => [],
            'OverallScore' => 40,
            'AiModelVersion' => 'old-model',
            'AnalyzedAt' => now()->subHour(),
        ]);

        $latestAnalysis = CVAnalysis::query()->create([
            'CvId' => $olderCv->CvID,
            'ExtractedSkillsJson' => [],
            'OverallScore' => 82,
            'AiModelVersion' => 'latest-model',
            'AnalyzedAt' => now(),
        ]);

        CVExtractedSkill::query()->create([
            'CVAnalysisID' => $latestAnalysis->CVAnalysisID,
            'RawSkillName' => 'Laravel',
            'InitialLevel' => 3,
            'ConfidenceScore' => 0.88,
            'ExtractionSource' => 'llm',
        ]);

        $response = $this
            ->actingAs($student)
            ->getJson('/api/student/cvs');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.cvs.0.cv_id', $newerCv->CvID)
            ->assertJsonPath('data.cvs.0.has_analysis', false)
            ->assertJsonPath('data.cvs.1.cv_id', $olderCv->CvID)
            ->assertJsonPath(
                'data.cvs.1.latest_analysis.analysis_id',
                $latestAnalysis->CVAnalysisID
            )
            ->assertJsonPath('data.cvs.1.latest_analysis.skills_count', 1)
            ->assertJsonPath('data.cvs.1.latest_analysis.overall_score', 82);

        $this->assertNotContains(
            $otherCv->CvID,
            collect($response->json('data.cvs'))->pluck('cv_id')->all()
        );
    }

    public function test_student_can_view_latest_analysis_for_their_cv_only(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();
        $cv = $this->createCv($student);

        $skill = Skill::query()->create([
            'name' => 'Laravel',
            'category' => 'Backend',
            'normalized_name' => 'laravel',
        ]);

        $analysis = CVAnalysis::query()->create([
            'CvId' => $cv->CvID,
            'ExtractedSkillsJson' => [],
            'MissingCriteriaJson' => ['Testing'],
            'OverallScore' => 76,
            'AiModelVersion' => 'extraction-v1',
            'AnalyzedAt' => now(),
        ]);

        CVExtractedSkill::query()->create([
            'CVAnalysisID' => $analysis->CVAnalysisID,
            'SkillID' => $skill->id,
            'RawSkillName' => 'Laravel Framework',
            'EvidenceText' => 'Built REST APIs with Laravel.',
            'InitialLevel' => 3,
            'ConfidenceScore' => 0.90,
            'ExtractionSource' => 'llm',
        ]);

        $this
            ->actingAs($student)
            ->getJson("/api/student/cvs/{$cv->CvID}/analysis")
            ->assertOk()
            ->assertJsonPath('data.cv.cv_id', $cv->CvID)
            ->assertJsonPath('data.analysis.analysis_id', $analysis->CVAnalysisID)
            ->assertJsonPath('data.analysis.skills.0.skill_id', $skill->id)
            ->assertJsonPath('data.analysis.skills.0.skill_name', 'Laravel')
            ->assertJsonPath('data.analysis.skills.0.initial_level', 3)
            ->assertJsonPath('data.analysis.missing_criteria.0', 'Testing');

        $this
            ->actingAs($otherStudent)
            ->getJson("/api/student/cvs/{$cv->CvID}/analysis")
            ->assertNotFound()
            ->assertJsonPath('message', 'CV not found.');
    }

    public function test_student_can_list_only_their_assessments(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();
        $careerPath = $this->createCareerPath();
        $skill = $this->createSkill();

        $olderAssessment = $this->createAssessment($student, $careerPath, [
            'Status' => AssessmentSession::STATUS_COMPLETED,
            'StartedAt' => now()->subDay(),
            'CompletedAt' => now()->subDay()->addHour(),
            'FinalResultsJson' => [['skill_id' => $skill->id]],
        ]);

        AssessmentSkillSession::query()->create([
            'AssessmentSessionID' => $olderAssessment->AssessmentSessionID,
            'SkillID' => $skill->id,
            'InitialLevel' => 2,
            'CurrentEstimatedLevel' => 3,
            'FinalLevel' => 3,
            'ConfidenceScore' => 0.80,
            'QuestionCount' => 5,
            'Status' => AssessmentSkillSession::STATUS_COMPLETED,
            'CompletedAt' => now()->subDay()->addHour(),
        ]);

        $newerAssessment = $this->createAssessment($student, $careerPath, [
            'StartedAt' => now(),
        ]);

        $otherAssessment = $this->createAssessment($otherStudent, $careerPath);

        $response = $this
            ->actingAs($student)
            ->getJson('/api/student/assessments');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath(
                'data.assessments.0.assessment_session_id',
                $newerAssessment->AssessmentSessionID
            )
            ->assertJsonPath(
                'data.assessments.1.assessment_session_id',
                $olderAssessment->AssessmentSessionID
            )
            ->assertJsonPath('data.assessments.1.progress.total_skills', 1)
            ->assertJsonPath('data.assessments.1.progress.completed_skills', 1)
            ->assertJsonPath('data.assessments.1.progress.completion_percentage', 100)
            ->assertJsonPath('data.assessments.1.final_results_available', true);

        $this->assertNotContains(
            $otherAssessment->AssessmentSessionID,
            collect($response->json('data.assessments'))
                ->pluck('assessment_session_id')
                ->all()
        );
    }

    public function test_student_can_view_full_details_for_their_assessment_only(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();
        $careerPath = $this->createCareerPath();
        $skill = $this->createSkill();
        $cv = $this->createCv($student);

        $assessment = $this->createAssessment($student, $careerPath, [
            'CvID' => $cv->CvID,
            'Status' => AssessmentSession::STATUS_COMPLETED,
            'CompletedAt' => now(),
            'FinalResultsJson' => [[
                'skill_id' => $skill->id,
                'final_level' => 4,
            ]],
        ]);

        $skillSession = AssessmentSkillSession::query()->create([
            'AssessmentSessionID' => $assessment->AssessmentSessionID,
            'SkillID' => $skill->id,
            'InitialLevel' => 3,
            'CurrentEstimatedLevel' => 4,
            'FinalLevel' => 4,
            'ConfidenceScore' => 0.84,
            'QuestionCount' => 1,
            'Status' => AssessmentSkillSession::STATUS_COMPLETED,
            'CompletedAt' => now(),
        ]);

        $question = QuestionBank::query()->create([
            'SkillID' => $skill->id,
            'CareerPathID' => $careerPath->CareerPathID,
            'Level' => 3,
            'QuestionType' => 'open_text',
            'Topic' => 'Routing',
            'QuestionText' => 'Explain Laravel route model binding.',
            'ExpectedAnswerType' => 'text',
            'DifficultyWeight' => 1,
            'IsActive' => true,
        ]);

        $attempt = AssessmentQuestionAttempt::query()->create([
            'AssessmentSkillSessionID' => $skillSession->AssessmentSkillSessionID,
            'QuestionID' => $question->QuestionID,
            'QuestionLevel' => 3,
            'AskedAt' => now()->subMinute(),
            'AnsweredAt' => now(),
            'LlmEvaluationStatus' => 'completed',
            'RawScore' => 8.5,
            'NormalizedScore' => 0.85,
            'FeedbackText' => 'Good answer.',
        ]);

        AssessmentAnswer::query()->create([
            'AssessmentQuestionAttemptID' => $attempt->AssessmentQuestionAttemptID,
            'AnswerText' => 'Laravel injects the model using the route key.',
            'SubmittedAt' => now(),
        ]);

        $this
            ->actingAs($student)
            ->getJson("/api/student/assessments/{$assessment->AssessmentSessionID}")
            ->assertOk()
            ->assertJsonPath(
                'data.assessment_session_id',
                $assessment->AssessmentSessionID
            )
            ->assertJsonPath('data.career_path.name', $careerPath->Name)
            ->assertJsonPath('data.cv.cv_id', $cv->CvID)
            ->assertJsonPath('data.skills.0.skill_name', $skill->name)
            ->assertJsonPath('data.skills.0.attempts.0.question.topic', 'Routing')
            ->assertJsonPath(
                'data.skills.0.attempts.0.answer.text',
                'Laravel injects the model using the route key.'
            )
            ->assertJsonPath(
                'data.skills.0.attempts.0.evaluation.normalized_score',
                0.85
            )
            ->assertJsonMissingPath(
                'data.skills.0.attempts.0.question.expected_answer'
            );

        $this
            ->actingAs($otherStudent)
            ->getJson("/api/student/assessments/{$assessment->AssessmentSessionID}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Assessment session not found.');
    }

    public function test_history_routes_require_authentication_and_student_role(): void
    {
        $this->getJson('/api/student/cvs')->assertUnauthorized();
        $this->getJson('/api/student/assessments')->assertUnauthorized();

        $authenticatedNonStudent = User::factory()->create();

        $this
            ->actingAs($authenticatedNonStudent)
            ->getJson('/api/student/cvs')
            ->assertForbidden();

        $this
            ->actingAs($authenticatedNonStudent)
            ->getJson('/api/student/assessments')
            ->assertForbidden();
    }

    public function test_authenticated_user_cannot_analyze_another_users_cv(): void
    {
        $owner = $this->createStudent();
        $otherStudent = $this->createStudent();
        $cv = $this->createCv($owner);

        $this
            ->actingAs($otherStudent)
            ->postJson("/api/cvs/{$cv->CvID}/analyze")
            ->assertForbidden()
            ->assertJsonPath('message', 'You are not authorized to analyze this CV.');
    }

    public function test_assessment_cannot_be_linked_to_another_users_cv(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent();
        $careerPath = $this->createCareerPath();
        $skill = $this->createSkill();
        $otherCv = $this->createCv($otherStudent);

        $this
            ->actingAs($student)
            ->postJson('/api/assessments', [
                'career_path_id' => $careerPath->CareerPathID,
                'cv_id' => $otherCv->CvID,
                'skill_ids' => [$skill->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cv_id');

        $this->assertDatabaseCount('assessment_sessions', 0);
    }

    private function createStudent(): User
    {
        $student = User::factory()->create();
        $student->assignRole('student');

        return $student;
    }

    private function createCv(User $student, array $overrides = []): CV
    {
        return CV::query()->create(array_merge([
            'UserId' => $student->id,
            'FileUrl' => 'cvs/'.uniqid().'.pdf',
            'IsPrimary' => false,
            'UploadedAt' => now(),
        ], $overrides));
    }

    private function createCareerPath(): CareerPath
    {
        return CareerPath::query()->create([
            'Name' => 'Backend Developer '.uniqid(),
            'Description' => 'Backend development path.',
        ]);
    }

    private function createSkill(): Skill
    {
        $suffix = uniqid();

        return Skill::query()->create([
            'name' => 'Laravel '.$suffix,
            'category' => 'Backend',
            'normalized_name' => 'laravel_'.$suffix,
        ]);
    }

    private function createAssessment(
        User $student,
        CareerPath $careerPath,
        array $overrides = []
    ): AssessmentSession {
        return AssessmentSession::query()->create(array_merge([
            'UserID' => $student->id,
            'CareerPathID' => $careerPath->CareerPathID,
            'Status' => AssessmentSession::STATUS_IN_PROGRESS,
            'InitialSkillsSnapshotJson' => [],
            'StartedAt' => now()->subHour(),
        ], $overrides));
    }
}
