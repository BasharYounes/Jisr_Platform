<?php

namespace Database\Seeders;

use App\Models\AssessmentAnswer;
use App\Models\AssessmentQuestionAttempt;
use App\Models\AssessmentSession;
use App\Models\AssessmentSkillSession;
use App\Models\CV;
use App\Models\QuestionBank;
use App\Models\User;
use App\Models\UserSkill;
use App\Services\Assessment\AssessmentCompletionService;
use App\Services\Assessment\LevelEstimationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GraduationDemoAssessmentSeeder extends Seeder
{
    private const STUDENT_EMAIL = 'leleen830@gmail.com';

    private const CAREER_PATH_NAME = 'Backend Developer';

    private const DEMO_TAG = 'graduation_defense_demo_assessment_v1';

    /**
     * Initial levels are the prepared CV-analysis starting point.
     * Final levels are NOT written directly. They are calculated by the
     * real LevelEstimationService + AssessmentCompletionService.
     */
    private const SKILL_PLAN = [
        'Python' => [
            'initial_level' => 4.0,
            'initial_confidence' => 0.97,
            'expected_final_level' => 3.6,
            'expected_confidence' => 0.77,
            'attempts' => [
                [
                    'topic' => 'decorators',
                    'level' => 4,
                    'score' => 0.60,
                    'answer' => 'الـ decorator هو شيء نطبقه على function حتى نغلفها ونضيف عليها behavior إضافي، وممكن نستخدمه مثلاً للـ logging.',
                ],
                [
                    'topic' => 'generators',
                    'level' => 4,
                    'score' => 0.40,
                    'answer' => 'الـ generator يعطينا القيم عند الحاجة بدل ما يخزن كل العناصر مثل list، لذلك يكون مناسباً أكثر لما نتعامل مع بيانات كبيرة.',
                ],
                [
                    'topic' => 'list_vs_tuple',
                    'level' => 2,
                    'score' => 0.60,
                    'answer' => 'الـ list قابلة للتعديل بينما tuple غير قابلة للتعديل. أستخدم list إذا كنت بحاجة لإضافة أو حذف عناصر، و tuple إذا كانت القيم ثابتة.',
                ],
                [
                    'topic' => 'collections_performance',
                    'level' => 4,
                    'score' => 0.40,
                    'answer' => 'أول شيء أحاول أقلل العمليات المتكررة وأتعامل مع البيانات على دفعات بدل تحميل كل السجلات مرة واحدة.',
                ],
                [
                    'topic' => 'file_reading',
                    'level' => 2,
                    'score' => 0.80,
                    'answer' => 'أستخدم with open("file.txt", "r") as file ثم file.read(). استخدام with يغلق الملف تلقائياً بعد الانتهاء.',
                ],
                [
                    'topic' => 'asyncio',
                    'level' => 5,
                    'score' => 0.40,
                    'answer' => 'asyncio مفيد عندما يكون عندي عدة عمليات تنتظر I/O مثل أكثر من API request، لأنه يسمح بتنفيذها بشكل غير متزامن بدل انتظار كل طلب لوحده.',
                ],
            ],
        ],

        'Flask' => [
            'initial_level' => 2.0,
            'initial_confidence' => 0.96,
            'expected_final_level' => 1.8,
            'expected_confidence' => 0.74,
            'attempts' => [
                [
                    'topic' => 'flask_post_json',
                    'level' => 2,
                    'score' => 0.60,
                    'answer' => 'أعرّف route تدعم POST باستخدام methods=["POST"]، وبعدها أقرأ البيانات باستخدام request.get_json(). ثم أتحقق أن البيانات المطلوبة موجودة.',
                ],
                [
                    'topic' => 'flask_jsonify_vs_string',
                    'level' => 2,
                    'score' => 0.60,
                    'answer' => 'jsonify تستخدم لإرجاع JSON response مناسب للـ API، بينما string عادي يرجع نصاً عادياً. jsonify أنسب عندما أتعامل مع API.',
                ],
                [
                    'topic' => 'flask_render_template_redirect',
                    'level' => 2,
                    'score' => 0.40,
                    'answer' => 'render_template تستخدم لعرض HTML template، أما redirect فتنقل المستخدم إلى route أخرى.',
                ],
                [
                    'topic' => 'flask_hello_world',
                    'level' => 1,
                    'score' => 0.40,
                    'answer' => 'أعمل app = Flask(__name__) وبعدها أضيف route ترجع "Hello World".',
                ],
                [
                    'topic' => 'flask_routing',
                    'level' => 1,
                    'score' => 0.80,
                    'answer' => '@app.route تربط URL معين بدالة في Flask، مثلاً /users، والدالة تعالج الطلب وترجع response.',
                ],
                [
                    'topic' => 'flask_blueprints',
                    'level' => 3,
                    'score' => 0.20,
                    'answer' => 'Blueprints تساعد على تقسيم routes في ملفات أو أجزاء مختلفة بدل وضع كل شيء في ملف واحد.',
                ],
            ],
        ],

        'SQL' => [
            'initial_level' => 3.0,
            'initial_confidence' => 0.95,
            // assessment_skill_sessions.FinalLevel is DECIMAL(3,1),
            // therefore the service result 2.95 is stored as 3.0.
            'expected_final_level' => 3.0,
            'expected_confidence' => 0.78,
            'attempts' => [
                [
                    'topic' => 'sql_subqueries',
                    'level' => 3,
                    'score' => 0.60,
                    'answer' => 'الـ subquery هو query داخل query ثانية. ممكن أستخدمه مثلاً في WHERE حتى أقارن قيمة بنتيجة استعلام آخر.',
                ],
                [
                    'topic' => 'sql_normalization',
                    'level' => 3,
                    'score' => 0.40,
                    'answer' => 'Normalization تعني تنظيم البيانات وتقليل التكرار عن طريق تقسيم المعلومات على جداول مترابطة.',
                ],
                [
                    'topic' => 'sql_select_all',
                    'level' => 1,
                    'score' => 0.60,
                    'answer' => 'SELECT * FROM students;',
                ],
                [
                    'topic' => 'sql_and_or_logic',
                    'level' => 3,
                    'score' => 0.40,
                    'answer' => "أستخدم AND و OR مع أقواس حتى أحدد ترتيب الشروط بوضوح، مثلاً WHERE active = 1 AND (role = 'student' OR role = 'mentor').",
                ],
                [
                    'topic' => 'sql_where_filtering',
                    'level' => 1,
                    'score' => 0.80,
                    'answer' => 'WHERE تستخدم لتصفية الصفوف حسب شرط، مثلاً SELECT * FROM students WHERE age > 20;',
                ],
                [
                    'topic' => 'sql_indexes',
                    'level' => 4,
                    'score' => 0.60,
                    'answer' => 'الـ index يساعد على تسريع البحث والقراءة خصوصاً على الأعمدة المستخدمة كثيراً في WHERE أو JOIN، لكن وجود indexes كثيرة قد يزيد تكلفة عمليات الكتابة.',
                ],
            ],
        ],

        'Git' => [
            'initial_level' => 2.0,
            'initial_confidence' => 0.94,
            'expected_final_level' => 1.3,
            'expected_confidence' => 0.72,
            'attempts' => [
                [
                    'topic' => 'git_branches',
                    'level' => 2,
                    'score' => 0.40,
                    'answer' => 'نستخدم branch حتى نشتغل على feature بشكل منفصل عن main.',
                ],
                [
                    'topic' => 'git_general_purpose',
                    'level' => 1,
                    'score' => 0.60,
                    'answer' => 'Git نظام للتحكم بالإصدارات، يتابع التغييرات ويحفظ تاريخ المشروع حتى نقدر نرجع لنسخة سابقة.',
                ],
                [
                    'topic' => 'git_merge',
                    'level' => 2,
                    'score' => 0.40,
                    'answer' => 'git merge تستخدم لضم تغييرات branch إلى branch ثانية.',
                ],
                [
                    'topic' => 'git_add_vs_commit',
                    'level' => 1,
                    'score' => 0.60,
                    'answer' => 'git add تجهز التغييرات داخل staging area، وبعدها git commit يحفظ التغييرات المجهزة في تاريخ repository.',
                ],
                [
                    'topic' => 'git_merge_conflict',
                    'level' => 2,
                    'score' => 0.40,
                    'answer' => 'merge conflict يحدث عندما يكون في تعديلات متعارضة بين فرعين، ونحل التعارض داخل الملف ثم نكمل commit.',
                ],
                [
                    'topic' => 'git_push',
                    'level' => 1,
                    'score' => 0.40,
                    'answer' => 'git push ترفع الـ commits الموجودة محلياً إلى remote repository مثل GitHub.',
                ],
            ],
        ],
    ];

    public function run(): void
    {
        $this->ensureSafeEnvironment();

        $student = User::query()
            ->where('email', self::STUDENT_EMAIL)
            ->first();

        if ($student === null) {
            throw new RuntimeException(
                'Graduation demo student was not found: '.self::STUDENT_EMAIL
                .'. Seed/create the demo accounts before this assessment seeder.'
            );
        }

        if (! $student->hasRole('student')) {
            throw new RuntimeException(
                'The configured graduation demo user exists but does not have the student role: '
                .self::STUDENT_EMAIL
            );
        }

        $careerPath = DB::table('career_paths')
            ->where('Name', self::CAREER_PATH_NAME)
            ->first();

        if ($careerPath === null) {
            throw new RuntimeException(
                'Career path "'.self::CAREER_PATH_NAME.'" was not found.'
            );
        }

        $careerPathId = (int) $careerPath->CareerPathID;

        $skills = $this->resolveSkillsAndValidatePath($careerPathId);
        $questions = $this->resolveQuestions($careerPathId, $skills);

        /** @var LevelEstimationService $levelService */
        $levelService = app(LevelEstimationService::class);

        /** @var AssessmentCompletionService $completionService */
        $completionService = app(AssessmentCompletionService::class);

        $session = DB::transaction(function () use (
            $student,
            $careerPathId,
            $skills,
            $questions,
            $levelService,
            $completionService
        ): AssessmentSession {
            $this->deletePreviousDemoSessions(
                studentId: (int) $student->id,
                careerPathId: $careerPathId,
            );

            $this->seedCvStartingSkills(
                studentId: (int) $student->id,
                skills: $skills,
            );

            $cvId = $this->resolveStudentCvId((int) $student->id);

            $startedAt = now()->subMinutes(50);

            $initialSnapshot = collect(self::SKILL_PLAN)
                ->map(function (array $plan, string $skillName) use ($skills): array {
                    return [
                        'skill_id' => $skills[$skillName],
                        'initial_level' => $plan['initial_level'],
                        'confidence_score' => $plan['initial_confidence'],
                        'source' => 'cv_analysis',
                        'demo_tag' => self::DEMO_TAG,
                    ];
                })
                ->values()
                ->all();

            $session = AssessmentSession::query()->create([
                'UserID' => (int) $student->id,
                'CvID' => $cvId,
                'CareerPathID' => $careerPathId,
                'Status' => AssessmentSession::STATUS_IN_PROGRESS,
                'InitialSkillsSnapshotJson' => $initialSnapshot,
                'FinalResultsJson' => null,
                'StartedAt' => $startedAt,
                'CompletedAt' => null,
            ]);

            $finalResults = [];

            foreach (self::SKILL_PLAN as $skillName => $plan) {
                $skillId = $skills[$skillName];

                $skillSession = AssessmentSkillSession::query()->create([
                    'AssessmentSessionID' => (int) $session->AssessmentSessionID,
                    'SkillID' => $skillId,
                    'InitialLevel' => $plan['initial_level'],
                    'CurrentEstimatedLevel' => $plan['initial_level'],
                    'FinalLevel' => null,
                    'ConfidenceScore' => null,
                    'QuestionCount' => 0,
                    'Status' => AssessmentSkillSession::STATUS_IN_PROGRESS,
                    'CompletedAt' => null,
                ]);

                $currentLevel = (float) $plan['initial_level'];

                foreach ($plan['attempts'] as $index => $attemptPlan) {
                    /** @var QuestionBank $question */
                    $question = $questions[$skillName][$attemptPlan['topic']];

                    $askedAt = $startedAt->copy()->addMinutes(
                        2 + (($this->skillOrder($skillName) * 10) + ($index * 2))
                    );
                    $answeredAt = $askedAt->copy()->addMinute();

                    $normalizedScore = (float) $attemptPlan['score'];
                    $maxScore = (float) $question->rubrics()->sum('MaxScore');

                    if ($maxScore <= 0) {
                        throw new RuntimeException(
                            "Question {$question->QuestionID} ({$attemptPlan['topic']}) "
                            .'has no scoring rubrics.'
                        );
                    }

                    $rawScore = round($normalizedScore * $maxScore, 2);
                    $feedback = $this->feedbackForScore(
                        skillName: $skillName,
                        score: $normalizedScore,
                    );

                    $attempt = AssessmentQuestionAttempt::query()->create([
                        'AssessmentSkillSessionID' => (
                            (int) $skillSession->AssessmentSkillSessionID
                        ),
                        'QuestionID' => (int) $question->QuestionID,
                        'QuestionLevel' => (int) $question->Level,
                        'AskedAt' => $askedAt,
                        'AnsweredAt' => $answeredAt,
                        'LlmEvaluationStatus' => 'completed',
                        'RawScore' => $rawScore,
                        'NormalizedScore' => $normalizedScore,
                        'FeedbackText' => $feedback,
                        'EvaluationJson' => [
                            'total_score' => $rawScore,
                            'max_score' => $maxScore,
                            'normalized_score' => $normalizedScore,
                            'feedback_ar' => $feedback,
                            'evaluation_mode' => 'expert_rules_precomputed_demo',
                            'question_engine_at_seed_time' => (
                                $question->EvaluationEngine ?: 'legacy_llm'
                            ),
                            'question_expert_ready_at_seed_time' => (
                                (bool) $question->IsExpertReady
                            ),
                            'demo_precomputed' => true,
                            'demo_tag' => self::DEMO_TAG,
                            'validation' => [
                                'needs_review' => false,
                                'warnings' => [],
                            ],
                            'note' => (
                                'Prepared graduation-defense assessment record. '
                                .'No external AI call is executed by this seeder.'
                            ),
                        ],
                        'EvaluationEngine' => 'expert_rules',
                        'EvaluationStatus' => 'completed',
                        'EvaluationEngineVersion' => (
                            $question->RuleSetVersion ?: 'v1'
                        ),
                    ]);

                    AssessmentAnswer::query()->create([
                        'AssessmentQuestionAttemptID' => (
                            (int) $attempt->AssessmentQuestionAttemptID
                        ),
                        'AnswerText' => $attemptPlan['answer'],
                        'AnswerJson' => null,
                        'SubmittedAt' => $answeredAt,
                    ]);

                    /*
                     * Mimic the production answer flow. CurrentEstimatedLevel
                     * is persisted after every answer, so we re-read it from
                     * the database to respect the DECIMAL(3,1) column exactly.
                     */
                    $currentLevel = $levelService->resolveNextLevel(
                        currentLevel: $currentLevel,
                        normalizedScore: $normalizedScore,
                    );

                    $skillSession->forceFill([
                        'CurrentEstimatedLevel' => $currentLevel,
                        'QuestionCount' => $index + 1,
                    ])->save();

                    $currentLevel = (float) $skillSession
                        ->fresh()
                        ->CurrentEstimatedLevel;
                }

                /*
                 * FinalLevel and ConfidenceScore are calculated by the real
                 * production completion service from the prepared attempts.
                 */
                $skillSession = $completionService
                    ->completeSkillSessionIfEligible($skillSession);

                $this->assertExpectedFinalResult(
                    skillName: $skillName,
                    skillSession: $skillSession,
                    expectedFinalLevel: (float) $plan['expected_final_level'],
                    expectedConfidence: (float) $plan['expected_confidence'],
                );

                $this->syncFinalUserSkill(
                    studentId: (int) $student->id,
                    skillSession: $skillSession,
                );

                $testedTopics = collect($plan['attempts'])
                    ->pluck('topic')
                    ->unique()
                    ->values()
                    ->all();

                $availableTopicCount = QuestionBank::query()
                    ->where('SkillID', $skillId)
                    ->where('IsActive', true)
                    ->whereNotNull('Topic')
                    ->distinct()
                    ->count('Topic');

                $topicCoverageRatio = $availableTopicCount > 0
                    ? round(count($testedTopics) / $availableTopicCount, 2)
                    : null;

                $finalResults[] = [
                    'skill_id' => $skillId,
                    'initial_level' => (float) $skillSession->InitialLevel,
                    'final_level' => (float) $skillSession->FinalLevel,
                    'confidence_score' => (float) $skillSession->ConfidenceScore,
                    'status' => $skillSession->Status,
                    'final_result_available' => true,
                    'tested_topics' => $testedTopics,
                    'topic_count' => count($testedTopics),
                    'available_topic_count' => $availableTopicCount,
                    'topic_coverage_ratio' => $topicCoverageRatio,
                ];
            }

            $session->forceFill([
                'Status' => AssessmentSession::STATUS_COMPLETED,
                'FinalResultsJson' => $finalResults,
                'CompletedAt' => now()->subMinutes(2),
            ])->save();

            return $session->fresh([
                'skillSessions.skill',
                'skillSessions.questionAttempts.answer',
                'skillSessions.questionAttempts.questionBank',
            ]);
        });

        $this->printSummary($session);
    }

    private function ensureSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'GraduationDemoAssessmentSeeder is allowed only in local or testing environments.'
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function resolveSkillsAndValidatePath(int $careerPathId): array
    {
        $resolved = [];

        foreach (self::SKILL_PLAN as $skillName => $plan) {
            $skill = DB::table('skills')
                ->where('name', $skillName)
                ->first();

            if ($skill === null) {
                throw new RuntimeException("Skill {$skillName} was not found.");
            }

            $pathSkill = DB::table('career_path_skills')
                ->where('CareerPathID', $careerPathId)
                ->where('SkillID', $skill->id)
                ->first();

            if ($pathSkill === null) {
                throw new RuntimeException(
                    "Skill {$skillName} is not configured for Backend Developer."
                );
            }

            $resolved[$skillName] = (int) $skill->id;
        }

        return $resolved;
    }

    /**
     * @param  array<string, int>  $skills
     * @return array<string, array<string, QuestionBank>>
     */
    private function resolveQuestions(int $careerPathId, array $skills): array
    {
        $resolved = [];

        foreach (self::SKILL_PLAN as $skillName => $plan) {
            foreach ($plan['attempts'] as $attemptPlan) {
                $topic = $attemptPlan['topic'];

                /*
                 * IMPORTANT:
                 * Match the real QuestionSelectionService behavior:
                 * - same Skill
                 * - active question
                 * - current CareerPath OR global question (CareerPathID = NULL)
                 * - Expert System only
                 * - Expert-ready only
                 */
                $question = QuestionBank::query()
                    ->where('SkillID', $skills[$skillName])
                    ->where('IsActive', true)
                    ->where('EvaluationEngine', 'expert_rules')
                    ->where('IsExpertReady', true)
                    ->where('Topic', $topic)
                    ->where(function ($query) use ($careerPathId) {
                        $query->where('CareerPathID', $careerPathId)
                            ->orWhereNull('CareerPathID');
                    })
                    ->orderByRaw(
                        'CASE WHEN CareerPathID = ? THEN 0 ELSE 1 END',
                        [$careerPathId]
                    )
                    ->first();

                if ($question === null) {
                    throw new RuntimeException(
                        "Expert System question not found for {$skillName} / {$topic}. "
                        .'Expected an active expert_rules + IsExpertReady question '
                        .'for Backend Developer or a global question.'
                    );
                }

                if ((int) $question->Level !== (int) $attemptPlan['level']) {
                    throw new RuntimeException(
                        "Unexpected level for {$skillName} / {$topic}. "
                        ."Expected {$attemptPlan['level']}, found {$question->Level}."
                    );
                }

                if ($question->rubrics()->count() === 0) {
                    throw new RuntimeException(
                        "Expert System question {$skillName} / {$topic} has no rubrics."
                    );
                }

                $resolved[$skillName][$topic] = $question;
            }
        }

        return $resolved;
    }

    private function deletePreviousDemoSessions(
        int $studentId,
        int $careerPathId
    ): void {
        $sessions = AssessmentSession::query()
            ->where('UserID', $studentId)
            ->where('CareerPathID', $careerPathId)
            ->get();

        foreach ($sessions as $session) {
            if ($this->isGraduationDemoSession($session)) {
                $session->delete();
            }
        }
    }

    private function isGraduationDemoSession(AssessmentSession $session): bool
    {
        $snapshot = $session->InitialSkillsSnapshotJson ?? [];

        if (! is_array($snapshot)) {
            return false;
        }

        foreach ($snapshot as $item) {
            if (
                is_array($item)
                && ($item['demo_tag'] ?? null) === self::DEMO_TAG
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Seeds the same starting levels that the prepared CV analysis provides.
     *
     * @param  array<string, int>  $skills
     */
    private function seedCvStartingSkills(int $studentId, array $skills): void
    {
        foreach (self::SKILL_PLAN as $skillName => $plan) {
            UserSkill::query()->updateOrCreate(
                [
                    'UserId' => $studentId,
                    'SkillId' => $skills[$skillName],
                ],
                [
                    'ProficiencyLevel' => (int) round($plan['initial_level']),
                    'ConfidenceScore' => (float) $plan['initial_confidence'],
                    'Source' => 'cv_analysis',
                    'Verified' => false,
                    'VerificationStatus' => UserSkill::STATUS_AI_ESTIMATED,
                    'VerifiedAt' => null,
                    'VerifiedBy' => null,
                ],
            );
        }
    }

    private function resolveStudentCvId(int $studentId): ?int
    {
        $cv = CV::query()
            ->where('UserId', $studentId)
            ->orderByDesc('IsPrimary')
            ->orderByDesc('UploadedAt')
            ->orderByDesc('CvID')
            ->first();

        return $cv !== null ? (int) $cv->CvID : null;
    }

    private function syncFinalUserSkill(
        int $studentId,
        AssessmentSkillSession $skillSession
    ): void {
        $userSkill = UserSkill::query()->firstOrNew([
            'UserId' => $studentId,
            'SkillId' => (int) $skillSession->SkillID,
        ]);

        $protectedStatuses = [
            UserSkill::STATUS_CODE_TESTED,
            UserSkill::STATUS_SUPERVISOR_VERIFIED,
            UserSkill::STATUS_COMPANY_VERIFIED,
        ];

        $hasStrongerVerification = $userSkill->exists
            && in_array(
                $userSkill->VerificationStatus,
                $protectedStatuses,
                true
            );

        $userSkill->ProficiencyLevel = max(
            1,
            min(5, (int) round((float) $skillSession->FinalLevel))
        );

        $userSkill->ConfidenceScore = (float) (
            $skillSession->ConfidenceScore ?? 0.50
        );

        if (! $hasStrongerVerification) {
            $userSkill->Source = 'ai_assessment';
            $userSkill->Verified = false;
            $userSkill->VerificationStatus = UserSkill::STATUS_AI_ESTIMATED;
            $userSkill->VerifiedAt = null;
            $userSkill->VerifiedBy = null;
        }

        $userSkill->save();
    }

    private function assertExpectedFinalResult(
        string $skillName,
        AssessmentSkillSession $skillSession,
        float $expectedFinalLevel,
        float $expectedConfidence
    ): void {
        if ($skillSession->Status !== AssessmentSkillSession::STATUS_COMPLETED) {
            throw new RuntimeException(
                "{$skillName} did not reach completed status."
            );
        }

        $actualLevel = (float) $skillSession->FinalLevel;
        $actualConfidence = (float) $skillSession->ConfidenceScore;

        if (abs($actualLevel - $expectedFinalLevel) > 0.001) {
            throw new RuntimeException(
                "{$skillName} final level mismatch. "
                ."Expected {$expectedFinalLevel}, got {$actualLevel}."
            );
        }

        if (abs($actualConfidence - $expectedConfidence) > 0.001) {
            throw new RuntimeException(
                "{$skillName} confidence mismatch. "
                ."Expected {$expectedConfidence}, got {$actualConfidence}."
            );
        }
    }

    private function feedbackForScore(string $skillName, float $score): string
    {
        return match (true) {
            $score >= 0.80 => (
                "إجابة قوية في {$skillName}. تم تحقيق معظم معايير السؤال، "
                .'مع بقاء مساحة بسيطة لإضافة تفاصيل أدق.'
            ),
            $score >= 0.60 => (
                "إجابة جيدة في {$skillName} وتحقق المعايير الأساسية، "
                .'لكنها تحتاج بعض التفاصيل الإضافية لتصبح مكتملة.'
            ),
            $score >= 0.40 => (
                "الإجابة في {$skillName} تحقق جزءاً من المعايير، "
                .'لكنها تحتاج توضيحاً أعمق لبعض المفاهيم المطلوبة.'
            ),
            default => (
                "الإجابة في {$skillName} أظهرت فهماً أولياً، "
                .'لكن معظم معايير السؤال ما زالت بحاجة إلى تطوير.'
            ),
        };
    }

    private function skillOrder(string $skillName): int
    {
        return match ($skillName) {
            'Python' => 0,
            'Flask' => 1,
            'SQL' => 2,
            'Git' => 3,
            default => 4,
        };
    }

    private function printSummary(AssessmentSession $session): void
    {
        $this->command?->newLine();
        $this->command?->info('Graduation demo assessment seeded successfully.');
        $this->command?->line(
            'Student: '.self::STUDENT_EMAIL.' | Session #'.$session->AssessmentSessionID
        );
        $this->command?->line(
            'Career path: '.self::CAREER_PATH_NAME
            .' | Linked CV: '.($session->CvID !== null ? '#'.$session->CvID : 'none')
        );

        $rows = $session->skillSessions
            ->sortBy(fn ($item) => $this->skillOrder($item->skill?->name ?? ''))
            ->map(function ($item): array {
                return [
                    $item->skill?->name ?? ('Skill #'.$item->SkillID),
                    number_format((float) $item->InitialLevel, 1),
                    number_format((float) $item->FinalLevel, 1),
                    number_format((float) $item->ConfidenceScore, 2),
                    (int) $item->QuestionCount,
                    $item->Status,
                ];
            })
            ->values()
            ->all();

        $this->command?->table(
            ['Skill', 'Initial', 'Final', 'Confidence', 'Questions', 'Status'],
            $rows
        );

        $this->command?->line(
            'Prepared attempts: '.$session->skillSessions
                ->sum(fn ($item) => $item->questionAttempts->count())
        );

        $this->command?->warn(
            'This seeder stores precomputed completed assessment records for the defense. '
            .'It does not call Gemini.'
        );
    }
}
