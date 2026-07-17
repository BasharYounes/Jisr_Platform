<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class PythonFundamentalsExpertRulesSeeder extends Seeder
{
    use ResolvesExpertQuestionsByTopic;

    private const SKILL_NAME = 'Python';

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
                'Python fundamentals Expert Rules data was seeded successfully.'
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
                'Cannot seed Python fundamentals questions because one or more '
                . 'questions already have assessment attempts. '
                . 'Create a new Rule Set version instead of replacing rules.'
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
                'RuleCode' => $rubric['code'] . '_FULL',
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
        return [
            [
                'question_text' =>
                    'ما وظيفة جملة if في Python؟ اكتب مثالًا بسيطًا.',
                'topic' => 'conditional_if',
                'rule_set_code' => 'PY_IF_STATEMENT_V1',
                'concepts' => [
                    [
                        'code' => 'py_if_checks_condition',
                        'name_ar' => 'if تتحقق من شرط',
                        'name_en' => 'If checks a condition',
                        'description_ar' =>
                            'يوضح أن جملة if تستخدم للتحقق من شرط.',
                        'claim_ar' =>
                            'الطالب يذكر أن جملة if تتحقق من شرط أو تقارن '
                            . 'نتيجة شرطية.',
                        'claim_en' =>
                            'The student states that an if statement checks '
                            . 'a condition or a boolean expression.',
                    ],
                    [
                        'code' => 'py_if_executes_when_true',
                        'name_ar' => 'if تنفذ الكود عند تحقق الشرط',
                        'name_en' => 'If executes code when true',
                        'description_ar' =>
                            'يوضح أن الكود داخل if ينفذ عندما تكون نتيجة الشرط صحيحة.',
                        'claim_ar' =>
                            'الطالب يذكر أن الكود داخل if ينفذ عندما يكون '
                            . 'الشرط صحيحًا أو متحققًا.',
                        'claim_en' =>
                            'The student states that code inside if executes '
                            . 'when the condition is true.',
                    ],
                    [
                        'code' => 'py_if_valid_example',
                        'name_ar' => 'مثال صحيح على if',
                        'name_en' => 'Valid if statement example',
                        'description_ar' =>
                            'يقدم مثالًا صحيحًا يحتوي if وشرطًا واضحًا.',
                        'claim_ar' =>
                            'الطالب يقدم مثالًا صحيحًا لجملة if في Python '
                            . 'يتضمن if وشرطًا وكودًا ينفذ داخلها.',
                        'claim_en' =>
                            'The student provides a valid Python if example '
                            . 'with an if keyword, a condition, and code inside it.',
                    ],
                    [
                        'code' => 'py_if_always_executes_claim',
                        'name_ar' => 'ادعاء أن if تنفذ دائمًا',
                        'name_en' => 'If always executes claim',
                        'description_ar' =>
                            'ادعاء خاطئ بأن if تنفذ الكود بغض النظر عن الشرط.',
                        'claim_ar' =>
                            'الطالب يذكر أن if تنفذ الكود دائمًا أو بغض النظر '
                            . 'عن نتيجة الشرط.',
                        'claim_en' =>
                            'The student claims that an if statement executes '
                            . 'its code regardless of whether the condition is true.',
                    ],
                ],
                'rubrics' => [
                    [
                        'code' => 'PY_IF_CHECKS_CONDITION',
                        'name_ar' => 'وظيفة if في التحقق من الشرط',
                        'description_ar' =>
                            'يوضح أن if تتحقق من شرط قبل اتخاذ القرار.',
                        'max_score' => 2.00,
                        'requires' => ['py_if_checks_condition'],
                        'blocked_by' => ['py_if_always_executes_claim'],
                        'sample_good' =>
                            'تتحقق if من شرط مثل العمر أكبر من 18.',
                        'sample_bad' =>
                            'if تنفذ الأوامر دائمًا بلا شرط.',
                        'feedback_pass' =>
                            'وضحت أن if تستخدم للتحقق من شرط.',
                        'feedback_fail' =>
                            'لم توضّح أن وظيفة if الأساسية هي التحقق من شرط.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_IF_EXECUTES_WHEN_TRUE',
                        'name_ar' => 'تنفيذ الكود عند تحقق الشرط',
                        'description_ar' =>
                            'يوضح أن الكود داخل if ينفذ عندما يكون الشرط صحيحًا.',
                        'max_score' => 2.00,
                        'requires' => ['py_if_executes_when_true'],
                        'blocked_by' => ['py_if_always_executes_claim'],
                        'sample_good' =>
                            'ينفذ print عندما تكون قيمة الشرط True.',
                        'sample_bad' =>
                            'ينفذ الكود حتى لو كان الشرط False.',
                        'feedback_pass' =>
                            'وضحت أن الكود داخل if ينفذ عند تحقق الشرط.',
                        'feedback_fail' =>
                            'لم توضّح متى ينفذ الكود الموجود داخل if.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_IF_VALID_EXAMPLE',
                        'name_ar' => 'مثال صحيح على if',
                        'description_ar' =>
                            'يقدم مثالًا صحيحًا ومناسبًا لجملة if.',
                        'max_score' => 1.00,
                        'requires' => ['py_if_valid_example'],
                        'blocked_by' => [],
                        'sample_good' =>
                            'if age >= 18: print("Adult")',
                        'sample_bad' =>
                            'if = age 18',
                        'feedback_pass' =>
                            'قدمت مثالًا صحيحًا على if.',
                        'feedback_fail' =>
                            'أضف مثالًا صحيحًا يحتوي شرطًا وجملة if.',
                        'order' => 3,
                    ],
                ],
                'contradictions' => [
                    [
                        'code' => 'PY_IF_CONFLICT_ALWAYS_EXECUTES',
                        'trigger_concept' => 'py_if_always_executes_claim',
                        'feedback_ar' =>
                            'جملة if لا تنفذ الكود دائمًا؛ بل تنفذه عندما '
                            . 'تكون نتيجة الشرط صحيحة.',
                        'blocked_rubrics' => [
                            'PY_IF_CHECKS_CONDITION',
                            'PY_IF_EXECUTES_WHEN_TRUE',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'ما الفرق بين for و while في Python؟',
                'topic' => 'loops_for_while',
                'rule_set_code' => 'PY_FOR_WHILE_V1',
                'concepts' => [
                    [
                        'code' => 'py_for_iterates_collection_or_range',
                        'name_ar' => 'for تكرر على عناصر أو نطاق',
                        'name_en' => 'For iterates a collection or range',
                        'description_ar' =>
                            'يوضح أن for تستخدم للتكرار على عناصر مجموعة أو range.',
                        'claim_ar' =>
                            'الطالب يذكر أن for تستخدم للتكرار على عناصر '
                            . 'قائمة أو مجموعة أو range.',
                        'claim_en' =>
                            'The student states that a for loop iterates '
                            . 'over items in a collection or a range.',
                    ],
                    [
                        'code' => 'py_while_repeats_while_condition_true',
                        'name_ar' => 'while تكرر ما دام الشرط صحيحًا',
                        'name_en' => 'While repeats while condition is true',
                        'description_ar' =>
                            'يوضح أن while تستمر في التكرار ما دام الشرط صحيحًا.',
                        'claim_ar' =>
                            'الطالب يذكر أن while تكرر تنفيذ الكود ما دام '
                            . 'الشرط صحيحًا أو متحققًا.',
                        'claim_en' =>
                            'The student states that a while loop repeats '
                            . 'code while its condition remains true.',
                    ],
                    [
                        'code' => 'py_for_while_usage_difference',
                        'name_ar' => 'الفرق في استخدام for وwhile',
                        'name_en' => 'For and while usage difference',
                        'description_ar' =>
                            'يفرق بين for للتكرار على عناصر وwhile لشرط متغير.',
                        'claim_ar' =>
                            'الطالب يوضح أن for مناسبة غالبًا عندما نكرر '
                            . 'على عناصر معروفة أو range، بينما while مناسبة '
                            . 'عندما يعتمد التكرار على استمرار شرط.',
                        'claim_en' =>
                            'The student explains that for is commonly used '
                            . 'for known items or ranges, while while is used '
                            . 'when repetition depends on a condition.',
                    ],
                    [
                        'code' => 'py_for_while_identical_claim',
                        'name_ar' => 'ادعاء أن for وwhile بلا فرق',
                        'name_en' => 'For and while are identical claim',
                        'description_ar' =>
                            'ادعاء خاطئ بأن for وwhile لهما نفس الاستخدام دائمًا.',
                        'claim_ar' =>
                            'الطالب يذكر أن for وwhile متطابقتان تمامًا ولا '
                            . 'يوجد فرق في طريقة استخدامهما.',
                        'claim_en' =>
                            'The student claims that for and while are '
                            . 'completely identical and have no usage difference.',
                    ],
                ],
                'rubrics' => [
                    [
                        'code' => 'PY_LOOP_FOR_USAGE',
                        'name_ar' => 'استخدام for',
                        'description_ar' =>
                            'يوضح أن for تكرر على عناصر أو نطاق.',
                        'max_score' => 2.00,
                        'requires' => ['py_for_iterates_collection_or_range'],
                        'blocked_by' => ['py_for_while_identical_claim'],
                        'sample_good' => 'for item in items: ...',
                        'sample_bad' => 'for تعمل فقط عندما يكون شرط while صحيحًا.',
                        'feedback_pass' => 'وضحت استخدام for للتكرار على العناصر أو range.',
                        'feedback_fail' => 'لم توضّح أن for تكرر على عناصر أو نطاق.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_LOOP_WHILE_USAGE',
                        'name_ar' => 'استخدام while',
                        'description_ar' =>
                            'يوضح أن while تستمر ما دام الشرط صحيحًا.',
                        'max_score' => 2.00,
                        'requires' => ['py_while_repeats_while_condition_true'],
                        'blocked_by' => ['py_for_while_identical_claim'],
                        'sample_good' => 'while count < 5: ...',
                        'sample_bad' => 'while لا تحتاج أي شرط.',
                        'feedback_pass' => 'وضحت أن while تكرر ما دام الشرط صحيحًا.',
                        'feedback_fail' => 'لم توضّح أن while تعتمد على استمرار الشرط.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_LOOP_DIFFERENCE',
                        'name_ar' => 'الفرق بين for وwhile',
                        'description_ar' =>
                            'يوضح متى نستخدم كل نوع من الحلقات.',
                        'max_score' => 1.00,
                        'requires' => ['py_for_while_usage_difference'],
                        'blocked_by' => ['py_for_while_identical_claim'],
                        'sample_good' =>
                            'for لعناصر قائمة وwhile عند استمرار شرط مثل إدخال صحيح.',
                        'sample_bad' => 'لا يوجد أي فرق بينهما.',
                        'feedback_pass' => 'فرّقت بشكل صحيح بين استخدام for وwhile.',
                        'feedback_fail' => 'لم توضّح الفرق العملي بين استخدام for وwhile.',
                        'order' => 3,
                    ],
                ],
                'contradictions' => [
                    [
                        'code' => 'PY_LOOP_CONFLICT_IDENTICAL',
                        'trigger_concept' => 'py_for_while_identical_claim',
                        'feedback_ar' =>
                            'for وwhile كلتاهما للحلقات، لكن for تستخدم غالبًا '
                            . 'للتكرار على عناصر أو نطاق وwhile تعتمد على شرط.',
                        'blocked_rubrics' => [
                            'PY_LOOP_FOR_USAGE',
                            'PY_LOOP_WHILE_USAGE',
                            'PY_LOOP_DIFFERENCE',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'اشرح الفرق بين list و tuple في Python. متى تفضّل استخدام كل منهما؟',
                'topic' => 'list_vs_tuple',
                'rule_set_code' => 'PY_LIST_TUPLE_V1',
                'concepts' => [
                    [
                        'code' => 'py_list_is_mutable',
                        'name_ar' => 'list قابلة للتعديل',
                        'name_en' => 'List is mutable',
                        'description_ar' =>
                            'يوضح أن عناصر list يمكن تعديلها أو إضافتها أو حذفها.',
                        'claim_ar' =>
                            'الطالب يذكر أن list قابلة للتعديل أو يمكن تغيير '
                            . 'عناصرها أو إضافة وحذف عناصر منها.',
                        'claim_en' =>
                            'The student states that a list is mutable and '
                            . 'its items can be changed, added, or removed.',
                    ],
                    [
                        'code' => 'py_tuple_is_immutable',
                        'name_ar' => 'tuple غير قابلة للتعديل',
                        'name_en' => 'Tuple is immutable',
                        'description_ar' =>
                            'يوضح أن عناصر tuple لا تعدل بعد إنشائها.',
                        'claim_ar' =>
                            'الطالب يذكر أن tuple غير قابلة للتعديل أو لا '
                            . 'يمكن تغيير عناصرها بعد إنشائها.',
                        'claim_en' =>
                            'The student states that a tuple is immutable '
                            . 'and its items cannot be changed after creation.',
                    ],
                    [
                        'code' => 'py_list_tuple_use_case',
                        'name_ar' => 'حالة استخدام list وtuple',
                        'name_en' => 'List and tuple use case',
                        'description_ar' =>
                            'يربط list بالبيانات المتغيرة وtuple بالبيانات الثابتة.',
                        'claim_ar' =>
                            'الطالب يوضح أن list تفضل عندما نحتاج تعديل البيانات، '
                            . 'وtuple تفضل عندما نريد قيماً ثابتة لا تتغير.',
                        'claim_en' =>
                            'The student explains that lists are preferred '
                            . 'for changeable data and tuples for fixed values.',
                    ],
                    [
                        'code' => 'py_list_is_immutable_claim',
                        'name_ar' => 'ادعاء أن list غير قابلة للتعديل',
                        'name_en' => 'List is immutable claim',
                        'description_ar' =>
                            'ادعاء خاطئ بأن عناصر list لا يمكن تعديلها.',
                        'claim_ar' =>
                            'الطالب يذكر أن list غير قابلة للتعديل أو لا '
                            . 'يمكن تغيير عناصرها.',
                        'claim_en' =>
                            'The student claims that a list is immutable '
                            . 'and its items cannot be changed.',
                    ],
                    [
                        'code' => 'py_tuple_is_mutable_claim',
                        'name_ar' => 'ادعاء أن tuple قابلة للتعديل',
                        'name_en' => 'Tuple is mutable claim',
                        'description_ar' =>
                            'ادعاء خاطئ بأن عناصر tuple يمكن تعديلها.',
                        'claim_ar' =>
                            'الطالب يذكر أن tuple قابلة للتعديل أو يمكن '
                            . 'تغيير عناصرها بعد إنشائها.',
                        'claim_en' =>
                            'The student claims that a tuple is mutable '
                            . 'and its items can be changed after creation.',
                    ],
                ],
                'rubrics' => [
                    [
                        'code' => 'PY_LIST_MUTABLE',
                        'name_ar' => 'قابلية تعديل list',
                        'description_ar' => 'يوضح أن list قابلة للتعديل.',
                        'max_score' => 2.00,
                        'requires' => ['py_list_is_mutable'],
                        'blocked_by' => ['py_list_is_immutable_claim'],
                        'sample_good' => 'يمكن إضافة عنصر إلى list أو تغيير عنصر فيها.',
                        'sample_bad' => 'لا يمكن تغيير عناصر list.',
                        'feedback_pass' => 'وضحت أن list قابلة للتعديل.',
                        'feedback_fail' => 'لم توضّح أن list قابلة للتعديل.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_TUPLE_IMMUTABLE',
                        'name_ar' => 'عدم قابلية تعديل tuple',
                        'description_ar' => 'يوضح أن tuple غير قابلة للتعديل.',
                        'max_score' => 2.00,
                        'requires' => ['py_tuple_is_immutable'],
                        'blocked_by' => ['py_tuple_is_mutable_claim'],
                        'sample_good' => 'لا يمكن تغيير عناصر tuple بعد إنشائها.',
                        'sample_bad' => 'يمكن حذف أو تعديل عنصر في tuple.',
                        'feedback_pass' => 'وضحت أن tuple غير قابلة للتعديل.',
                        'feedback_fail' => 'لم توضّح أن tuple غير قابلة للتعديل.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_LIST_TUPLE_USE_CASE',
                        'name_ar' => 'اختيار list أو tuple',
                        'description_ar' =>
                            'يوضح متى تكون list أو tuple مناسبة.',
                        'max_score' => 1.00,
                        'requires' => ['py_list_tuple_use_case'],
                        'blocked_by' => [
                            'py_list_is_immutable_claim',
                            'py_tuple_is_mutable_claim',
                        ],
                        'sample_good' =>
                            'أستخدم list للمهام التي تتغير وtuple للإحداثيات الثابتة.',
                        'sample_bad' => 'أستخدم tuple عندما أريد تعديل عناصرها دائمًا.',
                        'feedback_pass' => 'وضحت حالة استخدام مناسبة لكل نوع.',
                        'feedback_fail' => 'لم توضّح متى تفضّل list أو tuple.',
                        'order' => 3,
                    ],
                ],
                'contradictions' => [
                    [
                        'code' => 'PY_LIST_TUPLE_CONFLICT_LIST_IMMUTABLE',
                        'trigger_concept' => 'py_list_is_immutable_claim',
                        'feedback_ar' =>
                            'list قابلة للتعديل؛ يمكن تغيير عناصرها أو إضافة وحذف عناصر.',
                        'blocked_rubrics' => [
                            'PY_LIST_MUTABLE',
                            'PY_LIST_TUPLE_USE_CASE',
                        ],
                    ],
                    [
                        'code' => 'PY_LIST_TUPLE_CONFLICT_TUPLE_MUTABLE',
                        'trigger_concept' => 'py_tuple_is_mutable_claim',
                        'feedback_ar' =>
                            'tuple غير قابلة للتعديل بعد إنشائها.',
                        'blocked_rubrics' => [
                            'PY_TUPLE_IMMUTABLE',
                            'PY_LIST_TUPLE_USE_CASE',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'كيف تتعامل مع الملفات في Python لقراءة نص من ملف؟',
                'topic' => 'file_reading',
                'rule_set_code' => 'PY_FILE_READING_V1',
                'concepts' => [
                    [
                        'code' => 'py_file_open_for_reading',
                        'name_ar' => 'فتح الملف للقراءة',
                        'name_en' => 'Open a file for reading',
                        'description_ar' =>
                            'يوضح استخدام open للقراءة بوضع r أو وضع القراءة الافتراضي.',
                        'claim_ar' =>
                            'الطالب يذكر فتح الملف للقراءة باستخدام open مع '
                            . 'الوضع r أو وضع القراءة الافتراضي.',
                        'claim_en' =>
                            'The student states that a file is opened for '
                            . 'reading using open with r mode or default read mode.',
                    ],
                    [
                        'code' => 'py_file_uses_with_open',
                        'name_ar' => 'استخدام with open',
                        'name_en' => 'Use with open',
                        'description_ar' =>
                            'يوضح استخدام with open لإدارة الملف بأمان.',
                        'claim_ar' =>
                            'الطالب يذكر استخدام with open عند فتح الملف '
                            . 'للقراءة.',
                        'claim_en' =>
                            'The student states that with open is used when '
                            . 'opening a file for reading.',
                    ],
                    [
                        'code' => 'py_file_reads_content',
                        'name_ar' => 'قراءة محتوى الملف',
                        'name_en' => 'Read file content',
                        'description_ar' =>
                            'يوضح استخدام read أو طريقة قراءة مناسبة للمحتوى.',
                        'claim_ar' =>
                            'الطالب يذكر استخدام read() أو readline() أو '
                            . 'readlines() لقراءة محتوى الملف.',
                        'claim_en' =>
                            'The student states that read(), readline(), or '
                            . 'readlines() is used to obtain file content.',
                    ],
                    [
                        'code' => 'py_file_valid_read_example',
                        'name_ar' => 'مثال صحيح لقراءة ملف',
                        'name_en' => 'Valid file-reading example',
                        'description_ar' =>
                            'يقدم مثالًا صحيحًا يجمع with open وقراءة المحتوى.',
                        'claim_ar' =>
                            'الطالب يقدم مثال Python صحيحًا يستخدم with open '
                            . 'لفتح ملف وread أو طريقة قراءة للمحتوى.',
                        'claim_en' =>
                            'The student provides a valid Python example using '
                            . 'with open and read or another reading method.',
                    ],
                    [
                        'code' => 'py_file_write_mode_for_read_claim',
                        'name_ar' => 'ادعاء استخدام وضع الكتابة للقراءة',
                        'name_en' => 'Use write mode for reading claim',
                        'description_ar' =>
                            'ادعاء خاطئ بأن وضع w هو الوضع المناسب لقراءة النص.',
                        'claim_ar' =>
                            'الطالب يذكر أن الملف يجب فتحه بوضع w أو الكتابة '
                            . 'من أجل قراءة محتواه.',
                        'claim_en' =>
                            'The student claims that a file should be opened '
                            . 'in w or write mode to read its content.',
                    ],
                ],
                'rubrics' => [
                    [
                        'code' => 'PY_FILE_OPEN_READ',
                        'name_ar' => 'فتح الملف للقراءة',
                        'description_ar' => 'يوضح فتح الملف بوضع القراءة.',
                        'max_score' => 1.00,
                        'requires' => ['py_file_open_for_reading'],
                        'blocked_by' => ['py_file_write_mode_for_read_claim'],
                        'sample_good' => 'open("notes.txt", "r")',
                        'sample_bad' => 'open("notes.txt", "w") لقراءة النص.',
                        'feedback_pass' => 'وضحت فتح الملف للقراءة.',
                        'feedback_fail' => 'لم توضّح فتح الملف بوضع القراءة.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_FILE_WITH_OPEN',
                        'name_ar' => 'استخدام with لإدارة الملف',
                        'description_ar' =>
                            'يوضح استخدام with open لإغلاق الملف بأمان.',
                        'max_score' => 2.00,
                        'requires' => ['py_file_uses_with_open'],
                        'blocked_by' => ['py_file_write_mode_for_read_claim'],
                        'sample_good' => 'with open("notes.txt", "r") as file:',
                        'sample_bad' => 'افتح الملف بوضع w للقراءة.',
                        'feedback_pass' => 'استخدمت with open بطريقة صحيحة.',
                        'feedback_fail' => 'لم تذكر استخدام with open لإدارة الملف بأمان.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_FILE_READ_CONTENT',
                        'name_ar' => 'قراءة المحتوى',
                        'description_ar' => 'يوضح قراءة النص من الملف.',
                        'max_score' => 1.00,
                        'requires' => ['py_file_reads_content'],
                        'blocked_by' => [],
                        'sample_good' => 'text = file.read()',
                        'sample_bad' => 'file.write() لقراءة المحتوى.',
                        'feedback_pass' => 'وضحت طريقة قراءة محتوى الملف.',
                        'feedback_fail' => 'لم تذكر طريقة لقراءة محتوى الملف مثل read().',
                        'order' => 3,
                    ],
                    [
                        'code' => 'PY_FILE_VALID_EXAMPLE',
                        'name_ar' => 'مثال متكامل لقراءة ملف',
                        'description_ar' =>
                            'يقدم مثالًا صحيحًا يجمع فتح الملف وقراءة النص.',
                        'max_score' => 1.00,
                        'requires' => ['py_file_valid_read_example'],
                        'blocked_by' => [],
                        'sample_good' =>
                            'with open("notes.txt", "r") as file: text = file.read()',
                        'sample_bad' => 'open("notes.txt", "w") ثم read().',
                        'feedback_pass' => 'قدمت مثالًا صحيحًا لقراءة ملف.',
                        'feedback_fail' => 'أضف مثالًا صحيحًا يستخدم with open وread().',
                        'order' => 4,
                    ],
                ],
                'contradictions' => [
                    [
                        'code' => 'PY_FILE_CONFLICT_WRITE_FOR_READ',
                        'trigger_concept' => 'py_file_write_mode_for_read_claim',
                        'feedback_ar' =>
                            'وضع w مخصص للكتابة وقد يفرغ الملف، أما القراءة فتستخدم '
                            . 'وضع r أو الوضع الافتراضي.',
                        'blocked_rubrics' => [
                            'PY_FILE_OPEN_READ',
                            'PY_FILE_WITH_OPEN',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'اكتب دالة تأخذ قائمة أرقام وتُرجع قائمة جديدة تحتوي فقط على الأرقام الزوجية باستخدام Python.',
                'topic' => 'functions_even_numbers',
                'rule_set_code' => 'PY_EVEN_NUMBERS_FUNCTION_V1',
                'concepts' => [
                    [
                        'code' => 'py_even_function_accepts_list',
                        'name_ar' => 'الدالة تستقبل قائمة',
                        'name_en' => 'Function accepts a list',
                        'description_ar' =>
                            'يوضح أن الدالة لها معامل يمثل قائمة أرقام.',
                        'claim_ar' =>
                            'الطالب يقدم دالة أو يذكر أن الدالة تستقبل قائمة '
                            . 'أرقام كمعامل.',
                        'claim_en' =>
                            'The student provides a function or states that '
                            . 'the function accepts a list of numbers.',
                    ],
                    [
                        'code' => 'py_even_iterates_values',
                        'name_ar' => 'التكرار على عناصر القائمة',
                        'name_en' => 'Iterate over list values',
                        'description_ar' =>
                            'يوضح المرور على عناصر القائمة باستخدام for أو list comprehension.',
                        'claim_ar' =>
                            'الطالب يذكر أو يقدم تكرارًا على عناصر قائمة الأرقام '
                            . 'باستخدام for أو list comprehension.',
                        'claim_en' =>
                            'The student states or provides iteration over '
                            . 'number-list items using for or list comprehension.',
                    ],
                    [
                        'code' => 'py_even_checks_modulo_zero',
                        'name_ar' => 'فحص العدد الزوجي',
                        'name_en' => 'Check even number with modulo',
                        'description_ar' =>
                            'يوضح استخدام n % 2 == 0 لاختيار الأعداد الزوجية.',
                        'claim_ar' =>
                            'الطالب يذكر أو يقدم شرطًا صحيحًا لفحص العدد الزوجي '
                            . 'مثل n % 2 == 0.',
                        'claim_en' =>
                            'The student states or provides a correct even '
                            . 'number check such as n % 2 == 0.',
                    ],
                    [
                        'code' => 'py_even_returns_new_list',
                        'name_ar' => 'إرجاع قائمة جديدة',
                        'name_en' => 'Return a new list',
                        'description_ar' =>
                            'يوضح أن الناتج قائمة جديدة تحتوي الأعداد الزوجية.',
                        'claim_ar' =>
                            'الطالب يذكر أو يقدم return لقائمة جديدة تحتوي '
                            . 'الأرقام الزوجية فقط.',
                        'claim_en' =>
                            'The student states or provides a return of a '
                            . 'new list containing only even numbers.',
                    ],
                    [
                        'code' => 'py_even_uses_odd_condition_claim',
                        'name_ar' => 'ادعاء أن شرط الفردي يختار الزوجي',
                        'name_en' => 'Odd condition selects even numbers claim',
                        'description_ar' =>
                            'ادعاء خاطئ بأن n % 2 == 1 هو شرط اختيار الأعداد الزوجية.',
                        'claim_ar' =>
                            'الطالب يذكر أن n % 2 == 1 أو n % 2 != 0 هو '
                            . 'الشرط الذي يختار الأعداد الزوجية.',
                        'claim_en' =>
                            'The student claims that n % 2 == 1 or n % 2 != 0 '
                            . 'selects even numbers.',
                    ],
                ],
                'rubrics' => [
                    [
                        'code' => 'PY_EVEN_ACCEPTS_LIST',
                        'name_ar' => 'استقبال قائمة أرقام',
                        'description_ar' =>
                            'يوضح أن الدالة تستقبل قائمة أرقام.',
                        'max_score' => 1.00,
                        'requires' => ['py_even_function_accepts_list'],
                        'blocked_by' => [],
                        'sample_good' => 'def even_numbers(numbers):',
                        'sample_bad' => 'دالة بلا أي قائمة مدخلة.',
                        'feedback_pass' => 'قدمت دالة تستقبل قائمة أرقام.',
                        'feedback_fail' => 'لم توضّح أن الدالة تستقبل قائمة أرقام.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_EVEN_ITERATES',
                        'name_ar' => 'التكرار على القائمة',
                        'description_ar' =>
                            'يوضح المرور على عناصر القائمة.',
                        'max_score' => 1.00,
                        'requires' => ['py_even_iterates_values'],
                        'blocked_by' => [],
                        'sample_good' => 'for number in numbers:',
                        'sample_bad' => 'لا يوجد مرور على عناصر القائمة.',
                        'feedback_pass' => 'وضحت التكرار على عناصر القائمة.',
                        'feedback_fail' => 'لم توضّح كيف تمر على عناصر القائمة.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_EVEN_CONDITION',
                        'name_ar' => 'شرط اختيار الأعداد الزوجية',
                        'description_ar' =>
                            'يستخدم شرط modulo الصحيح لاختيار الأعداد الزوجية.',
                        'max_score' => 2.00,
                        'requires' => ['py_even_checks_modulo_zero'],
                        'blocked_by' => ['py_even_uses_odd_condition_claim'],
                        'sample_good' => 'if number % 2 == 0:',
                        'sample_bad' => 'if number % 2 == 1: للأعداد الزوجية.',
                        'feedback_pass' => 'استخدمت شرطًا صحيحًا لاختيار الأعداد الزوجية.',
                        'feedback_fail' => 'لم تستخدم شرطًا صحيحًا مثل number % 2 == 0.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'PY_EVEN_RETURNS_LIST',
                        'name_ar' => 'إرجاع قائمة جديدة',
                        'description_ar' =>
                            'يرجع قائمة جديدة تحتوي فقط الأعداد الزوجية.',
                        'max_score' => 1.00,
                        'requires' => ['py_even_returns_new_list'],
                        'blocked_by' => ['py_even_uses_odd_condition_claim'],
                        'sample_good' => 'return evens',
                        'sample_bad' => 'return رقم فردي واحد.',
                        'feedback_pass' => 'أرجعت قائمة جديدة بالأعداد الزوجية.',
                        'feedback_fail' => 'لم توضّح إرجاع قائمة جديدة بالأعداد الزوجية.',
                        'order' => 4,
                    ],
                ],
                'contradictions' => [
                    [
                        'code' => 'PY_EVEN_CONFLICT_ODD_CONDITION',
                        'trigger_concept' => 'py_even_uses_odd_condition_claim',
                        'feedback_ar' =>
                            'شرط العدد الزوجي هو n % 2 == 0، أما باقي القسمة 1 '
                            . 'فيدل على عدد فردي.',
                        'blocked_rubrics' => [
                            'PY_EVEN_CONDITION',
                            'PY_EVEN_RETURNS_LIST',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'ما هو Exception Handling في Python؟ وكيف تستخدم try و except؟',
                'topic' => 'exception_handling',
                'rule_set_code' => 'PY_EXCEPTION_HANDLING_V1',
                'concepts' => [
                    [
                        'code' => 'py_exception_is_runtime_error',
                        'name_ar' => 'الاستثناء خطأ أثناء التنفيذ',
                        'name_en' => 'Exception is a runtime error',
                        'description_ar' =>
                            'يوضح أن exception مشكلة أو خطأ يحدث أثناء تشغيل البرنامج.',
                        'claim_ar' =>
                            'الطالب يذكر أن exception هو خطأ أو مشكلة قد تحدث '
                            . 'أثناء تنفيذ البرنامج.',
                        'claim_en' =>
                            'The student states that an exception is an error '
                            . 'or problem that can occur while a program runs.',
                    ],
                    [
                        'code' => 'py_try_wraps_risky_code',
                        'name_ar' => 'try تحتوي الكود المعرض للخطأ',
                        'name_en' => 'Try wraps risky code',
                        'description_ar' =>
                            'يوضح أن try توضع حول الكود الذي قد يرفع exception.',
                        'claim_ar' =>
                            'الطالب يذكر أن try تحتوي الكود الذي قد يسبب خطأ '
                            . 'أو exception.',
                        'claim_en' =>
                            'The student states that try contains code that '
                            . 'may raise an exception or error.',
                    ],
                    [
                        'code' => 'py_except_handles_exception',
                        'name_ar' => 'except تعالج الاستثناء',
                        'name_en' => 'Except handles an exception',
                        'description_ar' =>
                            'يوضح أن except تلتقط الخطأ وتنفذ معالجة مناسبة.',
                        'claim_ar' =>
                            'الطالب يذكر أن except تلتقط exception أو تتعامل '
                            . 'مع الخطأ عند حدوثه.',
                        'claim_en' =>
                            'The student states that except catches or handles '
                            . 'an exception when it occurs.',
                    ],
                    [
                        'code' => 'py_try_except_valid_example',
                        'name_ar' => 'مثال صحيح على try وexcept',
                        'name_en' => 'Valid try except example',
                        'description_ar' =>
                            'يقدم مثالًا صحيحًا يتضمن try وexcept.',
                        'claim_ar' =>
                            'الطالب يقدم مثال Python صحيحًا يحتوي try وexcept '
                            . 'لمعالجة خطأ.',
                        'claim_en' =>
                            'The student provides a valid Python example using '
                            . 'try and except to handle an error.',
                    ],
                    [
                        'code' => 'py_except_prevents_try_execution_claim',
                        'name_ar' => 'ادعاء أن except تمنع try من التنفيذ',
                        'name_en' => 'Except prevents try execution claim',
                        'description_ar' =>
                            'ادعاء خاطئ بأن except تمنع أو تلغي تنفيذ كود try دائمًا.',
                        'claim_ar' =>
                            'الطالب يذكر أن except تمنع تنفيذ كود try أو أن '
                            . 'try لا تنفذ عند وجود except.',
                        'claim_en' =>
                            'The student claims that except prevents try code '
                            . 'from executing or that try does not run when except exists.',
                    ],
                ],
                'rubrics' => [
                    [
                        'code' => 'PY_EXC_DEFINE_EXCEPTION',
                        'name_ar' => 'تعريف exception',
                        'description_ar' =>
                            'يوضح أن exception مشكلة تحدث أثناء التنفيذ.',
                        'max_score' => 1.00,
                        'requires' => ['py_exception_is_runtime_error'],
                        'blocked_by' => [],
                        'sample_good' => 'الـ exception خطأ قد يحدث أثناء تشغيل البرنامج.',
                        'sample_bad' => 'exception هي دالة لطباعة النص فقط.',
                        'feedback_pass' => 'وضحت معنى exception.',
                        'feedback_fail' => 'لم توضّح أن exception مشكلة قد تحدث أثناء التنفيذ.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_EXC_TRY_USAGE',
                        'name_ar' => 'دور try',
                        'description_ar' =>
                            'يوضح أن try تحتوي الكود المعرض للخطأ.',
                        'max_score' => 2.00,
                        'requires' => ['py_try_wraps_risky_code'],
                        'blocked_by' => ['py_except_prevents_try_execution_claim'],
                        'sample_good' => 'ضع القسمة أو قراءة ملف داخل try.',
                        'sample_bad' => 'try لا تنفذ أي كود عند وجود except.',
                        'feedback_pass' => 'وضحت دور try في وضع الكود المعرض للخطأ.',
                        'feedback_fail' => 'لم توضّح أن try تحتوي الكود الذي قد يسبب exception.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_EXC_EXCEPT_USAGE',
                        'name_ar' => 'دور except',
                        'description_ar' =>
                            'يوضح أن except تعالج الخطأ عند وقوعه.',
                        'max_score' => 1.00,
                        'requires' => ['py_except_handles_exception'],
                        'blocked_by' => ['py_except_prevents_try_execution_claim'],
                        'sample_good' => 'except ValueError: print("Invalid input")',
                        'sample_bad' => 'except تمنع try من العمل.',
                        'feedback_pass' => 'وضحت أن except تتعامل مع الخطأ.',
                        'feedback_fail' => 'لم توضّح دور except في معالجة exception.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'PY_EXC_VALID_EXAMPLE',
                        'name_ar' => 'مثال صحيح على المعالجة',
                        'description_ar' =>
                            'يقدم مثالًا صحيحًا يجمع try وexcept.',
                        'max_score' => 1.00,
                        'requires' => ['py_try_except_valid_example'],
                        'blocked_by' => [],
                        'sample_good' =>
                            'try: value = int(text) except ValueError: print("Invalid")',
                        'sample_bad' => 'try بلا except لمعالجة الخطأ.',
                        'feedback_pass' => 'قدمت مثالًا صحيحًا على try وexcept.',
                        'feedback_fail' => 'أضف مثالًا صحيحًا يحتوي try وexcept.',
                        'order' => 4,
                    ],
                ],
                'contradictions' => [
                    [
                        'code' => 'PY_EXC_CONFLICT_EXCEPT_PREVENTS_TRY',
                        'trigger_concept' => 'py_except_prevents_try_execution_claim',
                        'feedback_ar' =>
                            'كود try ينفذ أولًا، وعند حدوث exception تنتقل المعالجة '
                            . 'إلى except المناسبة.',
                        'blocked_rubrics' => [
                            'PY_EXC_TRY_USAGE',
                            'PY_EXC_EXCEPT_USAGE',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'اشرح مفهوم OOP في Python واذكر مثالًا على class و object.',
                'topic' => 'oop_class_object',
                'rule_set_code' => 'PY_OOP_CLASS_OBJECT_V1',
                'concepts' => [
                    [
                        'code' => 'py_class_is_blueprint',
                        'name_ar' => 'class قالب للكائنات',
                        'name_en' => 'Class is a blueprint',
                        'description_ar' =>
                            'يوضح أن class تمثل قالبًا أو تعريفًا لإنشاء objects.',
                        'claim_ar' =>
                            'الطالب يذكر أن class هي قالب أو مخطط أو تعريف '
                            . 'ننشئ منه objects.',
                        'claim_en' =>
                            'The student states that a class is a blueprint '
                            . 'or definition used to create objects.',
                    ],
                    [
                        'code' => 'py_object_is_class_instance',
                        'name_ar' => 'object نسخة من class',
                        'name_en' => 'Object is a class instance',
                        'description_ar' =>
                            'يوضح أن object هو instance منشأ من class.',
                        'claim_ar' =>
                            'الطالب يذكر أن object هو instance أو كائن منشأ '
                            . 'من class.',
                        'claim_en' =>
                            'The student states that an object is an instance '
                            . 'created from a class.',
                    ],
                    [
                        'code' => 'py_class_object_valid_example',
                        'name_ar' => 'مثال صحيح على class وobject',
                        'name_en' => 'Valid class object example',
                        'description_ar' =>
                            'يقدم مثالًا صحيحًا لتعريف class وإنشاء object منها.',
                        'claim_ar' =>
                            'الطالب يقدم مثال Python صحيحًا يعرّف class ثم '
                            . 'ينشئ object أو instance منها.',
                        'claim_en' =>
                            'The student provides a valid Python example that '
                            . 'defines a class and creates an object from it.',
                    ],
                    [
                        'code' => 'py_class_object_same_claim',
                        'name_ar' => 'ادعاء أن class وobject الشيء نفسه',
                        'name_en' => 'Class and object are the same claim',
                        'description_ar' =>
                            'ادعاء خاطئ بأن class وobject لا فرق بينهما.',
                        'claim_ar' =>
                            'الطالب يذكر أن class وobject الشيء نفسه تمامًا '
                            . 'ولا يوجد فرق بينهما.',
                        'claim_en' =>
                            'The student claims that a class and an object '
                            . 'are exactly the same and have no difference.',
                    ],
                ],
                'rubrics' => [
                    [
                        'code' => 'PY_OOP_CLASS_BLUEPRINT',
                        'name_ar' => 'معنى class',
                        'description_ar' => 'يوضح أن class قالب لإنشاء objects.',
                        'max_score' => 2.00,
                        'requires' => ['py_class_is_blueprint'],
                        'blocked_by' => ['py_class_object_same_claim'],
                        'sample_good' => 'class هي قالب مثل Car.',
                        'sample_bad' => 'class وobject لا فرق بينهما.',
                        'feedback_pass' => 'وضحت أن class قالب لإنشاء objects.',
                        'feedback_fail' => 'لم توضّح أن class تمثل قالبًا أو مخططًا.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_OOP_OBJECT_INSTANCE',
                        'name_ar' => 'معنى object',
                        'description_ar' => 'يوضح أن object instance من class.',
                        'max_score' => 2.00,
                        'requires' => ['py_object_is_class_instance'],
                        'blocked_by' => ['py_class_object_same_claim'],
                        'sample_good' => 'car1 = Car() هو object من class Car.',
                        'sample_bad' => 'object هو class نفسها.',
                        'feedback_pass' => 'وضحت أن object هو instance من class.',
                        'feedback_fail' => 'لم توضّح أن object هو instance منشأ من class.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_OOP_VALID_EXAMPLE',
                        'name_ar' => 'مثال صحيح على class وobject',
                        'description_ar' =>
                            'يقدم مثالًا يعرّف class وينشئ object منها.',
                        'max_score' => 1.00,
                        'requires' => ['py_class_object_valid_example'],
                        'blocked_by' => [],
                        'sample_good' =>
                            'class Car: pass ثم car1 = Car()',
                        'sample_bad' => 'class = object',
                        'feedback_pass' => 'قدمت مثالًا صحيحًا على class وobject.',
                        'feedback_fail' => 'أضف مثالًا يعرّف class وينشئ object منها.',
                        'order' => 3,
                    ],
                ],
                'contradictions' => [
                    [
                        'code' => 'PY_OOP_CONFLICT_SAME',
                        'trigger_concept' => 'py_class_object_same_claim',
                        'feedback_ar' =>
                            'class هي قالب أو تعريف، بينما object هو instance '
                            . 'منشأ من هذا القالب.',
                        'blocked_rubrics' => [
                            'PY_OOP_CLASS_BLUEPRINT',
                            'PY_OOP_OBJECT_INSTANCE',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'ما فائدة كتابة اختبارات بسيطة في Python؟ اذكر مثالًا على اختبار unit test.',
                'topic' => 'unit_testing',
                'rule_set_code' => 'PY_UNIT_TESTING_V1',
                'concepts' => [
                    [
                        'code' => 'py_test_verifies_expected_behavior',
                        'name_ar' => 'الاختبار يتحقق من السلوك المتوقع',
                        'name_en' => 'Test verifies expected behavior',
                        'description_ar' =>
                            'يوضح أن الاختبارات تتحقق من أن الدالة تعطي النتيجة المتوقعة.',
                        'claim_ar' =>
                            'الطالب يذكر أن الاختبار يتحقق من السلوك أو '
                            . 'الناتج المتوقع لدالة أو جزء من البرنامج.',
                        'claim_en' =>
                            'The student states that a test verifies expected '
                            . 'behavior or expected output of code.',
                    ],
                    [
                        'code' => 'py_test_catches_errors_or_regressions',
                        'name_ar' => 'الاختبار يكشف الأخطاء والانحدارات',
                        'name_en' => 'Test catches errors or regressions',
                        'description_ar' =>
                            'يوضح أن الاختبارات تساعد على اكتشاف أخطاء أو regression بعد التغيير.',
                        'claim_ar' =>
                            'الطالب يذكر أن الاختبارات تساعد على كشف الأخطاء '
                            . 'أو منع regressions عند تعديل الكود.',
                        'claim_en' =>
                            'The student states that tests help catch bugs or '
                            . 'prevent regressions after code changes.',
                    ],
                    [
                        'code' => 'py_test_valid_assert_example',
                        'name_ar' => 'مثال صحيح على assert أو unit test',
                        'name_en' => 'Valid assert or unit test example',
                        'description_ar' =>
                            'يقدم مثالًا صحيحًا يستخدم assert أو اختبار unit test.',
                        'claim_ar' =>
                            'الطالب يقدم مثالًا صحيحًا لاختبار unit test أو '
                            . 'assert يقارن نتيجة فعلية بنتيجة متوقعة.',
                        'claim_en' =>
                            'The student provides a valid unit-test or assert '
                            . 'example comparing an actual result to an expected result.',
                    ],
                    [
                        'code' => 'py_test_modifies_production_behavior_claim',
                        'name_ar' => 'ادعاء أن الاختبار يغير السلوك الإنتاجي',
                        'name_en' => 'Test changes production behavior claim',
                        'description_ar' =>
                            'ادعاء خاطئ بأن الاختبار وظيفته تغيير منطق الكود الإنتاجي.',
                        'claim_ar' =>
                            'الطالب يذكر أن الاختبار وظيفته تغيير منطق الكود '
                            . 'الإنتاجي أو تعديل النتيجة بدل التحقق منها.',
                        'claim_en' =>
                            'The student claims that a test is meant to change '
                            . 'production logic or modify output instead of verifying it.',
                    ],
                ],
                'rubrics' => [
                    [
                        'code' => 'PY_TEST_EXPECTED_BEHAVIOR',
                        'name_ar' => 'التحقق من السلوك المتوقع',
                        'description_ar' =>
                            'يوضح أن الاختبار يقارن السلوك الفعلي بالمطلوب.',
                        'max_score' => 2.00,
                        'requires' => ['py_test_verifies_expected_behavior'],
                        'blocked_by' => ['py_test_modifies_production_behavior_claim'],
                        'sample_good' => 'نتحقق أن add(2, 3) ترجع 5.',
                        'sample_bad' => 'نغير add داخل الاختبار حتى ترجع 5.',
                        'feedback_pass' => 'وضحت أن الاختبارات تتحقق من السلوك المتوقع.',
                        'feedback_fail' => 'لم توضّح أن الاختبار يتحقق من نتيجة أو سلوك متوقع.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_TEST_ERRORS_REGRESSIONS',
                        'name_ar' => 'كشف الأخطاء والانحدارات',
                        'description_ar' =>
                            'يوضح أن الاختبارات تكشف bugs أو regressions.',
                        'max_score' => 2.00,
                        'requires' => ['py_test_catches_errors_or_regressions'],
                        'blocked_by' => ['py_test_modifies_production_behavior_claim'],
                        'sample_good' => 'تكتشف الاختبارات أن تعديلًا كسر دالة كانت تعمل.',
                        'sample_bad' => 'الاختبار يغير النتيجة حتى تختفي المشكلة.',
                        'feedback_pass' => 'وضحت أن الاختبارات تساعد على كشف الأخطاء والانحدارات.',
                        'feedback_fail' => 'لم توضّح كيف تساعد الاختبارات على كشف الأخطاء أو regressions.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_TEST_VALID_EXAMPLE',
                        'name_ar' => 'مثال صحيح على unit test',
                        'description_ar' =>
                            'يقدم مثالًا صحيحًا يستخدم assert أو unit test.',
                        'max_score' => 1.00,
                        'requires' => ['py_test_valid_assert_example'],
                        'blocked_by' => [],
                        'sample_good' => 'assert add(2, 3) == 5',
                        'sample_bad' => 'assert = 5',
                        'feedback_pass' => 'قدمت مثالًا صحيحًا على unit test أو assert.',
                        'feedback_fail' => 'أضف مثالًا مثل assert add(2, 3) == 5.',
                        'order' => 3,
                    ],
                ],
                'contradictions' => [
                    [
                        'code' => 'PY_TEST_CONFLICT_MODIFIES_PRODUCTION',
                        'trigger_concept' => 'py_test_modifies_production_behavior_claim',
                        'feedback_ar' =>
                            'الاختبار يتحقق من سلوك الكود ولا يغير منطق الكود '
                            . 'الإنتاجي لكي يمر الاختبار.',
                        'blocked_rubrics' => [
                            'PY_TEST_EXPECTED_BEHAVIOR',
                            'PY_TEST_ERRORS_REGRESSIONS',
                        ],
                    ],
                ],
            ],
        ];
    }
}
