<?php

namespace Database\Seeders;

use App\Models\AILearningPlan;
use App\Models\AssessmentSession;
use App\Services\Recommendations\LearningPathService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GraduationDemoLearningPlanSeeder extends Seeder
{
    private const STUDENT_EMAIL = 'leleen830@gmail.com';

    private const CAREER_PATH_NAME = 'Backend Developer';

    private const WEEKS = 4;

    private const HOURS_PER_WEEK = 5;

    private const DEMO_MODEL_VERSION = 'prepared-demo-v1';

    private const EXPECTED_ORDER = [
        'Git',
        'Flask',
    ];

    private const EXPECTED_MARKET_DEMAND = [
        'Git' => 40.54,
        'Flask' => 2.70,
    ];

    /**
     * These are official, stable documentation resources.
     *
     * They are inserted into learning_resources first, then the real
     * LearningPathService must return them before we are allowed to build the
     * prepared PlanJson. This keeps the prepared result aligned with the
     * production AILearningPlanService rule: use only provided resources.
     */
    private const DEMO_RESOURCES = [
        'Git' => [
            [
                'title' => 'Git: Basic Branching and Merging',
                'url' => 'https://git-scm.com/book/en/v2/Git-Branching-Basic-Branching-and-Merging.html',
                'type' => 'article',
                'level' => 2,
                'estimated_hours' => 2.00,
                'provider' => 'Git SCM',
            ],
            [
                'title' => 'Git: Branching Workflows',
                'url' => 'https://git-scm.com/book/en/v2/Git-Branching-Branching-Workflows',
                'type' => 'article',
                'level' => 2,
                'estimated_hours' => 1.50,
                'provider' => 'Git SCM',
            ],
        ],

        'Flask' => [
            [
                'title' => 'Flask API Reference: Requests and JSON',
                'url' => 'https://flask.palletsprojects.com/en/stable/api/',
                'type' => 'article',
                'level' => 2,
                'estimated_hours' => 1.50,
                'provider' => 'Flask Documentation',
            ],
            [
                'title' => 'Flask: Modular Applications with Blueprints',
                'url' => 'https://flask.palletsprojects.com/en/stable/blueprints/',
                'type' => 'article',
                'level' => 3,
                'estimated_hours' => 1.50,
                'provider' => 'Flask Documentation',
            ],
            [
                'title' => 'Flask: Handling Application Errors',
                'url' => 'https://flask.palletsprojects.com/en/stable/errorhandling/',
                'type' => 'article',
                'level' => 3,
                'estimated_hours' => 1.50,
                'provider' => 'Flask Documentation',
            ],
        ],
    ];

    public function run(): void
    {
        $this->ensureSafeEnvironment();

        $studentId = $this->resolveStudentId();
        $careerPathId = $this->resolveCareerPathId();

        $session = $this->resolveCompletedAssessment(
            studentId: $studentId,
            careerPathId: $careerPathId
        );

        $skillIds = $this->resolveSkillIds();

        DB::transaction(function () use ($skillIds): void {
            $this->seedDemoLearningResources($skillIds);
        });

        /** @var LearningPathService $learningPathService */
        $learningPathService = app(LearningPathService::class);

        /*
         * This is the real production learning-path calculation.
         * We do not hard-code Git > Flask here.
         */
        $learningPath = $learningPathService->generate($session);

        $this->validateLearningPath($learningPath);

        $inputSnapshot = [
            'assessment_session_id' => (
                (int) $session->AssessmentSessionID
            ),
            'career_path_id' => $careerPathId,
            'weeks' => self::WEEKS,
            'hours_per_week' => self::HOURS_PER_WEEK,
            'learning_path' => $learningPath,
        ];

        /*
         * Prepared for the graduation defense:
         * - no Gemini call is made by this seeder
         * - the structure matches AILearningPlanService
         * - every resource used below must already be present in the real
         *   learning_path generated above
         */
        $planJson = $this->buildPreparedPlan(
            learningPath: $learningPath
        );

        $this->validatePreparedPlan(
            plan: $planJson,
            learningPath: $learningPath
        );

        $plan = DB::transaction(function () use (
            $session,
            $studentId,
            $inputSnapshot,
            $planJson
        ): AILearningPlan {
            /*
             * Idempotency: remove only this seeder's own previous prepared
             * plans for this assessment. Real/generated plans are untouched.
             */
            AILearningPlan::query()
                ->where(
                    'AssessmentSessionID',
                    $session->AssessmentSessionID
                )
                ->where(
                    'AiModelVersion',
                    self::DEMO_MODEL_VERSION
                )
                ->delete();

            return AILearningPlan::query()->create([
                'AssessmentSessionID' => (
                    $session->AssessmentSessionID
                ),
                'UserID' => $studentId,
                'Status' => 'generated',
                'Weeks' => self::WEEKS,
                'HoursPerWeek' => self::HOURS_PER_WEEK,
                'InputSnapshotJson' => $inputSnapshot,
                'PlanJson' => $planJson,
                'SummaryText' => $planJson['summary_ar'],
                /*
                 * Deliberately NOT a Gemini model name.
                 * This record is a prepared demo fixture.
                 */
                'AiModelVersion' => self::DEMO_MODEL_VERSION,
                'GeneratedAt' => now(),
            ]);
        });

        $this->verifyPersistedPlan(
            plan: $plan,
            session: $session
        );

        $this->printSummary(
            plan: $plan,
            learningPath: $learningPath
        );
    }

    private function ensureSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'GraduationDemoLearningPlanSeeder is allowed only '
                .'in local or testing environments.'
            );
        }
    }

    private function resolveStudentId(): int
    {
        $studentId = DB::table('users')
            ->where('email', self::STUDENT_EMAIL)
            ->value('id');

        if (! $studentId) {
            throw new RuntimeException(
                'Graduation demo student was not found: '
                .self::STUDENT_EMAIL
            );
        }

        return (int) $studentId;
    }

    private function resolveCareerPathId(): int
    {
        $careerPathId = DB::table('career_paths')
            ->where('Name', self::CAREER_PATH_NAME)
            ->value('CareerPathID');

        if (! $careerPathId) {
            throw new RuntimeException(
                'Backend Developer career path was not found.'
            );
        }

        return (int) $careerPathId;
    }

    private function resolveCompletedAssessment(
        int $studentId,
        int $careerPathId
    ): AssessmentSession {
        $session = AssessmentSession::query()
            ->where('UserID', $studentId)
            ->where('CareerPathID', $careerPathId)
            ->where(
                'Status',
                AssessmentSession::STATUS_COMPLETED
            )
            ->latest('AssessmentSessionID')
            ->first();

        if (! $session) {
            throw new RuntimeException(
                'No completed Backend assessment was found for '
                .self::STUDENT_EMAIL
                .'. Run GraduationDemoAssessmentSeeder first.'
            );
        }

        return $session;
    }

    /**
     * @return array<string, int>
     */
    private function resolveSkillIds(): array
    {
        $resolved = [];

        foreach (self::EXPECTED_ORDER as $skillName) {
            $skillId = DB::table('skills')
                ->where('name', $skillName)
                ->value('id');

            if (! $skillId) {
                throw new RuntimeException(
                    "Required skill {$skillName} was not found."
                );
            }

            $resolved[$skillName] = (int) $skillId;
        }

        return $resolved;
    }

    /**
     * @param  array<string, int>  $skillIds
     */
    private function seedDemoLearningResources(
        array $skillIds
    ): void {
        foreach (
            self::DEMO_RESOURCES as $skillName => $resources
        ) {
            $skillId = $skillIds[$skillName] ?? null;

            if (! $skillId) {
                throw new RuntimeException(
                    "Cannot seed resources for {$skillName}: "
                    .'skill id is missing.'
                );
            }

            foreach ($resources as $resource) {
                DB::table('learning_resources')->updateOrInsert(
                    [
                        'SkillID' => $skillId,
                        'Url' => $resource['url'],
                    ],
                    [
                        'Title' => $resource['title'],
                        'Type' => $resource['type'],
                        'Level' => $resource['level'],
                        'EstimatedHours' => (
                            $resource['estimated_hours']
                        ),
                        'Provider' => $resource['provider'],
                        'Language' => 'en',
                        'IsFree' => true,
                        'IsActive' => true,
                    ]
                );
            }
        }
    }

    private function validateLearningPath(
        array $learningPath
    ): void {
        $actualOrder = collect($learningPath)
            ->pluck('skill_name')
            ->values()
            ->all();

        if ($actualOrder !== self::EXPECTED_ORDER) {
            throw new RuntimeException(
                'Learning path does not match the verified demo state. '
                .'Expected exactly: Git, Flask. Got: '
                .implode(', ', $actualOrder)
            );
        }

        foreach ($learningPath as $item) {
            $skillName = (string) (
                $item['skill_name'] ?? ''
            );

            if (! isset(
                self::EXPECTED_MARKET_DEMAND[$skillName]
            )) {
                throw new RuntimeException(
                    "Unexpected learning-path skill: {$skillName}."
                );
            }

            $marketDemand = (float) (
                $item['market']['demand_score'] ?? -1
            );

            $expectedDemand = (
                self::EXPECTED_MARKET_DEMAND[$skillName]
            );

            if (
                abs($marketDemand - $expectedDemand) > 0.001
            ) {
                throw new RuntimeException(
                    "{$skillName} market demand changed. Expected "
                    ."{$expectedDemand}, got {$marketDemand}."
                );
            }

            $availableUrls = collect(
                $item['resources'] ?? []
            )
                ->pluck('url')
                ->filter()
                ->values()
                ->all();

            foreach (
                self::DEMO_RESOURCES[$skillName] as $resource
            ) {
                if (
                    ! in_array(
                        $resource['url'],
                        $availableUrls,
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'The real LearningPathService did not return '
                        ."the required {$skillName} resource: "
                        .$resource['title']
                    );
                }
            }
        }
    }

    private function buildPreparedPlan(
        array $learningPath
    ): array {
        $resource = function (
            string $skillName,
            string $url
        ) use ($learningPath): array {
            $skillPath = collect($learningPath)->first(
                fn ($item) => (
                    ($item['skill_name'] ?? null)
                    === $skillName
                )
            );

            if (! is_array($skillPath)) {
                throw new RuntimeException(
                    "Learning path entry missing for {$skillName}."
                );
            }

            $found = collect(
                $skillPath['resources'] ?? []
            )->first(
                fn ($item) => (
                    ($item['url'] ?? null) === $url
                )
            );

            if (! is_array($found)) {
                throw new RuntimeException(
                    'Prepared plan resource is not available '
                    ."in the real learning path: {$url}"
                );
            }

            return [
                'title' => $found['title'],
                'url' => $found['url'],
                'type' => $found['type'],
                'skill' => $skillName,
            ];
        };

        $gitBranching = $resource(
            'Git',
            self::DEMO_RESOURCES['Git'][0]['url']
        );

        $gitWorkflows = $resource(
            'Git',
            self::DEMO_RESOURCES['Git'][1]['url']
        );

        $flaskApi = $resource(
            'Flask',
            self::DEMO_RESOURCES['Flask'][0]['url']
        );

        $flaskBlueprints = $resource(
            'Flask',
            self::DEMO_RESOURCES['Flask'][1]['url']
        );

        $flaskErrors = $resource(
            'Flask',
            self::DEMO_RESOURCES['Flask'][2]['url']
        );

        return [
            'career_path' => self::CAREER_PATH_NAME,
            'summary_ar' => (
                'خطة تطوير عملية لمدة أربعة أسابيع تركّز أولاً '
                .'على Git لأنها تشترك مع Flask في حجم فجوة المهارة '
                .'لكنها أعلى طلباً في عينة سوق العمل الحالية، ثم '
                .'تنتقل إلى Flask لتعزيز بناء واجهات API منظمة '
                .'والتعامل الصحيح مع الطلبات والأخطاء.'
            ),
            'weeks' => [
                [
                    'week_number' => 1,
                    'focus_skills' => ['Git'],
                    'goals' => [
                        'فهم استخدام الفروع في العمل اليومي.',
                        'تنفيذ الدمج والتعامل مع تعارض بسيط بثقة.',
                    ],
                    'tasks' => [
                        [
                            'title' => (
                                'مراجعة أساسيات Branching وMerging '
                                .'من التوثيق الرسمي'
                            ),
                            'estimated_hours' => 1.5,
                            'skill' => 'Git',
                        ],
                        [
                            'title' => (
                                'إنشاء مستودع تجريبي وتنفيذ Feature '
                                .'Branch مع عدة Commits ثم دمجه'
                            ),
                            'estimated_hours' => 2.0,
                            'skill' => 'Git',
                        ],
                        [
                            'title' => (
                                'إنشاء Merge Conflict مقصود وحله '
                                .'ثم مراجعة git status والتاريخ'
                            ),
                            'estimated_hours' => 1.5,
                            'skill' => 'Git',
                        ],
                    ],
                    'resources' => [
                        $gitBranching,
                    ],
                    'expected_outcome' => (
                        'القدرة على إنشاء الفروع ودمجها وحل '
                        .'تعارض بسيط دون التأثير على الفرع الرئيسي.'
                    ),
                ],

                [
                    'week_number' => 2,
                    'focus_skills' => ['Git'],
                    'goals' => [
                        'تحويل أساسيات Git إلى Workflow قريب من العمل الجماعي.',
                        'تحسين تنظيم الفروع والـCommits قبل الدمج.',
                    ],
                    'tasks' => [
                        [
                            'title' => (
                                'دراسة Branching Workflows '
                                .'والفرق بين الفروع طويلة وقصيرة العمر'
                            ),
                            'estimated_hours' => 1.5,
                            'skill' => 'Git',
                        ],
                        [
                            'title' => (
                                'محاكاة Workflow لفريق صغير: '
                                .'feature branch ثم review ثم merge'
                            ),
                            'estimated_hours' => 2.0,
                            'skill' => 'Git',
                        ],
                        [
                            'title' => (
                                'مراجعة سجل الـCommits وتنظيف '
                                .'التسميات وتوثيق خطوات الدمج'
                            ),
                            'estimated_hours' => 1.5,
                            'skill' => 'Git',
                        ],
                    ],
                    'resources' => [
                        $gitBranching,
                        $gitWorkflows,
                    ],
                    'expected_outcome' => (
                        'القدرة على استخدام Git ضمن Workflow منظم '
                        .'ومناسب للتعاون على مشروع برمجي.'
                    ),
                ],

                [
                    'week_number' => 3,
                    'focus_skills' => ['Flask'],
                    'goals' => [
                        'تحسين التعامل مع POST وJSON في Flask.',
                        'إضافة Validation أساسي وإرجاع Responses واضحة.',
                    ],
                    'tasks' => [
                        [
                            'title' => (
                                'مراجعة Request وJSON Response '
                                .'من Flask API Reference'
                            ),
                            'estimated_hours' => 1.5,
                            'skill' => 'Flask',
                        ],
                        [
                            'title' => (
                                'بناء POST endpoint يستقبل JSON '
                                .'ويتحقق من الحقول المطلوبة'
                            ),
                            'estimated_hours' => 2.0,
                            'skill' => 'Flask',
                        ],
                        [
                            'title' => (
                                'اختبار حالات الإدخال الصحيح والخاطئ '
                                .'ومراجعة Status Codes والـResponses'
                            ),
                            'estimated_hours' => 1.5,
                            'skill' => 'Flask',
                        ],
                    ],
                    'resources' => [
                        $flaskApi,
                    ],
                    'expected_outcome' => (
                        'القدرة على بناء POST endpoint يتعامل '
                        .'مع JSON وValidation واستجابات API واضحة.'
                    ),
                ],

                [
                    'week_number' => 4,
                    'focus_skills' => ['Flask'],
                    'goals' => [
                        'تنظيم التطبيق باستخدام Blueprints.',
                        'تحسين Error Handling في واجهات الـAPI.',
                    ],
                    'tasks' => [
                        [
                            'title' => (
                                'دراسة مفهوم Blueprints وتقسيم '
                                .'Routes إلى مكونات منظمة'
                            ),
                            'estimated_hours' => 1.5,
                            'skill' => 'Flask',
                        ],
                        [
                            'title' => (
                                'دراسة Error Handlers وإرجاع '
                                .'أخطاء API بصيغة JSON'
                            ),
                            'estimated_hours' => 1.5,
                            'skill' => 'Flask',
                        ],
                        [
                            'title' => (
                                'إعادة هيكلة Mini API باستخدام '
                                .'Blueprints وإضافة Error Handling '
                                .'ثم اختبار السيناريو كاملاً'
                            ),
                            'estimated_hours' => 2.0,
                            'skill' => 'Flask',
                        ],
                    ],
                    'resources' => [
                        $flaskBlueprints,
                        $flaskErrors,
                    ],
                    'expected_outcome' => (
                        'القدرة على تنظيم Flask API بشكل أوضح '
                        .'والتعامل مع الأخطاء باستجابات مناسبة.'
                    ),
                ],
            ],
            'final_outcome_ar' => (
                'بعد إكمال الخطة يُتوقع أن تصبح الطالبة أكثر جاهزية '
                .'للعمل التعاوني باستخدام Git، وقادرة على بناء '
                .'Flask API صغيرة ومنظمة تتعامل مع JSON والتحقق '
                .'من المدخلات والأخطاء بشكل أفضل.'
            ),
        ];
    }

    private function validatePreparedPlan(
        array $plan,
        array $learningPath
    ): void {
        if (
            ($plan['career_path'] ?? null)
            !== self::CAREER_PATH_NAME
        ) {
            throw new RuntimeException(
                'Prepared plan career path is invalid.'
            );
        }

        $weeks = $plan['weeks'] ?? [];

        if (count($weeks) !== self::WEEKS) {
            throw new RuntimeException(
                'Prepared plan must contain exactly 4 weeks.'
            );
        }

        $allowedSkills = collect($learningPath)
            ->pluck('skill_name')
            ->filter()
            ->values()
            ->all();

        $allowedUrls = collect($learningPath)
            ->flatMap(
                fn ($item) => $item['resources'] ?? []
            )
            ->pluck('url')
            ->filter()
            ->unique()
            ->values()
            ->all();

        foreach ($weeks as $index => $week) {
            $expectedWeekNumber = $index + 1;

            if (
                (int) ($week['week_number'] ?? 0)
                !== $expectedWeekNumber
            ) {
                throw new RuntimeException(
                    "Invalid week number at index {$index}."
                );
            }

            $focusSkills = $week['focus_skills'] ?? [];

            foreach ($focusSkills as $skillName) {
                if (
                    ! in_array(
                        $skillName,
                        $allowedSkills,
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Prepared plan uses an unsupported '
                        ."skill: {$skillName}."
                    );
                }
            }

            $taskHours = collect($week['tasks'] ?? [])
                ->sum(
                    fn ($task) => (
                        (float) (
                            $task['estimated_hours'] ?? 0
                        )
                    )
                );

            if (
                abs(
                    $taskHours - self::HOURS_PER_WEEK
                ) > 0.001
            ) {
                throw new RuntimeException(
                    "Week {$expectedWeekNumber} must total "
                    .self::HOURS_PER_WEEK
                    ." task hours; got {$taskHours}."
                );
            }

            foreach (
                $week['resources'] ?? [] as $resource
            ) {
                $url = $resource['url'] ?? null;

                if (
                    ! is_string($url)
                    || ! in_array($url, $allowedUrls, true)
                ) {
                    throw new RuntimeException(
                        "Week {$expectedWeekNumber} uses a "
                        .'resource that was not provided by '
                        .'LearningPathService.'
                    );
                }
            }
        }
    }

    private function verifyPersistedPlan(
        AILearningPlan $plan,
        AssessmentSession $session
    ): void {
        $fresh = AILearningPlan::query()
            ->find($plan->AILearningPlanID);

        if (! $fresh) {
            throw new RuntimeException(
                'Prepared AI learning plan was not persisted.'
            );
        }

        if (
            (int) $fresh->AssessmentSessionID
            !== (int) $session->AssessmentSessionID
        ) {
            throw new RuntimeException(
                'Persisted plan assessment-session mismatch.'
            );
        }

        if (
            (int) $fresh->Weeks !== self::WEEKS
            || (int) $fresh->HoursPerWeek
                !== self::HOURS_PER_WEEK
        ) {
            throw new RuntimeException(
                'Persisted plan duration mismatch.'
            );
        }

        if (
            $fresh->AiModelVersion
            !== self::DEMO_MODEL_VERSION
        ) {
            throw new RuntimeException(
                'Prepared-plan provenance marker is missing.'
            );
        }

        $latest = AILearningPlan::query()
            ->where(
                'AssessmentSessionID',
                $session->AssessmentSessionID
            )
            ->latest('AILearningPlanID')
            ->first();

        if (
            ! $latest
            || (int) $latest->AILearningPlanID
                !== (int) $fresh->AILearningPlanID
        ) {
            throw new RuntimeException(
                'Prepared plan is not the latest plan that the API '
                .'would return for this assessment session.'
            );
        }
    }

    private function printSummary(
        AILearningPlan $plan,
        array $learningPath
    ): void {
        $this->command?->newLine();
        $this->command?->info(
            'Prepared graduation AI learning plan seeded successfully.'
        );

        $this->command?->line(
            'Student: '.self::STUDENT_EMAIL
            .' | Assessment Session #'
            .$plan->AssessmentSessionID
            .' | Learning Plan #'
            .$plan->AILearningPlanID
        );

        $this->command?->line(
            'Duration: '.self::WEEKS
            .' weeks × '.self::HOURS_PER_WEEK
            .' hours/week = '
            .(self::WEEKS * self::HOURS_PER_WEEK)
            .' planned hours'
        );

        $rankingRows = collect($learningPath)
            ->map(function (array $item, int $index): array {
                return [
                    $index + 1,
                    $item['skill_name'] ?? '-',
                    $item['current_level'] ?? '-',
                    $item['target_level'] ?? '-',
                    $item['priority'] ?? '-',
                    (
                        $item['market']['demand_score']
                        ?? 'N/A'
                    ),
                    count($item['resources'] ?? []),
                ];
            })
            ->all();

        $this->command?->table(
            [
                'Rank',
                'Skill',
                'Current',
                'Target',
                'Gap priority',
                'Market %',
                'Resources',
            ],
            $rankingRows
        );

        $weekRows = collect(
            $plan->PlanJson['weeks'] ?? []
        )->map(function (array $week): array {
            return [
                $week['week_number'],
                implode(
                    ', ',
                    $week['focus_skills'] ?? []
                ),
                collect($week['tasks'] ?? [])
                    ->sum(
                        fn ($task) => (
                            (float) (
                                $task['estimated_hours']
                                ?? 0
                            )
                        )
                    ),
                count($week['resources'] ?? []),
            ];
        })->all();

        $this->command?->table(
            [
                'Week',
                'Focus',
                'Task hours',
                'Resources used',
            ],
            $weekRows
        );

        $this->command?->warn(
            'No Gemini call was executed. AiModelVersion is '
            .self::DEMO_MODEL_VERSION
            .' so the database does not falsely claim a live AI run.'
        );

        $this->command?->info(
            'The latest-plan API can now return this prepared '
            .'record for the assessment session.'
        );
    }
}
