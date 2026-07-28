<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class GitExpertRulesSeeder extends Seeder
{
    use ResolvesExpertQuestionsByTopic;

    private const SKILL_NAME = 'Git';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->guardNoExistingAttempts();

            $now = now();

            foreach ($this->definitions() as $definition) {
                $this->syncQuestion(
                    definition: $definition,
                    now: $now,
                );
            }
        });

        if ($this->command) {
            $this->command->info(
                'Git Expert Rules data was seeded successfully.'
            );
        }
    }

    private function guardNoExistingAttempts(): void
    {
        $questionIds = $this->expertQuestionIdsByTopics(
            skillName: self::SKILL_NAME,
            topics: array_column($this->definitions(), 'topic'),
        );

        $attemptCount = DB::table('assessment_question_attempts')
            ->whereIn('QuestionID', $questionIds)
            ->count();

        if ($attemptCount > 0) {
            throw new RuntimeException(
                'Cannot seed Git questions because one or more '
                .'questions already have assessment attempts. '
                .'Create a new Rule Set version instead of replacing rules.'
            );
        }
    }

    private function syncQuestion(
        array $definition,
        $now,
    ): void {
        $question = $this->resolveExpertQuestionByTopic(
            skillName: self::SKILL_NAME,
            topic: $definition['topic'],
        );

        $questionId = (int) $question->QuestionID;

        $this->clearQuestionStructure($questionId);

        DB::table('question_bank')
            ->where('QuestionID', $questionId)
            ->update([
                'QuestionText' => $definition['question_text'],
                'Topic' => $definition['topic'],
                'EvaluationEngine' => 'expert_rules',
                'RuleSetVersion' => 'v1',
                'IsExpertReady' => false,
                'updated_at' => $now,
            ]);

        $conceptIds = [];

        foreach ($definition['concepts'] as $concept) {
            $conceptIds[$concept['code']] = $this->ensureConcept(
                concept: $concept,
                now: $now,
            );
        }

        $rubricIds = [];

        foreach ($definition['rubrics'] as $rubric) {
            $rubricIds[$rubric['code']] = DB::table('question_rubrics')
                ->insertGetId([
                    'QuestionID' => $questionId,
                    'CriterionCode' => $rubric['code'],
                    'CriterionName' => $rubric['name_ar'],
                    'CriterionDescription' => $rubric['description_ar'],
                    'MaxScore' => $rubric['max_score'],
                    'Weight' => 1.00,
                    'KeywordsJson' => $this->json(
                        $rubric['requires']
                    ),
                    'SampleGoodAnswer' => $rubric['sample_good'],
                    'SampleBadAnswer' => $rubric['sample_bad'],
                    'FeedbackOnPass' => $rubric['feedback_pass'],
                    'FeedbackOnFail' => $rubric['feedback_fail'],
                    'OrderIndex' => $rubric['order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'QuestionRubricID');
        }

        $ruleSetId = DB::table('assessment_rule_sets')
            ->insertGetId([
                'QuestionID' => $questionId,
                'RuleSetCode' => $definition['rule_set_code'],
                'Version' => 'v1',
                'Status' => 'active',
                'CreatedByUserId' => null,
                'ActivatedAt' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'RuleSetID');

        foreach ($definition['rubrics'] as $rubric) {
            DB::table('criterion_rules')->insert([
                'RuleSetID' => $ruleSetId,
                'QuestionRubricID' => $rubricIds[$rubric['code']],
                'RuleCode' => $rubric['code'].'_FULL',
                'RuleType' => 'award_full',
                'Priority' => 10,
                'ConditionsJson' => $this->json([
                    'all' => array_map(
                        fn (string $conceptCode): array => [
                            'concept' => $conceptCode,
                            'expected' => true,
                            'not_negated' => true,
                        ],
                        $rubric['requires']
                    ),
                    'none' => array_map(
                        fn (string $conceptCode): array => [
                            'concept' => $conceptCode,
                            'expected' => true,
                        ],
                        $rubric['blocked_by']
                    ),
                ]),
                'AwardScore' => $rubric['max_score'],
                'FeedbackTemplate' => $rubric['feedback_pass'],
                'IsActive' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($definition['contradictions'] as $contradiction) {
            $triggerConcept = $contradiction['trigger_concept'];

            if (! isset($conceptIds[$triggerConcept])) {
                throw new RuntimeException(
                    "Unknown contradiction concept: {$triggerConcept}"
                );
            }

            $contradictionRuleId = DB::table(
                'assessment_contradiction_rules'
            )->insertGetId([
                'RuleSetID' => $ruleSetId,
                'TriggerConceptID' => $conceptIds[$triggerConcept],
                'Code' => $contradiction['code'],
                'Severity' => 'block_criterion',
                'FeedbackAr' => $contradiction['feedback_ar'],
                'IsActive' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'ContradictionRuleID');

            foreach ($contradiction['blocked_rubrics'] as $rubricCode) {
                if (! isset($rubricIds[$rubricCode])) {
                    throw new RuntimeException(
                        "Unknown rubric code: {$rubricCode}"
                    );
                }

                DB::table(
                    'assessment_contradiction_rule_rubrics'
                )->insert([
                    'ContradictionRuleID' => $contradictionRuleId,
                    'QuestionRubricID' => $rubricIds[$rubricCode],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function clearQuestionStructure(int $questionId): void
    {
        $ruleSetIds = DB::table('assessment_rule_sets')
            ->where('QuestionID', $questionId)
            ->pluck('RuleSetID')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! empty($ruleSetIds)) {
            $contradictionRuleIds = DB::table(
                'assessment_contradiction_rules'
            )
                ->whereIn('RuleSetID', $ruleSetIds)
                ->pluck('ContradictionRuleID')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! empty($contradictionRuleIds)) {
                DB::table('assessment_contradiction_rule_rubrics')
                    ->whereIn(
                        'ContradictionRuleID',
                        $contradictionRuleIds
                    )
                    ->delete();

                DB::table('assessment_contradiction_rules')
                    ->whereIn(
                        'ContradictionRuleID',
                        $contradictionRuleIds
                    )
                    ->delete();
            }

            DB::table('criterion_rules')
                ->whereIn('RuleSetID', $ruleSetIds)
                ->delete();

            DB::table('assessment_rule_sets')
                ->whereIn('RuleSetID', $ruleSetIds)
                ->delete();
        }

        DB::table('question_rubrics')
            ->where('QuestionID', $questionId)
            ->delete();
    }

    private function ensureConcept(
        array $concept,
        $now,
    ): int {
        $existingId = DB::table('assessment_concepts')
            ->where('ConceptCode', $concept['code'])
            ->value('ConceptID');

        if ($existingId) {
            DB::table('assessment_concepts')
                ->where('ConceptID', $existingId)
                ->update([
                    'NameAr' => $concept['name_ar'],
                    'NameEn' => $concept['name_en'],
                    'Description' => $concept['description_ar'],
                    'ClaimAr' => $concept['claim_ar'],
                    'ClaimEn' => $concept['claim_en'],
                    'IsActive' => true,
                    'updated_at' => $now,
                ]);

            return (int) $existingId;
        }

        return (int) DB::table('assessment_concepts')
            ->insertGetId([
                'ConceptCode' => $concept['code'],
                'NameAr' => $concept['name_ar'],
                'NameEn' => $concept['name_en'],
                'Description' => $concept['description_ar'],
                'ClaimAr' => $concept['claim_ar'],
                'ClaimEn' => $concept['claim_en'],
                'IsActive' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'ConceptID');
    }

    private function json(array $data): string
    {
        try {
            return json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new LogicException(
                'Unable to encode Expert Rules JSON.',
                previous: $exception
            );
        }
    }

    private function definitions(): array
    {
        $definitionsJson = <<<'JSON'
[
    {
        "question_text": "ما فائدة Git بشكل عام؟",
        "topic": "git_general_purpose",
        "rule_set_code": "GIT_GENERAL_PURPOSE_V1",
        "concepts": [
            {
                "code": "git_tracks_changes",
                "name_ar": "تتبّع التغييرات",
                "name_en": "Tracks changes",
                "description_ar": "يوضح أن Git يتتبع تغييرات الملفات بمرور الوقت.",
                "claim_ar": "يذكر الطالب أن Git يتتبع التغييرات التي تحدث على الملفات أو المشروع.",
                "claim_en": "The student states that Git tracks changes made to files or a project."
            },
            {
                "code": "git_records_history_versions",
                "name_ar": "حفظ التاريخ والإصدارات",
                "name_en": "Records history and versions",
                "description_ar": "يوضح أنه يحفظ سجل الإصدارات ويمكن الرجوع لحالة سابقة.",
                "claim_ar": "يذكر الطالب أن Git يحفظ تاريخ الإصدارات أو يسمح بالرجوع إلى نسخة سابقة.",
                "claim_en": "The student states that Git stores version history or allows returning to a previous version."
            },
            {
                "code": "git_supports_collaboration",
                "name_ar": "دعم العمل الجماعي",
                "name_en": "Supports collaboration",
                "description_ar": "يوضح أنه يساعد عدة مطورين على العمل على المشروع.",
                "claim_ar": "يذكر الطالب أن Git يساعد الفريق على التعاون أو مشاركة التغييرات وتنظيمها.",
                "claim_en": "The student states that Git helps a team collaborate or share and organize changes."
            },
            {
                "code": "git_not_tracks_changes_claim",
                "name_ar": "ادعاء خاطئ بعدم تتبع التغييرات",
                "name_en": "Claims Git does not track changes",
                "description_ar": "ادعاء خاطئ بأن Git لا يتتبع التغييرات أو لا يحتفظ بتاريخ.",
                "claim_ar": "يدعي الطالب أن Git لا يتتبع التغييرات أو لا يحفظ تاريخ الإصدارات.",
                "claim_en": "The student claims Git does not track changes or does not keep version history."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_PURPOSE_TRACKING",
                "name_ar": "تتبّع التغييرات",
                "description_ar": "يوضح أن Git يتتبع تغييرات المشروع.",
                "max_score": 2,
                "requires": [
                    "git_tracks_changes"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن Git يتتبع التغييرات في الملفات والمشروع.",
                "order": 1
            },
            {
                "code": "GIT_PURPOSE_HISTORY",
                "name_ar": "تاريخ الإصدارات",
                "description_ar": "يوضح حفظ التاريخ والقدرة على الرجوع.",
                "max_score": 2,
                "requires": [
                    "git_records_history_versions"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر حفظ تاريخ الإصدارات أو إمكانية الرجوع إلى نسخة سابقة.",
                "order": 2
            },
            {
                "code": "GIT_PURPOSE_COLLABORATION",
                "name_ar": "العمل الجماعي",
                "description_ar": "يوضح دعم التعاون بين المطورين.",
                "max_score": 1,
                "requires": [
                    "git_supports_collaboration"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر كيف يساعد Git عدة مطورين على التعاون ومشاركة التغييرات.",
                "order": 3
            }
        ],
        "contradictions": [
            {
                "code": "GIT_PURPOSE_CONFLICT_NO_TRACKING",
                "trigger_concept": "git_not_tracks_changes_claim",
                "feedback_ar": "Git نظام تحكم بالإصدارات: وظيفته الأساسية تتبع التغييرات وحفظ تاريخها، كما يساعد الفريق على التعاون.",
                "blocked_rubrics": [
                    "GIT_PURPOSE_TRACKING",
                    "GIT_PURPOSE_HISTORY",
                    "GIT_PURPOSE_COLLABORATION"
                ]
            }
        ]
    },
    {
        "question_text": "ما الفرق بين git add و git commit؟",
        "topic": "git_add_vs_commit",
        "rule_set_code": "GIT_ADD_COMMIT_V1",
        "concepts": [
            {
                "code": "git_add_stages_changes",
                "name_ar": "إضافة التغييرات إلى staging",
                "name_en": "Stages changes with git add",
                "description_ar": "يوضح أن git add يجهز تغييرات محددة في منطقة staging.",
                "claim_ar": "يذكر الطالب أن git add يضيف أو يجهز التغييرات إلى staging area قبل الحفظ.",
                "claim_en": "The student states that git add stages selected changes in the staging area before saving."
            },
            {
                "code": "git_commit_records_staged_snapshot",
                "name_ar": "حفظ التغييرات المجهزة",
                "name_en": "Commits staged snapshot",
                "description_ar": "يوضح أن git commit يحفظ التغييرات المجهزة كسجل في تاريخ المستودع المحلي.",
                "claim_ar": "يذكر الطالب أن git commit يسجل التغييرات الموجودة في staging كسجل أو snapshot في التاريخ المحلي.",
                "claim_en": "The student states that git commit records the staged changes as a snapshot in local history."
            },
            {
                "code": "git_add_then_commit_sequence",
                "name_ar": "تسلسل add ثم commit",
                "name_en": "Add then commit sequence",
                "description_ar": "يوضح أن add يسبق commit وأنهما مرحلتان مختلفتان.",
                "claim_ar": "يوضح الطالب أن git add يختار ما سيتم تضمينه ثم git commit يحفظه في التاريخ.",
                "claim_en": "The student explains that git add chooses what to include, then git commit stores it in history."
            },
            {
                "code": "git_add_commit_identical_claim",
                "name_ar": "ادعاء تطابق add وcommit",
                "name_en": "Claims add and commit are identical",
                "description_ar": "ادعاء خاطئ بأن git add وgit commit يقومان بالعمل نفسه.",
                "claim_ar": "يدعي الطالب أن git add وgit commit متطابقان أو أن أيًا منهما يحفظ التغييرات مباشرة في التاريخ نفسه.",
                "claim_en": "The student claims git add and git commit are identical or either saves changes directly in the same history."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_ADD_STAGE",
                "name_ar": "وظيفة git add",
                "description_ar": "يوضح تجهيز التغييرات في staging.",
                "max_score": 2,
                "requires": [
                    "git_add_stages_changes"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن git add يجهز تغييرات محددة في staging area.",
                "order": 1
            },
            {
                "code": "GIT_COMMIT_RECORD",
                "name_ar": "وظيفة git commit",
                "description_ar": "يوضح تسجيل التغييرات المجهزة في التاريخ.",
                "max_score": 2,
                "requires": [
                    "git_commit_records_staged_snapshot"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن git commit يحفظ التغييرات المجهزة كسجل في تاريخ المستودع المحلي.",
                "order": 2
            },
            {
                "code": "GIT_ADD_COMMIT_DIFFERENCE",
                "name_ar": "الفرق والتسلسل",
                "description_ar": "يوضح اختلاف المرحلتين وتسلسلهما.",
                "max_score": 1,
                "requires": [
                    "git_add_then_commit_sequence"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن add يسبق commit: يختار التغييرات ثم يسجلها.",
                "order": 3
            }
        ],
        "contradictions": [
            {
                "code": "GIT_ADD_COMMIT_CONFLICT_IDENTICAL",
                "trigger_concept": "git_add_commit_identical_claim",
                "feedback_ar": "git add وgit commit مرحلتان مختلفتان: add يجهز التغييرات، وcommit يسجل ما تم تجهيزه في تاريخ المستودع.",
                "blocked_rubrics": [
                    "GIT_ADD_STAGE",
                    "GIT_COMMIT_RECORD",
                    "GIT_ADD_COMMIT_DIFFERENCE"
                ]
            }
        ]
    },
    {
        "question_text": "ما وظيفة git push؟",
        "topic": "git_push",
        "rule_set_code": "GIT_PUSH_V1",
        "concepts": [
            {
                "code": "git_push_sends_local_commits_remote",
                "name_ar": "إرسال commits إلى remote",
                "name_en": "Sends local commits to remote",
                "description_ar": "يوضح أن git push يرسل commits المحلية إلى مستودع بعيد.",
                "claim_ar": "يذكر الطالب أن git push يرفع أو يرسل commits المحلية إلى remote repository مثل GitHub.",
                "claim_en": "The student states that git push uploads local commits to a remote repository such as GitHub."
            },
            {
                "code": "git_push_updates_remote_branch",
                "name_ar": "تحديث الفرع البعيد",
                "name_en": "Updates remote branch",
                "description_ar": "يوضح أنه يحدث الفرع المقابل في المستودع البعيد.",
                "claim_ar": "يذكر الطالب أن git push يحدث remote branch أو يجعل التغييرات متاحة على الفرع البعيد.",
                "claim_en": "The student states that git push updates the remote branch or makes changes available on it."
            },
            {
                "code": "git_push_supports_sharing",
                "name_ar": "مشاركة التغييرات",
                "name_en": "Shares changes with team",
                "description_ar": "يوضح أنه يسمح بمشاركة العمل مع الفريق بعد commit.",
                "claim_ar": "يذكر الطالب أن push يجعل التغييرات التي تم عمل commit لها متاحة لباقي الفريق.",
                "claim_en": "The student states that push makes committed changes available to the rest of the team."
            },
            {
                "code": "git_push_local_only_claim",
                "name_ar": "ادعاء أن push محلي فقط",
                "name_en": "Claims push is local only",
                "description_ar": "ادعاء خاطئ بأن push لا يتعامل مع remote أو أنه يحفظ محليًا فقط.",
                "claim_ar": "يدعي الطالب أن git push يحفظ التغييرات محليًا فقط ولا يرسلها إلى remote repository.",
                "claim_en": "The student claims git push saves changes only locally and does not send them to a remote repository."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_PUSH_REMOTE",
                "name_ar": "إرسال commits للـremote",
                "description_ar": "يوضح إرسال commits المحلية إلى remote.",
                "max_score": 2,
                "requires": [
                    "git_push_sends_local_commits_remote"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن git push يرسل commits المحلية إلى remote repository.",
                "order": 1
            },
            {
                "code": "GIT_PUSH_BRANCH",
                "name_ar": "تحديث الفرع البعيد",
                "description_ar": "يوضح تحديث remote branch.",
                "max_score": 2,
                "requires": [
                    "git_push_updates_remote_branch"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر أن push يحدث الفرع المقابل في المستودع البعيد.",
                "order": 2
            },
            {
                "code": "GIT_PUSH_COLLABORATION",
                "name_ar": "مشاركة العمل",
                "description_ar": "يوضح إتاحة التغييرات للفريق.",
                "max_score": 1,
                "requires": [
                    "git_push_supports_sharing"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر أن push يجعل التغييرات بعد commit متاحة للفريق.",
                "order": 3
            }
        ],
        "contradictions": [
            {
                "code": "GIT_PUSH_CONFLICT_LOCAL_ONLY",
                "trigger_concept": "git_push_local_only_claim",
                "feedback_ar": "git push يرسل commits من المستودع المحلي إلى remote repository ويحدث الفرع البعيد، وليس حفظًا محليًا فقط.",
                "blocked_rubrics": [
                    "GIT_PUSH_REMOTE",
                    "GIT_PUSH_BRANCH",
                    "GIT_PUSH_COLLABORATION"
                ]
            }
        ]
    },
    {
        "question_text": "لماذا نستخدم branches في Git؟",
        "topic": "git_branches",
        "rule_set_code": "GIT_BRANCHES_V1",
        "concepts": [
            {
                "code": "git_branch_isolates_work",
                "name_ar": "عزل العمل",
                "name_en": "Isolates work",
                "description_ar": "يوضح أن الفرع يعزل تطوير ميزة أو إصلاح عن main.",
                "claim_ar": "يذكر الطالب أن branches تعزل العمل على ميزة أو إصلاح بعيدًا عن الفرع الرئيسي.",
                "claim_en": "The student states that branches isolate feature or fix work away from the main branch."
            },
            {
                "code": "git_branch_supports_parallel_work",
                "name_ar": "عمل متوازٍ",
                "name_en": "Supports parallel work",
                "description_ar": "يوضح أن الفروع تسمح لعدة أشخاص أو ميزات بالعمل بالتوازي.",
                "claim_ar": "يذكر الطالب أن branches تسمح بتطوير ميزات متعددة أو عمل عدة مطورين بالتوازي.",
                "claim_en": "The student states that branches allow multiple features or developers to work in parallel."
            },
            {
                "code": "git_branch_protects_main",
                "name_ar": "حماية الاستقرار",
                "name_en": "Protects main stability",
                "description_ar": "يوضح أن main يبقى مستقرًا حتى مراجعة ودمج العمل.",
                "claim_ar": "يذكر الطالب أن branches تحافظ على استقرار main إلى أن تتم مراجعة العمل ودمجه.",
                "claim_en": "The student states that branches keep main stable until work is reviewed and merged."
            },
            {
                "code": "git_branch_no_isolation_claim",
                "name_ar": "ادعاء أن الفروع لا تعزل",
                "name_en": "Claims branches do not isolate",
                "description_ar": "ادعاء خاطئ بأن كل العمل يجب أن يكون على main أو أن branches لا تفيد في العزل.",
                "claim_ar": "يدعي الطالب أن كل العمل يجب أن يتم مباشرة على main أو أن branches لا تعزل العمل.",
                "claim_en": "The student claims all work should happen directly on main or branches do not isolate work."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_BRANCH_ISOLATION",
                "name_ar": "عزل الميزة أو الإصلاح",
                "description_ar": "يوضح عزل العمل عن main.",
                "max_score": 2,
                "requires": [
                    "git_branch_isolates_work"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن branch يعزل تطوير ميزة أو إصلاح عن main.",
                "order": 1
            },
            {
                "code": "GIT_BRANCH_PARALLEL",
                "name_ar": "العمل المتوازي",
                "description_ar": "يوضح دعم تطوير ميزات أو مطورين بالتوازي.",
                "max_score": 2,
                "requires": [
                    "git_branch_supports_parallel_work"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر أن branches تدعم عمل ميزات أو مطورين بالتوازي.",
                "order": 2
            },
            {
                "code": "GIT_BRANCH_MAIN_STABILITY",
                "name_ar": "استقرار main",
                "description_ar": "يوضح حماية الفرع الرئيسي حتى المراجعة والدمج.",
                "max_score": 1,
                "requires": [
                    "git_branch_protects_main"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر أن main يبقى أكثر استقرارًا إلى أن يراجع العمل ويدمج.",
                "order": 3
            }
        ],
        "contradictions": [
            {
                "code": "GIT_BRANCH_CONFLICT_NO_ISOLATION",
                "trigger_concept": "git_branch_no_isolation_claim",
                "feedback_ar": "الفروع تستخدم لعزل العمل والسماح بالتوازي مع الحفاظ على استقرار main حتى تتم المراجعة والدمج.",
                "blocked_rubrics": [
                    "GIT_BRANCH_ISOLATION",
                    "GIT_BRANCH_PARALLEL",
                    "GIT_BRANCH_MAIN_STABILITY"
                ]
            }
        ]
    },
    {
        "question_text": "اشرح بشكل مبسط ما الذي يفعله git merge.",
        "topic": "git_merge",
        "rule_set_code": "GIT_MERGE_V1",
        "concepts": [
            {
                "code": "git_merge_integrates_branches",
                "name_ar": "دمج تغييرات الفروع",
                "name_en": "Integrates branch changes",
                "description_ar": "يوضح أن merge يدمج تغييرات فرع في فرع آخر.",
                "claim_ar": "يذكر الطالب أن git merge يدمج تغييرات أو commits من branch إلى branch آخر مثل main.",
                "claim_en": "The student states that git merge integrates changes or commits from one branch into another such as main."
            },
            {
                "code": "git_merge_combines_history",
                "name_ar": "جمع التاريخ",
                "name_en": "Combines history",
                "description_ar": "يوضح أن الدمج يجمع تاريخ التغييرات أو ينشئ merge commit عند الحاجة.",
                "claim_ar": "يذكر الطالب أن merge يجمع تاريخ الفرعين أو قد ينشئ merge commit عند الحاجة.",
                "claim_en": "The student states that merge combines branch histories or may create a merge commit when needed."
            },
            {
                "code": "git_merge_can_require_conflict_resolution",
                "name_ar": "قد يحتاج حل تعارض",
                "name_en": "May require conflict resolution",
                "description_ar": "يوضح أن التعارض يمكن أن يحدث ويجب حله قبل إتمام الدمج.",
                "claim_ar": "يذكر الطالب أن merge قد ينتج conflict ويحتاج حل التعارض ثم إتمام الدمج.",
                "claim_en": "The student states that merge may create a conflict that must be resolved before completion."
            },
            {
                "code": "git_merge_deletes_branch_claim",
                "name_ar": "ادعاء أن merge يحذف فرعًا",
                "name_en": "Claims merge deletes branch",
                "description_ar": "ادعاء خاطئ بأن merge يحذف الفرع أو يستبدل تغييرات عشوائيًا دون دمج.",
                "claim_ar": "يدعي الطالب أن git merge يحذف branch الآخر أو يلغي تغييراته بدل دمجها.",
                "claim_en": "The student claims git merge deletes the other branch or discards its changes instead of integrating them."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_MERGE_INTEGRATE",
                "name_ar": "دمج التغييرات",
                "description_ar": "يوضح دمج فرع في آخر.",
                "max_score": 2,
                "requires": [
                    "git_merge_integrates_branches"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن git merge يدمج تغييرات أو commits من فرع إلى آخر.",
                "order": 1
            },
            {
                "code": "GIT_MERGE_HISTORY",
                "name_ar": "تاريخ الدمج",
                "description_ar": "يوضح جمع تاريخ العمل أو merge commit.",
                "max_score": 2,
                "requires": [
                    "git_merge_combines_history"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر أن merge يجمع التاريخ أو قد ينشئ merge commit.",
                "order": 2
            },
            {
                "code": "GIT_MERGE_CONFLICTS",
                "name_ar": "التعامل مع التعارض",
                "description_ar": "يوضح احتمال التعارض وحله.",
                "max_score": 1,
                "requires": [
                    "git_merge_can_require_conflict_resolution"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر أن التعارض قد يحتاج حله قبل اكتمال merge.",
                "order": 3
            }
        ],
        "contradictions": [
            {
                "code": "GIT_MERGE_CONFLICT_DELETES_BRANCH",
                "trigger_concept": "git_merge_deletes_branch_claim",
                "feedback_ar": "git merge يدمج تغييرات الفروع في فرع هدف؛ لا يعني حذف الفرع الآخر أو إلغاء عمله عشوائيًا.",
                "blocked_rubrics": [
                    "GIT_MERGE_INTEGRATE",
                    "GIT_MERGE_HISTORY",
                    "GIT_MERGE_CONFLICTS"
                ]
            }
        ]
    },
    {
        "question_text": "ما المقصود بـ merge conflict؟ وكيف تتعامل معه بشكل مبدئي؟",
        "topic": "git_merge_conflict",
        "rule_set_code": "GIT_MERGE_CONFLICT_V1",
        "concepts": [
            {
                "code": "git_conflict_incompatible_changes",
                "name_ar": "تغييرات لا يمكن دمجها تلقائيًا",
                "name_en": "Incompatible changes",
                "description_ar": "يوضح أن التعارض يحدث عندما لا يستطيع Git دمج تغييرات متعارضة تلقائيًا.",
                "claim_ar": "يذكر الطالب أن merge conflict يحدث عندما تكون تغييرات الفروع غير متوافقة أو لا يستطيع Git دمجها تلقائيًا.",
                "claim_en": "The student states that a merge conflict occurs when branch changes are incompatible or Git cannot merge them automatically."
            },
            {
                "code": "git_conflict_inspect_markers_choose_content",
                "name_ar": "فحص markers واختيار المحتوى",
                "name_en": "Inspects markers and chooses content",
                "description_ar": "يوضح فحص علامات التعارض ومقارنة النسخ لتحديد المحتوى الصحيح.",
                "claim_ar": "يذكر الطالب أنه يفتح الملفات ويقرأ conflict markers ويقرر أو يدمج المحتوى الصحيح.",
                "claim_en": "The student states that they open files, inspect conflict markers, and choose or merge the correct content."
            },
            {
                "code": "git_conflict_test_stage_commit",
                "name_ar": "اختبار ثم add وcommit",
                "name_en": "Tests then stages and commits",
                "description_ar": "يوضح اختبار الحل ثم git add وcommit لإكمال الدمج.",
                "claim_ar": "يذكر الطالب أنه بعد الحل يختبر ثم يستخدم git add وgit commit لإتمام merge.",
                "claim_en": "The student states that after resolving, they test then use git add and git commit to finish the merge."
            },
            {
                "code": "git_conflict_delete_randomly_claim",
                "name_ar": "ادعاء حذف عشوائي",
                "name_en": "Claims random deletion solves conflict",
                "description_ar": "ادعاء خاطئ بأن حذف أحد الجانبين عشوائيًا أو تجاهل التعارض هو حل صحيح.",
                "claim_ar": "يدعي الطالب أن حذف أحد جانبي التعارض عشوائيًا أو تجاهله يكفي دون مراجعة أو اختبار.",
                "claim_en": "The student claims randomly deleting one side of a conflict or ignoring it is sufficient without review or testing."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_CONFLICT_DEFINITION",
                "name_ar": "تعريف merge conflict",
                "description_ar": "يوضح عدم قدرة Git على الدمج التلقائي.",
                "max_score": 2,
                "requires": [
                    "git_conflict_incompatible_changes"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن التعارض يحدث عند تغييرات غير متوافقة لا يستطيع Git دمجها تلقائيًا.",
                "order": 1
            },
            {
                "code": "GIT_CONFLICT_RESOLVE_CONTENT",
                "name_ar": "حل المحتوى",
                "description_ar": "يوضح فحص markers واختيار المحتوى الصحيح.",
                "max_score": 2,
                "requires": [
                    "git_conflict_inspect_markers_choose_content"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر فحص conflict markers ومقارنة المحتوى قبل اختيار الحل.",
                "order": 2
            },
            {
                "code": "GIT_CONFLICT_VERIFY_COMMIT",
                "name_ar": "التحقق وإتمام الدمج",
                "description_ar": "يوضح الاختبار ثم add وcommit.",
                "max_score": 1,
                "requires": [
                    "git_conflict_test_stage_commit"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر الاختبار ثم git add وgit commit لإكمال الدمج.",
                "order": 3
            }
        ],
        "contradictions": [
            {
                "code": "GIT_CONFLICT_CONFLICT_RANDOM_DELETE",
                "trigger_concept": "git_conflict_delete_randomly_claim",
                "feedback_ar": "لا يُحل merge conflict بحذف أحد الجانبين عشوائيًا؛ يجب فهم التغييرات واختيار الحل الصحيح ثم اختباره وإتمامه.",
                "blocked_rubrics": [
                    "GIT_CONFLICT_DEFINITION",
                    "GIT_CONFLICT_RESOLVE_CONTENT",
                    "GIT_CONFLICT_VERIFY_COMMIT"
                ]
            }
        ]
    },
    {
        "question_text": "ما الفكرة العامة وراء Pull Request أو Merge Request في العمل الجماعي؟",
        "topic": "git_pull_request",
        "rule_set_code": "GIT_PULL_REQUEST_V1",
        "concepts": [
            {
                "code": "git_pr_proposes_changes_for_review",
                "name_ar": "طلب مراجعة التغييرات",
                "name_en": "Proposes changes for review",
                "description_ar": "يوضح أن PR/MR يقترح دمج تغييرات فرع لمراجعتها.",
                "claim_ar": "يذكر الطالب أن Pull Request أو Merge Request هو طلب لدمج تغييرات branch بعد مراجعتها.",
                "claim_en": "The student states that a Pull Request or Merge Request is a request to merge branch changes after review."
            },
            {
                "code": "git_pr_discussion_review_feedback",
                "name_ar": "مناقشة ومراجعة",
                "name_en": "Discussion and review feedback",
                "description_ar": "يوضح أن الفريق يناقش الكود ويقدم ملاحظات قبل الدمج.",
                "claim_ar": "يذكر الطالب أن PR/MR يسمح بمراجعة الكود والتعليقات أو النقاش قبل الدمج.",
                "claim_en": "The student states that a PR/MR enables code review, comments, or discussion before merging."
            },
            {
                "code": "git_pr_quality_ci_controlled_merge",
                "name_ar": "جودة وCI ودمج مضبوط",
                "name_en": "Quality, CI, controlled merge",
                "description_ar": "يوضح أنه يمكن تشغيل اختبارات/CI والتحكم بدمج تغييرات آمنة.",
                "claim_ar": "يذكر الطالب أن PR/MR يساعد على تشغيل CI أو الاختبارات ودمج التغييرات بشكل مضبوط وآمن.",
                "claim_en": "The student states that PR/MR helps run CI/tests and merge changes in a controlled and safe way."
            },
            {
                "code": "git_pr_no_review_direct_merge_claim",
                "name_ar": "ادعاء عدم الحاجة للمراجعة",
                "name_en": "Claims no review is needed",
                "description_ar": "ادعاء خاطئ بأن PR/MR يعني دمجًا مباشرًا بلا مراجعة أو فحص.",
                "claim_ar": "يدعي الطالب أن Pull Request يعني دمج التغييرات مباشرة دون مراجعة أو اختبار.",
                "claim_en": "The student claims a Pull Request means merging changes directly without review or testing."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_PR_PROPOSAL",
                "name_ar": "طلب دمج التغييرات",
                "description_ar": "يوضح أن PR/MR يقترح دمج branch.",
                "max_score": 2,
                "requires": [
                    "git_pr_proposes_changes_for_review"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن PR/MR هو طلب لدمج تغييرات branch في فرع هدف.",
                "order": 1
            },
            {
                "code": "GIT_PR_REVIEW",
                "name_ar": "مراجعة ونقاش الكود",
                "description_ar": "يوضح الملاحظات والمناقشة قبل الدمج.",
                "max_score": 2,
                "requires": [
                    "git_pr_discussion_review_feedback"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر code review والتعليقات أو النقاش قبل الدمج.",
                "order": 2
            },
            {
                "code": "GIT_PR_QUALITY",
                "name_ar": "جودة ودمج مضبوط",
                "description_ar": "يوضح دور CI أو الاختبارات والتحكم بالدمج.",
                "max_score": 1,
                "requires": [
                    "git_pr_quality_ci_controlled_merge"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر تشغيل الاختبارات أو CI ودمج التغييرات بشكل مضبوط.",
                "order": 3
            }
        ],
        "contradictions": [
            {
                "code": "GIT_PR_CONFLICT_NO_REVIEW",
                "trigger_concept": "git_pr_no_review_direct_merge_claim",
                "feedback_ar": "فكرة Pull/Merge Request هي عرض التغييرات للمراجعة والنقاش وغالبًا للفحوصات قبل دمجها في الفرع الهدف.",
                "blocked_rubrics": [
                    "GIT_PR_PROPOSAL",
                    "GIT_PR_REVIEW",
                    "GIT_PR_QUALITY"
                ]
            }
        ]
    },
    {
        "question_text": "ما الفرق بشكل مبسط بين merge و rebase؟",
        "topic": "git_merge_vs_rebase",
        "rule_set_code": "GIT_MERGE_REBASE_V1",
        "concepts": [
            {
                "code": "git_merge_combines_history_preserves_branches",
                "name_ar": "merge يجمع التاريخ",
                "name_en": "Merge combines history",
                "description_ar": "يوضح أن merge يدمج فرعًا مع الحفاظ على تاريخ متشعب وقد ينشئ merge commit.",
                "claim_ar": "يذكر الطالب أن merge يجمع تغييرات أو تاريخ فرعين وقد يحافظ على التفرع أو ينشئ merge commit.",
                "claim_en": "The student states that merge combines changes or histories and may preserve branching or create a merge commit."
            },
            {
                "code": "git_rebase_reapplies_commits_new_base",
                "name_ar": "rebase يعيد تطبيق commits",
                "name_en": "Rebase reapplies commits",
                "description_ar": "يوضح أن rebase ينقل أو يعيد تطبيق commits على base أحدث ويعيد كتابة history المحلي.",
                "claim_ar": "يذكر الطالب أن rebase يعيد تطبيق commits فوق base جديد أو أحدث، ولذلك يعيد كتابة history.",
                "claim_en": "The student states that rebase reapplies commits onto a newer base and therefore rewrites history."
            },
            {
                "code": "git_rebase_shared_branch_caution",
                "name_ar": "الحذر على الفروع المشتركة",
                "name_en": "Shared branch caution",
                "description_ar": "يوضح ضرورة الحذر عند rebase لفروع مشتركة لأن التاريخ يعاد كتابته.",
                "claim_ar": "يذكر الطالب أن rebase يحتاج حذرًا على shared branches أو بعد push لأنه يعيد كتابة التاريخ.",
                "claim_en": "The student states that rebase requires caution on shared branches or after push because it rewrites history."
            },
            {
                "code": "git_merge_rebase_identical_claim",
                "name_ar": "ادعاء تطابق merge وrebase",
                "name_en": "Claims merge and rebase are identical",
                "description_ar": "ادعاء خاطئ بأن merge وrebase متطابقان ولا يؤثران في التاريخ بشكل مختلف.",
                "claim_ar": "يدعي الطالب أن merge وrebase عمليتان متطابقتان تمامًا ولا يوجد فرق في history.",
                "claim_en": "The student claims merge and rebase are completely identical and do not differ in history behavior."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_MERGE_REBASE_MERGE",
                "name_ar": "تفسير merge",
                "description_ar": "يوضح دمج التغييرات/التاريخ مع الحفاظ على التفرع.",
                "max_score": 2,
                "requires": [
                    "git_merge_combines_history_preserves_branches"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن merge يجمع التغييرات أو التاريخ وقد ينشئ merge commit.",
                "order": 1
            },
            {
                "code": "GIT_MERGE_REBASE_REBASE",
                "name_ar": "تفسير rebase",
                "description_ar": "يوضح إعادة تطبيق commits على base جديد وكتابة التاريخ.",
                "max_score": 2,
                "requires": [
                    "git_rebase_reapplies_commits_new_base"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن rebase يعيد تطبيق commits على base أحدث ويعيد كتابة history.",
                "order": 2
            },
            {
                "code": "GIT_MERGE_REBASE_CAUTION",
                "name_ar": "الحذر في الفروع المشتركة",
                "description_ar": "يوضح خطر rebase على shared branches.",
                "max_score": 1,
                "requires": [
                    "git_rebase_shared_branch_caution"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر الحذر من rebase على shared branches أو commits التي تم push لها.",
                "order": 3
            }
        ],
        "contradictions": [
            {
                "code": "GIT_MERGE_REBASE_CONFLICT_IDENTICAL",
                "trigger_concept": "git_merge_rebase_identical_claim",
                "feedback_ar": "merge وrebase يحققان دمج العمل بطرق مختلفة: merge يجمع التاريخ، أما rebase فيعيد تطبيق commits ويعيد كتابة history.",
                "blocked_rubrics": [
                    "GIT_MERGE_REBASE_MERGE",
                    "GIT_MERGE_REBASE_REBASE",
                    "GIT_MERGE_REBASE_CAUTION"
                ]
            }
        ]
    },
    {
        "question_text": "كيف تحافظ على commit history نظيف ومفهوم في مشروع جماعي؟",
        "topic": "git_clean_history",
        "rule_set_code": "GIT_CLEAN_HISTORY_V1",
        "concepts": [
            {
                "code": "git_clean_history_atomic_commits",
                "name_ar": "commits صغيرة مركزة",
                "name_en": "Atomic focused commits",
                "description_ar": "يوضح أن كل commit يجب أن يكون صغيرًا ومركزًا على تغيير واحد منطقي.",
                "claim_ar": "يذكر الطالب أن commits يجب أن تكون صغيرة أو atomic ومركزة على تغيير منطقي واحد.",
                "claim_en": "The student states that commits should be small or atomic and focused on one logical change."
            },
            {
                "code": "git_clean_history_meaningful_messages",
                "name_ar": "رسائل واضحة",
                "name_en": "Meaningful messages",
                "description_ar": "يوضح استخدام commit messages وصفية ومفهومة.",
                "claim_ar": "يذكر الطالب استخدام رسائل commit واضحة تصف ماذا ولماذا تم التغيير.",
                "claim_en": "The student states that commit messages should be clear and describe what and why changed."
            },
            {
                "code": "git_clean_history_review_rebase_squash",
                "name_ar": "تنظيم قبل الدمج",
                "name_en": "Organizes before merge",
                "description_ar": "يوضح مراجعة وتنظيم commits قبل الدمج باستخدام rebase/squash عند ملاءمته.",
                "claim_ar": "يذكر الطالب أنه يراجع history قبل merge وقد يستخدم interactive rebase أو squash لتنظيم commits عند الحاجة.",
                "claim_en": "The student states that they review history before merge and may use interactive rebase or squash when appropriate."
            },
            {
                "code": "git_clean_history_avoid_unrelated_changes",
                "name_ar": "تجنب خلط تغييرات غير مرتبطة",
                "name_en": "Avoids unrelated changes",
                "description_ar": "يوضح عدم خلط refactor كبير مع feature أو fix غير مرتبط في commit واحد.",
                "claim_ar": "يذكر الطالب تجنب وضع تغييرات غير مرتبطة أو ملفات مولدة وضوضاء في نفس commit.",
                "claim_en": "The student states that unrelated changes, generated files, or noise should not be mixed into the same commit."
            },
            {
                "code": "git_clean_history_one_vague_commit_claim",
                "name_ar": "ادعاء commit واحد مبهم",
                "name_en": "Claims one vague commit is best",
                "description_ar": "ادعاء خاطئ بأن commit واحدًا ضخمًا برسالة مبهمة هو الأفضل دائمًا.",
                "claim_ar": "يدعي الطالب أن أفضل history هو commit واحد كبير لكل العمل برسالة مثل update فقط، دون تنظيم أو مراجعة.",
                "claim_en": "The student claims the best history is one huge commit with a vague message such as update, without organization or review."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_HISTORY_ATOMIC",
                "name_ar": "Commits مركزة",
                "description_ar": "يوضح commits صغيرة ومنطقية.",
                "max_score": 2,
                "requires": [
                    "git_clean_history_atomic_commits"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر commits صغيرة ومركزة على تغيير منطقي واحد.",
                "order": 1
            },
            {
                "code": "GIT_HISTORY_MESSAGES",
                "name_ar": "رسائل commits واضحة",
                "description_ar": "يوضح messages وصفية.",
                "max_score": 1,
                "requires": [
                    "git_clean_history_meaningful_messages"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر رسائل commit واضحة تصف التغيير.",
                "order": 2
            },
            {
                "code": "GIT_HISTORY_ORGANIZE",
                "name_ar": "تنظيم history قبل الدمج",
                "description_ar": "يوضح rebase/squash أو مراجعة history عند الحاجة.",
                "max_score": 1,
                "requires": [
                    "git_clean_history_review_rebase_squash"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر مراجعة history قبل merge واستخدام squash أو rebase عند ملاءمته.",
                "order": 3
            },
            {
                "code": "GIT_HISTORY_SCOPE",
                "name_ar": "عدم خلط تغييرات غير مرتبطة",
                "description_ar": "يوضح فصل التغييرات غير المرتبطة.",
                "max_score": 1,
                "requires": [
                    "git_clean_history_avoid_unrelated_changes"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر تجنب خلط تغييرات غير مرتبطة أو ضوضاء في commit واحد.",
                "order": 4
            }
        ],
        "contradictions": [
            {
                "code": "GIT_HISTORY_CONFLICT_ONE_VAGUE_COMMIT",
                "trigger_concept": "git_clean_history_one_vague_commit_claim",
                "feedback_ar": "history نظيف يحتاج commits مركزة ورسائل واضحة وفصل التغييرات غير المرتبطة، وليس commit ضخمًا مبهمًا لكل العمل.",
                "blocked_rubrics": [
                    "GIT_HISTORY_ATOMIC",
                    "GIT_HISTORY_MESSAGES",
                    "GIT_HISTORY_ORGANIZE",
                    "GIT_HISTORY_SCOPE"
                ]
            }
        ]
    },
    {
        "question_text": "كيف تتعامل مع تعارضات merge معقدة في مشروع كبير؟",
        "topic": "git_complex_merge_conflicts",
        "rule_set_code": "GIT_COMPLEX_MERGE_CONFLICTS_V1",
        "concepts": [
            {
                "code": "git_complex_conflict_update_context_coordinate",
                "name_ar": "تحديث السياق والتنسيق",
                "name_en": "Updates context and coordinates",
                "description_ar": "يوضح تحديث الفرع وفهم السياق والتواصل مع أصحاب التغييرات قبل الحل.",
                "claim_ar": "يذكر الطالب أنه يحدّث فرعه ويفهم السياق ويتواصل مع أصحاب التغييرات أو الفريق عند التعارض المعقد.",
                "claim_en": "The student states that they update their branch, understand context, and coordinate with change owners or the team for a complex conflict."
            },
            {
                "code": "git_complex_conflict_resolve_incrementally",
                "name_ar": "حل تدريجي مدروس",
                "name_en": "Resolves incrementally",
                "description_ar": "يوضح مقارنة base/ours/theirs وحل الملفات تدريجيًا مع الحفاظ على السلوك المقصود.",
                "claim_ar": "يذكر الطالب مقارنة base وours وtheirs أو حل التعارض خطوة بخطوة بدل تعديل عشوائي.",
                "claim_en": "The student states that they compare base, ours, and theirs or resolve the conflict step by step instead of editing randomly."
            },
            {
                "code": "git_complex_conflict_test_validate",
                "name_ar": "اختبار وتحقق",
                "name_en": "Tests and validates",
                "description_ar": "يوضح بناء المشروع وتشغيل الاختبارات بعد الحل.",
                "claim_ar": "يذكر الطالب تشغيل build أو tests والتحقق من السلوك بعد حل التعارض.",
                "claim_en": "The student states that they run the build or tests and validate behavior after resolving the conflict."
            },
            {
                "code": "git_complex_conflict_document_commit",
                "name_ar": "توثيق وحفظ الحل",
                "name_en": "Documents and commits solution",
                "description_ar": "يوضح مراجعة الحل ثم add/commit مع توثيق مختصر عند الحاجة.",
                "claim_ar": "يذكر الطالب مراجعة الحل ثم git add وcommit وتوثيق القرار عند الحاجة.",
                "claim_en": "The student states that they review the solution, then git add and commit, documenting the decision when useful."
            },
            {
                "code": "git_complex_conflict_discard_without_review_claim",
                "name_ar": "ادعاء حذف بلا مراجعة",
                "name_en": "Claims discard without review",
                "description_ar": "ادعاء خاطئ بأن حذف تغييرات الفريق أو استخدام ours/theirs دون فهم/اختبار هو الحل دائمًا.",
                "claim_ar": "يدعي الطالب أن حذف تغييرات أحد الأطراف أو اختيار ours/theirs دون فهم أو اختبار هو الحل الصحيح دائمًا.",
                "claim_en": "The student claims discarding one side or choosing ours/theirs without understanding or testing is always correct."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_COMPLEX_CONFLICT_CONTEXT",
                "name_ar": "فهم السياق والتنسيق",
                "description_ar": "يوضح تحديث السياق والتواصل.",
                "max_score": 2,
                "requires": [
                    "git_complex_conflict_update_context_coordinate"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر تحديث الفرع وفهم السياق والتنسيق مع أصحاب التغييرات عند الحاجة.",
                "order": 1
            },
            {
                "code": "GIT_COMPLEX_CONFLICT_RESOLVE",
                "name_ar": "حل تدريجي مدروس",
                "description_ar": "يوضح مقارنة النسخ وحل التعارض تدريجيًا.",
                "max_score": 1,
                "requires": [
                    "git_complex_conflict_resolve_incrementally"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر مقارنة base/ours/theirs أو حل الملفات خطوة بخطوة.",
                "order": 2
            },
            {
                "code": "GIT_COMPLEX_CONFLICT_TEST",
                "name_ar": "اختبار الحل",
                "description_ar": "يوضح تشغيل build/tests بعد الحل.",
                "max_score": 1,
                "requires": [
                    "git_complex_conflict_test_validate"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر تشغيل build أو tests والتحقق من السلوك بعد الحل.",
                "order": 3
            },
            {
                "code": "GIT_COMPLEX_CONFLICT_COMMIT",
                "name_ar": "إتمام وتوثيق الحل",
                "description_ar": "يوضح المراجعة وadd/commit والتوثيق عند الحاجة.",
                "max_score": 1,
                "requires": [
                    "git_complex_conflict_document_commit"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر مراجعة الحل ثم git add وcommit وتوثيق القرار عند الحاجة.",
                "order": 4
            }
        ],
        "contradictions": [
            {
                "code": "GIT_COMPLEX_CONFLICT_CONFLICT_DISCARD",
                "trigger_concept": "git_complex_conflict_discard_without_review_claim",
                "feedback_ar": "في التعارضات المعقدة لا تحذف تغييرات أحد الأطراف بلا فهم أو اختبار؛ افهم السياق، حل التغييرات تدريجيًا، ثم اختبر النتيجة.",
                "blocked_rubrics": [
                    "GIT_COMPLEX_CONFLICT_CONTEXT",
                    "GIT_COMPLEX_CONFLICT_RESOLVE",
                    "GIT_COMPLEX_CONFLICT_TEST",
                    "GIT_COMPLEX_CONFLICT_COMMIT"
                ]
            }
        ]
    },
    {
        "question_text": "ما الفائدة من وجود workflow واضح للفروع مثل feature branches و release branches؟",
        "topic": "git_branch_workflow",
        "rule_set_code": "GIT_BRANCH_WORKFLOW_V1",
        "concepts": [
            {
                "code": "git_workflow_feature_isolation",
                "name_ar": "عزل ميزات التطوير",
                "name_en": "Feature isolation",
                "description_ar": "يوضح أن feature branches تعزل تطوير كل ميزة.",
                "claim_ar": "يذكر الطالب أن feature branches تعزل كل ميزة أو ticket عن main وعن الميزات الأخرى.",
                "claim_en": "The student states that feature branches isolate each feature or ticket from main and other features."
            },
            {
                "code": "git_workflow_release_stabilization",
                "name_ar": "تثبيت الإصدار",
                "name_en": "Release stabilization",
                "description_ar": "يوضح أن release branches تستخدم لتثبيت الإصدار وإصلاحه قبل النشر.",
                "claim_ar": "يذكر الطالب أن release branches تساعد على تثبيت إصدار أو إصلاح أخطاءه وتحضيره للنشر.",
                "claim_en": "The student states that release branches help stabilize a release, fix issues, and prepare it for deployment."
            },
            {
                "code": "git_workflow_controlled_parallel_integration",
                "name_ar": "توازي ودمج مضبوط",
                "name_en": "Controlled parallel integration",
                "description_ar": "يوضح العمل المتوازي مع مراجعة ودمج منضبطين.",
                "claim_ar": "يذكر الطالب أن workflow واضح يسمح بالعمل بالتوازي مع review وCI ودمج منضبط.",
                "claim_en": "The student states that a clear workflow enables parallel work with review, CI, and controlled integration."
            },
            {
                "code": "git_workflow_traceable_releases",
                "name_ar": "تتبع الإصدارات",
                "name_en": "Traceable releases",
                "description_ar": "يوضح أن workflow يساعد على تتبع الإصدار والـhotfix والعودة الآمنة.",
                "claim_ar": "يذكر الطالب أن workflow واضح يسهل تتبع الإصدارات وhotfixes أو الرجوع الآمن عند المشكلة.",
                "claim_en": "The student states that a clear workflow makes releases, hotfixes, or safe rollback easier to trace."
            },
            {
                "code": "git_workflow_all_direct_main_claim",
                "name_ar": "ادعاء العمل المباشر على main",
                "name_en": "Claims all work directly on main",
                "description_ar": "ادعاء خاطئ بأن الجميع يجب أن يعمل ويدمج مباشرة على main بلا workflow.",
                "claim_ar": "يدعي الطالب أن أفضل workflow هو أن يعمل الجميع ويدمج مباشرة على main دون فروع أو مراجعة.",
                "claim_en": "The student claims the best workflow is for everyone to work and merge directly on main without branches or review."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_WORKFLOW_FEATURE",
                "name_ar": "Feature branches",
                "description_ar": "يوضح عزل تطوير الميزة.",
                "max_score": 2,
                "requires": [
                    "git_workflow_feature_isolation"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن feature branches تعزل العمل على كل ميزة أو ticket.",
                "order": 1
            },
            {
                "code": "GIT_WORKFLOW_RELEASE",
                "name_ar": "Release branches",
                "description_ar": "يوضح تثبيت الإصدار وتحضيره للنشر.",
                "max_score": 1,
                "requires": [
                    "git_workflow_release_stabilization"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر دور release branch في تثبيت الإصدار أو إصلاحه قبل النشر.",
                "order": 2
            },
            {
                "code": "GIT_WORKFLOW_INTEGRATION",
                "name_ar": "دمج مضبوط",
                "description_ar": "يوضح التوازي مع review/CI.",
                "max_score": 1,
                "requires": [
                    "git_workflow_controlled_parallel_integration"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر العمل المتوازي مع review أو CI ودمج مضبوط.",
                "order": 3
            },
            {
                "code": "GIT_WORKFLOW_TRACEABILITY",
                "name_ar": "تتبع الإصدارات",
                "description_ar": "يوضح سهولة تتبع release/hotfix/rollback.",
                "max_score": 1,
                "requires": [
                    "git_workflow_traceable_releases"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر تتبع الإصدارات أو hotfixes أو الرجوع الآمن.",
                "order": 4
            }
        ],
        "contradictions": [
            {
                "code": "GIT_WORKFLOW_CONFLICT_DIRECT_MAIN",
                "trigger_concept": "git_workflow_all_direct_main_claim",
                "feedback_ar": "workflow واضح للفروع يمنع الفوضى في main، ويسمح بعزل الميزات وتثبيت الإصدارات ومراجعة ودمج التغييرات بطريقة مضبوطة.",
                "blocked_rubrics": [
                    "GIT_WORKFLOW_FEATURE",
                    "GIT_WORKFLOW_RELEASE",
                    "GIT_WORKFLOW_INTEGRATION",
                    "GIT_WORKFLOW_TRACEABILITY"
                ]
            }
        ]
    },
    {
        "question_text": "متى قد يكون force push خطرًا؟",
        "topic": "git_force_push_risk",
        "rule_set_code": "GIT_FORCE_PUSH_RISK_V1",
        "concepts": [
            {
                "code": "git_force_push_rewrites_remote_history",
                "name_ar": "إعادة كتابة التاريخ البعيد",
                "name_en": "Rewrites remote history",
                "description_ar": "يوضح أن force push يمكن أن يعيد كتابة تاريخ remote branch.",
                "claim_ar": "يذكر الطالب أن force push قد يعيد كتابة history على remote branch.",
                "claim_en": "The student states that force push may rewrite history on a remote branch."
            },
            {
                "code": "git_force_push_can_lose_others_work",
                "name_ar": "فقدان عمل الآخرين",
                "name_en": "Can lose others work",
                "description_ar": "يوضح أنه قد يخفي أو يزيل commits زملاء على shared branch.",
                "claim_ar": "يذكر الطالب أن force push قد يفقد أو يخفي commits الآخرين على فرع مشترك.",
                "claim_en": "The student states that force push can lose or hide others commits on a shared branch."
            },
            {
                "code": "git_force_push_safe_practice_coordination_lease",
                "name_ar": "ممارسة آمنة وتحذير",
                "name_en": "Safe practice and coordination",
                "description_ar": "يوضح تجنبه على الفروع المشتركة، والتنسيق واستعمال --force-with-lease عند الاضطرار.",
                "claim_ar": "يذكر الطالب تجنب force push على shared branches والتنسيق مع الفريق واستخدام --force-with-lease عند الضرورة.",
                "claim_en": "The student states to avoid force push on shared branches, coordinate with the team, and use --force-with-lease when necessary."
            },
            {
                "code": "git_force_push_always_safe_claim",
                "name_ar": "ادعاء أنه آمن دائمًا",
                "name_en": "Claims force push is always safe",
                "description_ar": "ادعاء خاطئ بأن force push آمن دائمًا في الفروع المشتركة ولا يؤثر على عمل الآخرين.",
                "claim_ar": "يدعي الطالب أن force push آمن دائمًا على shared branches ولا يمكن أن يؤثر على commits الآخرين.",
                "claim_en": "The student claims force push is always safe on shared branches and cannot affect others commits."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_FORCE_PUSH_HISTORY",
                "name_ar": "إعادة كتابة history",
                "description_ar": "يوضح أن force push يعيد كتابة تاريخ remote.",
                "max_score": 2,
                "requires": [
                    "git_force_push_rewrites_remote_history"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "وضح أن force push قد يعيد كتابة history في remote branch.",
                "order": 1
            },
            {
                "code": "GIT_FORCE_PUSH_OTHERS",
                "name_ar": "خطر فقدان عمل الآخرين",
                "description_ar": "يوضح خطر إخفاء/فقدان commits الفريق.",
                "max_score": 2,
                "requires": [
                    "git_force_push_can_lose_others_work"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر خطر فقدان أو إخفاء commits الآخرين على shared branch.",
                "order": 2
            },
            {
                "code": "GIT_FORCE_PUSH_SAFE_PRACTICE",
                "name_ar": "التصرف الآمن",
                "description_ar": "يوضح التنسيق و--force-with-lease عند الضرورة.",
                "max_score": 1,
                "requires": [
                    "git_force_push_safe_practice_coordination_lease"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر تجنب force push على shared branches والتنسيق أو --force-with-lease عند الضرورة.",
                "order": 3
            }
        ],
        "contradictions": [
            {
                "code": "GIT_FORCE_PUSH_CONFLICT_ALWAYS_SAFE",
                "trigger_concept": "git_force_push_always_safe_claim",
                "feedback_ar": "force push يمكن أن يعيد كتابة تاريخ الفرع البعيد ويخفي عمل الآخرين؛ تجنبه على الفروع المشتركة واستخدم --force-with-lease والتنسيق عند الضرورة.",
                "blocked_rubrics": [
                    "GIT_FORCE_PUSH_HISTORY",
                    "GIT_FORCE_PUSH_OTHERS",
                    "GIT_FORCE_PUSH_SAFE_PRACTICE"
                ]
            }
        ]
    },
    {
        "question_text": "كيف تضع استراتيجية Git مناسبة لفريق Backend يعمل على ميزات متعددة بالتوازي؟",
        "topic": "git_backend_team_strategy",
        "rule_set_code": "GIT_BACKEND_TEAM_STRATEGY_V1",
        "concepts": [
            {
                "code": "git_strategy_protected_main_feature_branches",
                "name_ar": "main محمي وفروع ميزات",
                "name_en": "Protected main and feature branches",
                "description_ar": "يوضح main protected وفروع feature لكل ticket/ميزة.",
                "claim_ar": "يذكر الطالب استخدام main محمي وفروع feature منفصلة لكل ميزة أو ticket.",
                "claim_en": "The student states to use protected main and separate feature branches for each feature or ticket."
            },
            {
                "code": "git_strategy_pr_review_ci",
                "name_ar": "PR ومراجعة وCI",
                "name_en": "PR review and CI",
                "description_ar": "يوضح أن الدمج يتم عبر PR مع code review واختبارات/CI.",
                "claim_ar": "يذكر الطالب أن التغييرات تدمج عبر Pull Request بعد code review وCI أو tests.",
                "claim_en": "The student states that changes are merged through Pull Requests after code review and CI or tests."
            },
            {
                "code": "git_strategy_release_hotfix_policy",
                "name_ar": "سياسة release وhotfix",
                "name_en": "Release and hotfix policy",
                "description_ar": "يوضح وجود release/hotfix branches أو tags وسياسة نشر واضحة.",
                "claim_ar": "يذكر الطالب استخدام release أو hotfix branches أو tags مع سياسة واضحة للنشر.",
                "claim_en": "The student states to use release or hotfix branches or tags with a clear deployment policy."
            },
            {
                "code": "git_strategy_commit_conventions_sync",
                "name_ar": "اتفاقيات commits ومزامنة",
                "name_en": "Commit conventions and sync",
                "description_ar": "يوضح اتفاق الفريق على تسمية الفروع ورسائل commits ومزامنة الفروع لتقليل التعارض.",
                "claim_ar": "يذكر الطالب اتفاقيات لتسمية branches ورسائل commits وتحديث الفروع بانتظام لتقليل التعارضات.",
                "claim_en": "The student states to define branch naming, commit-message conventions, and regular synchronization to reduce conflicts."
            },
            {
                "code": "git_strategy_roles_access_recovery",
                "name_ar": "صلاحيات واستعادة",
                "name_en": "Access and recovery",
                "description_ar": "يوضح حماية الفروع والصلاحيات وخطة rollback/recovery عند خطأ النشر.",
                "claim_ar": "يذكر الطالب branch protection وصلاحيات الدمج وخطة rollback أو recovery عند حدوث مشكلة.",
                "claim_en": "The student states branch protection, merge permissions, and a rollback or recovery plan for incidents."
            },
            {
                "code": "git_strategy_everyone_push_main_claim",
                "name_ar": "ادعاء push مباشر للجميع",
                "name_en": "Claims everyone pushes directly to main",
                "description_ar": "ادعاء خاطئ بأن الجميع يجب أن يpush مباشرة إلى main بلا PR أو حماية.",
                "claim_ar": "يدعي الطالب أن الاستراتيجية الأفضل هي أن كل المطورين يpush مباشرة إلى main دون Pull Requests أو branch protection.",
                "claim_en": "The student claims the best strategy is for every developer to push directly to main without Pull Requests or branch protection."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_BACKEND_STRATEGY_BRANCHES",
                "name_ar": "فروع محمية ومنظمة",
                "description_ar": "يوضح main محمي وfeature branches.",
                "max_score": 1,
                "requires": [
                    "git_strategy_protected_main_feature_branches"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر main محمي وفروع feature مستقلة لكل ميزة أو ticket.",
                "order": 1
            },
            {
                "code": "GIT_BACKEND_STRATEGY_PR_CI",
                "name_ar": "PR ومراجعة وCI",
                "description_ar": "يوضح review وCI قبل الدمج.",
                "max_score": 1,
                "requires": [
                    "git_strategy_pr_review_ci"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر Pull Requests وcode review وCI/tests قبل الدمج.",
                "order": 2
            },
            {
                "code": "GIT_BACKEND_STRATEGY_RELEASE",
                "name_ar": "إصدارات وhotfix",
                "description_ar": "يوضح سياسة release/hotfix أو tags.",
                "max_score": 1,
                "requires": [
                    "git_strategy_release_hotfix_policy"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر release/hotfix branches أو tags وسياسة نشر واضحة.",
                "order": 3
            },
            {
                "code": "GIT_BACKEND_STRATEGY_CONVENTIONS",
                "name_ar": "اتفاقيات الفريق",
                "description_ar": "يوضح naming/commit conventions ومزامنة.",
                "max_score": 1,
                "requires": [
                    "git_strategy_commit_conventions_sync"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر اتفاقيات تسمية الفروع ورسائل commits وتحديث الفروع دوريًا.",
                "order": 4
            },
            {
                "code": "GIT_BACKEND_STRATEGY_GOVERNANCE",
                "name_ar": "حماية واستعادة",
                "description_ar": "يوضح الصلاحيات والحماية وخطة recovery.",
                "max_score": 1,
                "requires": [
                    "git_strategy_roles_access_recovery"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر branch protection وصلاحيات الدمج وخطة rollback أو recovery.",
                "order": 5
            }
        ],
        "contradictions": [
            {
                "code": "GIT_BACKEND_STRATEGY_CONFLICT_DIRECT_MAIN",
                "trigger_concept": "git_strategy_everyone_push_main_claim",
                "feedback_ar": "لفريق يعمل بالتوازي، push المباشر من الجميع إلى main بلا PR أو حماية يرفع مخاطر التعارضات والأخطاء؛ استخدم فروعًا منظمة ومراجعة وCI وحماية للفرع الرئيسي.",
                "blocked_rubrics": [
                    "GIT_BACKEND_STRATEGY_BRANCHES",
                    "GIT_BACKEND_STRATEGY_PR_CI",
                    "GIT_BACKEND_STRATEGY_RELEASE",
                    "GIT_BACKEND_STRATEGY_CONVENTIONS",
                    "GIT_BACKEND_STRATEGY_GOVERNANCE"
                ]
            }
        ]
    },
    {
        "question_text": "إذا حدثت مشكلة في تاريخ المستودع بعد rebase أو force push، كيف تتعامل معها بحذر؟",
        "topic": "git_history_recovery",
        "rule_set_code": "GIT_HISTORY_RECOVERY_V1",
        "concepts": [
            {
                "code": "git_recovery_stop_notify_preserve",
                "name_ar": "إيقاف والتنبيه وحفظ الحالة",
                "name_en": "Stop, notify, preserve state",
                "description_ar": "يوضح التوقف عن عمليات destructive والتنبيه وعدم زيادة المشكلة.",
                "claim_ar": "يذكر الطالب أنه يوقف push/force push إضافيًا وينبه الفريق ويحافظ على الحالة قبل التصرف.",
                "claim_en": "The student states that they stop additional push/force push, notify the team, and preserve the state before acting."
            },
            {
                "code": "git_recovery_use_reflog_remote_refs_backup",
                "name_ar": "استخدام reflog والمراجع والنسخ",
                "name_en": "Uses reflog, refs, backups",
                "description_ar": "يوضح استخدام git reflog أو remote refs أو backup لتحديد commit المفقود.",
                "claim_ar": "يذكر الطالب استخدام git reflog أو remote tracking refs أو backup للعثور على commits السابقة أو المفقودة.",
                "claim_en": "The student states that they use git reflog, remote tracking refs, or backups to find prior or missing commits."
            },
            {
                "code": "git_recovery_restore_safely_new_branch_cherry_pick",
                "name_ar": "استعادة آمنة",
                "name_en": "Restores safely",
                "description_ar": "يوضح إنشاء فرع إنقاذ ثم cherry-pick/reset/restore بحذر والتحقق قبل push.",
                "claim_ar": "يذكر الطالب إنشاء recovery branch أو restore آمن ثم استخدام cherry-pick أو reset بحذر والتحقق قبل push.",
                "claim_en": "The student states that they create a recovery branch or restore safely, then use cherry-pick or reset carefully and verify before push."
            },
            {
                "code": "git_recovery_coordinate_force_with_lease",
                "name_ar": "تنسيق force push آمن",
                "name_en": "Coordinates safe force push",
                "description_ar": "يوضح التنسيق واستخدام --force-with-lease إذا لزم تصحيح remote.",
                "claim_ar": "يذكر الطالب التنسيق مع الفريق واستخدام --force-with-lease فقط عند الحاجة بعد التحقق.",
                "claim_en": "The student states that they coordinate with the team and use --force-with-lease only when necessary after verification."
            },
            {
                "code": "git_recovery_document_prevent_repeat",
                "name_ar": "توثيق ومنع التكرار",
                "name_en": "Documents and prevents repeat",
                "description_ar": "يوضح توثيق ما حدث وتحسين الحماية/سياسة الفروع.",
                "claim_ar": "يذكر الطالب توثيق الحادث وتحسين branch protection أو workflow لمنع تكراره.",
                "claim_en": "The student states that they document the incident and improve branch protection or workflow to prevent recurrence."
            },
            {
                "code": "git_recovery_delete_repo_repeat_force_claim",
                "name_ar": "ادعاء حذف أو force push إضافي",
                "name_en": "Claims delete/repeat force push solves it",
                "description_ar": "ادعاء خاطئ بأن حذف المستودع أو force push إضافي بلا تحقق هو الحل.",
                "claim_ar": "يدعي الطالب أن حذف المستودع أو تنفيذ force push آخر مباشرة دون فحص reflog أو تنسيق هو الحل الصحيح.",
                "claim_en": "The student claims deleting the repository or immediately doing another force push without reflog checks or coordination is the correct solution."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_RECOVERY_STOP_NOTIFY",
                "name_ar": "إيقاف وتصعيد",
                "description_ar": "يوضح إيقاف العمليات الخطرة والتنبيه.",
                "max_score": 1,
                "requires": [
                    "git_recovery_stop_notify_preserve"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر إيقاف force push إضافي وتنبيه الفريق وحفظ الحالة.",
                "order": 1
            },
            {
                "code": "GIT_RECOVERY_FIND_HISTORY",
                "name_ar": "تحديد commits المفقودة",
                "description_ar": "يوضح reflog أو refs أو backups.",
                "max_score": 1,
                "requires": [
                    "git_recovery_use_reflog_remote_refs_backup"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر git reflog أو remote refs أو backup لتحديد commits السابقة.",
                "order": 2
            },
            {
                "code": "GIT_RECOVERY_RESTORE",
                "name_ar": "استعادة آمنة",
                "description_ar": "يوضح recovery branch وrestore/cherry-pick/reset بحذر.",
                "max_score": 1,
                "requires": [
                    "git_recovery_restore_safely_new_branch_cherry_pick"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر فرع إنقاذ أو cherry-pick/reset/restore بحذر والتحقق قبل push.",
                "order": 3
            },
            {
                "code": "GIT_RECOVERY_COORDINATE",
                "name_ar": "تنسيق تصحيح remote",
                "description_ar": "يوضح التنسيق و--force-with-lease عند الحاجة.",
                "max_score": 1,
                "requires": [
                    "git_recovery_coordinate_force_with_lease"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر التنسيق واستخدام --force-with-lease عند الضرورة فقط.",
                "order": 4
            },
            {
                "code": "GIT_RECOVERY_PREVENT",
                "name_ar": "منع تكرار المشكلة",
                "description_ar": "يوضح التوثيق والحماية المستقبلية.",
                "max_score": 1,
                "requires": [
                    "git_recovery_document_prevent_repeat"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر توثيق الحادث وتحسين branch protection أو workflow.",
                "order": 5
            }
        ],
        "contradictions": [
            {
                "code": "GIT_RECOVERY_CONFLICT_DELETE_REPEAT_FORCE",
                "trigger_concept": "git_recovery_delete_repo_repeat_force_claim",
                "feedback_ar": "لا تعالج مشكلة history بحذف المستودع أو force push إضافي بلا تحقق؛ أوقف العمليات الخطرة، استخدم reflog/refs للاستعادة، نسق مع الفريق، ثم صحح remote بحذر.",
                "blocked_rubrics": [
                    "GIT_RECOVERY_STOP_NOTIFY",
                    "GIT_RECOVERY_FIND_HISTORY",
                    "GIT_RECOVERY_RESTORE",
                    "GIT_RECOVERY_COORDINATE",
                    "GIT_RECOVERY_PREVENT"
                ]
            }
        ]
    },
    {
        "question_text": "ما الممارسات التي تجعل استخدام Git على مستوى الفريق احترافيًا وقابلًا للتوسع؟",
        "topic": "git_team_professional_practices",
        "rule_set_code": "GIT_TEAM_PRACTICES_V1",
        "concepts": [
            {
                "code": "git_team_branch_protection_reviews",
                "name_ar": "حماية الفروع والمراجعة",
                "name_en": "Branch protection and reviews",
                "description_ar": "يوضح حماية main/branches وطلب review قبل الدمج.",
                "claim_ar": "يذكر الطالب branch protection وPull Requests ومراجعة الكود قبل الدمج.",
                "claim_en": "The student states branch protection, Pull Requests, and code review before merging."
            },
            {
                "code": "git_team_ci_tests_quality_gates",
                "name_ar": "CI واختبارات الجودة",
                "name_en": "CI and quality gates",
                "description_ar": "يوضح CI/tests/lint كـquality gates قبل الدمج أو النشر.",
                "claim_ar": "يذكر الطالب تشغيل CI أو tests أو lint كفحوصات جودة قبل الدمج أو النشر.",
                "claim_en": "The student states that CI, tests, or lint run as quality gates before merge or deployment."
            },
            {
                "code": "git_team_atomic_commits_conventions",
                "name_ar": "commits واتفاقيات واضحة",
                "name_en": "Atomic commits and conventions",
                "description_ar": "يوضح commits مركزة ورسائل ونمط تسمية branches واضح.",
                "claim_ar": "يذكر الطالب commits صغيرة ورسائل واضحة واتفاقيات لتسمية branches أو commits.",
                "claim_en": "The student states small commits, clear messages, and conventions for branch names or commits."
            },
            {
                "code": "git_team_release_tags_docs",
                "name_ar": "إصدارات وtags وتوثيق",
                "name_en": "Releases, tags, documentation",
                "description_ar": "يوضح tags/releases وdocumented workflow وhotfix/rollback.",
                "claim_ar": "يذكر الطالب tags أو releases وworkflow موثقًا وخطة hotfix أو rollback.",
                "claim_en": "The student states tags or releases, a documented workflow, and a hotfix or rollback plan."
            },
            {
                "code": "git_team_access_monitoring_recovery",
                "name_ar": "صلاحيات ومراقبة واستعادة",
                "name_en": "Access, monitoring, recovery",
                "description_ar": "يوضح صلاحيات مناسبة وسجل تدقيق/نسخ واستعادة وتعلم من الحوادث.",
                "claim_ar": "يذكر الطالب صلاحيات دمج مناسبة أو audit/history وbackup/recovery وتحسين الممارسات بعد الحوادث.",
                "claim_en": "The student states appropriate merge permissions or audit/history, backup/recovery, and improvement after incidents."
            },
            {
                "code": "git_team_no_rules_direct_push_claim",
                "name_ar": "ادعاء عدم الحاجة للقواعد",
                "name_en": "Claims no rules/direct push is professional",
                "description_ar": "ادعاء خاطئ بأن العمل بلا قواعد وpush مباشر للجميع أكثر احترافية وقابلية للتوسع.",
                "claim_ar": "يدعي الطالب أن Git الاحترافي لا يحتاج مراجعة أو CI أو حماية فروع، وأن push المباشر من الجميع هو الأفضل.",
                "claim_en": "The student claims professional Git needs no review, CI, or protected branches and that direct pushes by everyone are best."
            }
        ],
        "rubrics": [
            {
                "code": "GIT_TEAM_PROTECTION",
                "name_ar": "حماية ومراجعة",
                "description_ar": "يوضح branch protection وPR/review.",
                "max_score": 1,
                "requires": [
                    "git_team_branch_protection_reviews"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر branch protection وPull Requests وcode review قبل الدمج.",
                "order": 1
            },
            {
                "code": "GIT_TEAM_CI",
                "name_ar": "CI واختبارات",
                "description_ar": "يوضح فحوصات الجودة.",
                "max_score": 1,
                "requires": [
                    "git_team_ci_tests_quality_gates"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر CI أو tests أو lint قبل الدمج أو النشر.",
                "order": 2
            },
            {
                "code": "GIT_TEAM_CONVENTIONS",
                "name_ar": "Commits واتفاقيات",
                "description_ar": "يوضح commits مركزة ورسائل/تسمية واضحة.",
                "max_score": 1,
                "requires": [
                    "git_team_atomic_commits_conventions"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر commits صغيرة ورسائل واضحة واتفاقيات للفروع أو commits.",
                "order": 3
            },
            {
                "code": "GIT_TEAM_RELEASES",
                "name_ar": "إصدارات وتوثيق",
                "description_ar": "يوضح tags/releases وworkflow وخطة hotfix/rollback.",
                "max_score": 1,
                "requires": [
                    "git_team_release_tags_docs"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر tags أو releases وworkflow موثقًا وخطة hotfix أو rollback.",
                "order": 4
            },
            {
                "code": "GIT_TEAM_GOVERNANCE",
                "name_ar": "صلاحيات واستعادة وتحسين",
                "description_ar": "يوضح access/recovery/learning.",
                "max_score": 1,
                "requires": [
                    "git_team_access_monitoring_recovery"
                ],
                "blocked_by": [],
                "sample_good": "إجابة صحيحة توضّح المعيار.",
                "sample_bad": "إجابة لا تحقق المعيار.",
                "feedback_pass": "حققت هذا المعيار.",
                "feedback_fail": "اذكر صلاحيات مناسبة وbackup/recovery وتحسين الممارسات بعد الحوادث.",
                "order": 5
            }
        ],
        "contradictions": [
            {
                "code": "GIT_TEAM_CONFLICT_NO_RULES_DIRECT_PUSH",
                "trigger_concept": "git_team_no_rules_direct_push_claim",
                "feedback_ar": "الاستخدام الاحترافي القابل للتوسع يحتاج حماية فروع ومراجعة وCI واتفاقيات واضحة وإدارة إصدارات وخطة استعادة؛ push المباشر بلا ضوابط يزيد المخاطر.",
                "blocked_rubrics": [
                    "GIT_TEAM_PROTECTION",
                    "GIT_TEAM_CI",
                    "GIT_TEAM_CONVENTIONS",
                    "GIT_TEAM_RELEASES",
                    "GIT_TEAM_GOVERNANCE"
                ]
            }
        ]
    }
]
JSON;

        return json_decode(
            $definitionsJson,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
