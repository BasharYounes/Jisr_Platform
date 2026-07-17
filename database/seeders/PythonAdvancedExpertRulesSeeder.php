<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PythonAdvancedExpertRulesSeeder extends Seeder
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
                'Python advanced Expert Rules data was seeded successfully.'
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
                'Cannot seed Python advanced questions because one or more '
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

        $ruleSetCodeUsedElsewhere = DB::table('assessment_rule_sets')
            ->where('RuleSetCode', $definition['rule_set_code'])
            ->where('QuestionID', '<>', $questionId)
            ->exists();

        if ($ruleSetCodeUsedElsewhere) {
            throw new RuntimeException(
                "RuleSetCode {$definition['rule_set_code']} "
                . "is already assigned to another question."
            );
        }

        /*
         * Safe because guardNoExistingAttempts() already confirmed
         * that none of these questions has historical attempts.
         */
        $this->clearQuestionStructure($questionId);

        DB::table('question_bank')
            ->where('QuestionID', $questionId)
            ->update([
                'QuestionText' => $definition['question_text'],
                'Topic' => $definition['topic'],
                'EvaluationEngine' => 'expert_rules',
                'RuleSetVersion' => 'v1',

                /*
                 * Staged only.
                 * Each question must be tested before real activation.
                 */
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
            if (! isset($conceptIds[$contradiction['trigger_concept']])) {
                throw new RuntimeException(
                    'Unknown contradiction concept: '
                    . $contradiction['trigger_concept']
                );
            }

            $contradictionRuleId = DB::table(
                'assessment_contradiction_rules'
            )->insertGetId([
                'RuleSetID' => $ruleSetId,
                'TriggerConceptID' => (
                    $conceptIds[$contradiction['trigger_concept']]
                ),
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
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }

    private function definitions(): array
    {
        return [
            [
                'question_text' =>
                    'عرّف decorator في Python، واذكر استخدامًا واحدًا له، '
                    . 'ووضح ماذا يضيف للدالة.',
                'topic' => 'decorators',
                'rule_set_code' => 'PY_DECORATOR_V1',

                'concepts' => [
                    [
                        'code' => 'py_dec_wraps_function',
                        'name_ar' => 'الـ decorator يغلّف دالة',
                        'name_en' => 'Decorator wraps a function',
                        'description_ar' =>
                            'يفهم الطالب أن decorator يطبّق على دالة '
                            . 'أو يغلّفها.',
                        'claim_ar' =>
                            'الطالب يذكر أن decorator في Python هو آلية '
                            . 'تغلّف دالة أو تُطبّق على دالة أخرى.',
                        'claim_en' =>
                            'The student states that a Python decorator '
                            . 'wraps or is applied to another function.',
                    ],
                    [
                        'code' => 'py_dec_adds_behavior_without_core_change',
                        'name_ar' => 'الـ decorator يضيف سلوكًا دون تغيير المنطق',
                        'name_en' =>
                            'Decorator adds behavior without core change',
                        'description_ar' =>
                            'يوضح أن decorator يضيف سلوكًا قبل أو بعد '
                            . 'تنفيذ الدالة دون تعديل منطقها الأساسي.',
                        'claim_ar' =>
                            'الطالب يوضح أن decorator يضيف سلوكًا قبل أو '
                            . 'بعد تنفيذ الدالة دون تعديل منطق الدالة الأساسي.',
                        'claim_en' =>
                            'The student explains that a decorator adds '
                            . 'behavior before or after a function runs '
                            . 'without changing its core logic.',
                    ],
                    [
                        'code' => 'py_dec_valid_use_case',
                        'name_ar' => 'استخدام صحيح للـ decorator',
                        'name_en' => 'Valid decorator use case',
                        'description_ar' =>
                            'يقدم استخدامًا صحيحًا مثل logging أو caching '
                            . 'أو authentication أو timing.',
                        'claim_ar' =>
                            'الطالب يذكر استخدامًا صحيحًا للـ decorator '
                            . 'مثل logging أو caching أو authentication '
                            . 'أو timing.',
                        'claim_en' =>
                            'The student provides a valid decorator use case '
                            . 'such as logging, caching, authentication, '
                            . 'or timing.',
                    ],
                    [
                        'code' => 'py_dec_is_inheritance_claim',
                        'name_ar' =>
                            'ادعاء أن الـ decorator هو inheritance بين classes',
                        'name_en' =>
                            'Decorator is class inheritance claim',
                        'description_ar' =>
                            'خلط صريح بين decorator وinheritance.',
                        'claim_ar' =>
                            'الطالب يذكر أن decorator هو inheritance '
                            . 'بين classes أو نوع من وراثة الكائنات.',
                        'claim_en' =>
                            'The student claims that a decorator is class '
                            . 'inheritance or object-oriented inheritance.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'PY_DEC_DEFINE',
                        'name_ar' => 'تعريف decorator',
                        'description_ar' =>
                            'يوضح أن decorator يغلّف دالة أو يُطبّق عليها.',
                        'max_score' => 2.00,
                        'requires' => ['py_dec_wraps_function'],
                        'blocked_by' => ['py_dec_is_inheritance_claim'],
                        'sample_good' =>
                            'الـ decorator يغلّف دالة لإضافة سلوك حولها.',
                        'sample_bad' =>
                            'الـ decorator هو inheritance بين classes.',
                        'feedback_pass' =>
                            'وضحت أن decorator يطبّق على دالة أو يغلّفها.',
                        'feedback_fail' =>
                            'لم توضّح أن decorator يغلّف دالة أو يُطبّق عليها.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_DEC_ADD_BEHAVIOR',
                        'name_ar' =>
                            'إضافة سلوك دون تغيير منطق الدالة',
                        'description_ar' =>
                            'يوضح إضافة سلوك قبل أو بعد التنفيذ دون تغيير '
                            . 'منطق الدالة الأساسي.',
                        'max_score' => 2.00,
                        'requires' => [
                            'py_dec_adds_behavior_without_core_change',
                        ],
                        'blocked_by' => ['py_dec_is_inheritance_claim'],
                        'sample_good' =>
                            'يمكنه تسجيل logging قبل وبعد تشغيل الدالة '
                            . 'دون تعديل جسمها.',
                        'sample_bad' =>
                            'يجب تعديل جسم الدالة يدويًا في كل استخدام.',
                        'feedback_pass' =>
                            'وضحت أن decorator يضيف سلوكًا حول الدالة '
                            . 'دون تغيير منطقها الأساسي.',
                        'feedback_fail' =>
                            'لم توضّح كيف يضيف decorator سلوكًا '
                            . 'دون تعديل منطق الدالة.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_DEC_USE_CASE',
                        'name_ar' => 'استخدام صحيح للـ decorator',
                        'description_ar' =>
                            'يذكر استخدامًا صحيحًا للـ decorator.',
                        'max_score' => 1.00,
                        'requires' => ['py_dec_valid_use_case'],
                        'blocked_by' => [],
                        'sample_good' =>
                            'يمكن استخدامه لإضافة logging إلى الدوال.',
                        'sample_bad' =>
                            'يستخدم فقط لإنشاء tables في قاعدة البيانات.',
                        'feedback_pass' =>
                            'قدمت استخدامًا صحيحًا للـ decorator.',
                        'feedback_fail' =>
                            'اذكر استخدامًا صحيحًا مثل logging أو caching.',
                        'order' => 3,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'PY_DEC_CONFLICT_INHERITANCE',
                        'trigger_concept' => 'py_dec_is_inheritance_claim',
                        'feedback_ar' =>
                            'يوجد خلط بين decorator وinheritance؛ '
                            . 'الـ decorator آلية لإضافة سلوك حول الدوال.',
                        'blocked_rubrics' => [
                            'PY_DEC_DEFINE',
                            'PY_DEC_ADD_BEHAVIOR',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'ما الفرق الأساسي بين generator وlist في Python؟ '
                    . 'ولماذا قد تفضّل generator عند معالجة عدد كبير من العناصر؟',
                'topic' => 'generators',
                'rule_set_code' => 'PY_GENERATOR_VS_LIST_V1',

                'concepts' => [
                    [
                        'code' => 'py_gen_lazy_yields_items',
                        'name_ar' => 'الـ generator ينتج العناصر تدريجيًا',
                        'name_en' => 'Generator yields items lazily',
                        'description_ar' =>
                            'يفهم أن generator لا ينشئ العناصر كلها دفعة واحدة.',
                        'claim_ar' =>
                            'الطالب يذكر أن generator ينتج أو يولّد العناصر '
                            . 'تدريجيًا عند الحاجة بدل إنشائها كلها دفعة واحدة.',
                        'claim_en' =>
                            'The student states that a generator yields '
                            . 'items lazily or on demand rather than '
                            . 'creating them all at once.',
                    ],
                    [
                        'code' => 'py_gen_list_materializes_all_items',
                        'name_ar' => 'الـ list تحمل العناصر كلها',
                        'name_en' => 'List materializes all items',
                        'description_ar' =>
                            'يفهم أن list تحتفظ بالعناصر الموجودة فيها '
                            . 'دفعة واحدة في الذاكرة.',
                        'claim_ar' =>
                            'الطالب يذكر أن list تحتوي أو تنشئ عناصرها '
                            . 'كلها دفعة واحدة في الذاكرة.',
                        'claim_en' =>
                            'The student states that a list contains or '
                            . 'materializes all of its items in memory.',
                    ],
                    [
                        'code' => 'py_gen_memory_efficient_large_data',
                        'name_ar' =>
                            'الـ generator يوفر ذاكرة مع البيانات الكبيرة',
                        'name_en' =>
                            'Generator is memory efficient for large data',
                        'description_ar' =>
                            'يربط استخدام generator بتقليل الذاكرة '
                            . 'عند معالجة عناصر كثيرة.',
                        'claim_ar' =>
                            'الطالب يوضح أن generator قد يكون أفضل عند '
                            . 'معالجة عدد كبير من العناصر لأنه لا يحتفظ '
                            . 'بها كلها في الذاكرة.',
                        'claim_en' =>
                            'The student explains that a generator can be '
                            . 'better for many items because it does not '
                            . 'keep all of them in memory.',
                    ],
                    [
                        'code' => 'py_gen_eager_load_claim',
                        'name_ar' =>
                            'ادعاء أن generator يحمل كل العناصر مسبقًا',
                        'name_en' =>
                            'Generator eagerly loads all items claim',
                        'description_ar' =>
                            'تناقض مع فكرة الإنتاج التدريجي.',
                        'claim_ar' =>
                            'الطالب يذكر أن generator ينشئ أو يحمل كل '
                            . 'العناصر في الذاكرة قبل البدء.',
                        'claim_en' =>
                            'The student claims that a generator loads or '
                            . 'creates all items in memory before use.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'PY_GEN_LAZY',
                        'name_ar' =>
                            'يوضح أن generator ينتج العناصر تدريجيًا',
                        'description_ar' =>
                            'يفرق بين الإنتاج التدريجي والإنشاء الكامل.',
                        'max_score' => 2.00,
                        'requires' => ['py_gen_lazy_yields_items'],
                        'blocked_by' => ['py_gen_eager_load_claim'],
                        'sample_good' =>
                            'الـ generator يعطي العنصر عند الحاجة.',
                        'sample_bad' =>
                            'الـ generator يحمل كل العناصر قبل التشغيل.',
                        'feedback_pass' =>
                            'وضحت أن generator ينتج العناصر تدريجيًا.',
                        'feedback_fail' =>
                            'لم توضّح أن generator ينتج العناصر عند الحاجة.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_GEN_LIST_MEMORY',
                        'name_ar' =>
                            'يوضح سلوك list في الذاكرة',
                        'description_ar' =>
                            'يوضح أن list تحتفظ بالعناصر كاملة.',
                        'max_score' => 1.00,
                        'requires' => [
                            'py_gen_list_materializes_all_items',
                        ],
                        'blocked_by' => [],
                        'sample_good' =>
                            'الـ list تحتفظ بعناصرها كلها في الذاكرة.',
                        'sample_bad' =>
                            'الـ list دائمًا تنتج العناصر عند الحاجة فقط.',
                        'feedback_pass' =>
                            'وضحت أن list تحتفظ بالعناصر كاملة.',
                        'feedback_fail' =>
                            'لم توضّح الفرق بين list وgenerator من ناحية '
                            . 'إنشاء العناصر.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_GEN_LARGE_DATA',
                        'name_ar' =>
                            'اختيار generator للبيانات الكبيرة',
                        'description_ar' =>
                            'يربط generator بتقليل استخدام الذاكرة '
                            . 'عند معالجة عناصر كثيرة.',
                        'max_score' => 2.00,
                        'requires' => [
                            'py_gen_memory_efficient_large_data',
                        ],
                        'blocked_by' => ['py_gen_eager_load_claim'],
                        'sample_good' =>
                            'يفضل عند معالجة ملف كبير لأنه لا يحمل كل '
                            . 'السطور دفعة واحدة.',
                        'sample_bad' =>
                            'يفضل لأنه يخزن كل العناصر في الذاكرة.',
                        'feedback_pass' =>
                            'ربطت generator بشكل صحيح بتقليل استخدام '
                            . 'الذاكرة مع البيانات الكبيرة.',
                        'feedback_fail' =>
                            'لم توضّح لماذا يفيد generator مع عدد كبير '
                            . 'من العناصر.',
                        'order' => 3,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'PY_GEN_CONFLICT_EAGER',
                        'trigger_concept' => 'py_gen_eager_load_claim',
                        'feedback_ar' =>
                            'الـ generator لا يحمل العناصر كلها مسبقًا؛ '
                            . 'بل ينتجها تدريجيًا عند الحاجة.',
                        'blocked_rubrics' => [
                            'PY_GEN_LAZY',
                            'PY_GEN_LARGE_DATA',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'لديك قائمة كبيرة من المعرّفات وتحتاج للتحقق المتكرر '
                    . 'من وجود معرّف فيها. لماذا يكون set غالبًا أفضل من '
                    . 'list لهذا الغرض؟',
                'topic' => 'collections_performance',
                'rule_set_code' => 'PY_SET_MEMBERSHIP_V1',

                'concepts' => [
                    [
                        'code' => 'py_set_average_fast_membership',
                        'name_ar' =>
                            'الـ set توفر membership سريعًا غالبًا',
                        'name_en' =>
                            'Set provides fast average membership',
                        'description_ar' =>
                            'يربط set بفحص وجود عنصر بزمن ثابت تقريبيًا.',
                        'claim_ar' =>
                            'الطالب يذكر أن التحقق من وجود عنصر في set '
                            . 'يكون غالبًا سريعًا أو قريبًا من O(1).',
                        'claim_en' =>
                            'The student states that membership checking '
                            . 'in a set is usually fast or approximately O(1).',
                    ],
                    [
                        'code' => 'py_list_linear_membership',
                        'name_ar' =>
                            'الـ list تبحث تسلسليًا عند فحص وجود عنصر',
                        'name_en' =>
                            'List membership uses linear search',
                        'description_ar' =>
                            'يفهم أن list قد تحتاج المرور على عناصر عديدة.',
                        'claim_ar' =>
                            'الطالب يذكر أن فحص وجود عنصر في list قد يحتاج '
                            . 'بحثًا تسلسليًا أو مرورًا على العناصر وقد يكون O(n).',
                        'claim_en' =>
                            'The student states that list membership may '
                            . 'require a linear scan through items and can be O(n).',
                    ],
                    [
                        'code' => 'py_set_suitable_repeated_lookup',
                        'name_ar' =>
                            'الـ set مناسبة لفحص العضوية المتكرر',
                        'name_en' =>
                            'Set is suitable for repeated membership checks',
                        'description_ar' =>
                            'يربط set بفحص وجود عناصر كثيرة ومتكررًا.',
                        'claim_ar' =>
                            'الطالب يوضح أن set مناسبة عندما نحتاج فحصًا '
                            . 'متكررًا لوجود عناصر أو عندما لا نحتاج تكرار '
                            . 'العناصر أو ترتيبها.',
                        'claim_en' =>
                            'The student explains that a set is suitable '
                            . 'for repeated membership checks or when '
                            . 'duplicates and order are not needed.',
                    ],
                    [
                        'code' => 'py_set_linear_membership_claim',
                        'name_ar' =>
                            'ادعاء أن set تبحث تسلسليًا دائمًا',
                        'name_en' =>
                            'Set membership is linear claim',
                        'description_ar' =>
                            'تناقض مع سبب استخدام set لفحص العضوية.',
                        'claim_ar' =>
                            'الطالب يذكر أن set تحتاج دائمًا إلى المرور '
                            . 'على كل العناصر للبحث عن عنصر.',
                        'claim_en' =>
                            'The student claims that a set always scans '
                            . 'all items sequentially for membership.',
                    ],
                    [
                        'code' => 'py_list_direct_lookup_claim',
                        'name_ar' =>
                            'ادعاء أن list توفر lookup مباشرًا للعضوية',
                        'name_en' =>
                            'List membership is direct lookup claim',
                        'description_ar' =>
                            'تناقض مع البحث التسلسلي في list.',
                        'claim_ar' =>
                            'الطالب يذكر أن list تتحقق من وجود عنصر '
                            . 'بـ lookup مباشر دون المرور على العناصر.',
                        'claim_en' =>
                            'The student claims that list membership uses '
                            . 'direct lookup without scanning items.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'PY_SET_FAST_MEMBERSHIP',
                        'name_ar' =>
                            'سرعة فحص العضوية في set',
                        'description_ar' =>
                            'يوضح أن set غالبًا أسرع لفحص وجود العناصر.',
                        'max_score' => 2.00,
                        'requires' => [
                            'py_set_average_fast_membership',
                        ],
                        'blocked_by' => [
                            'py_set_linear_membership_claim',
                        ],
                        'sample_good' =>
                            'فحص x in ids_set يكون غالبًا سريعًا.',
                        'sample_bad' =>
                            'set تمر على كل العناصر مثل list.',
                        'feedback_pass' =>
                            'وضحت سبب سرعة set في فحص العضوية.',
                        'feedback_fail' =>
                            'لم توضّح لماذا يكون فحص العضوية في set سريعًا.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_SET_LIST_LINEAR',
                        'name_ar' =>
                            'البحث التسلسلي في list',
                        'description_ar' =>
                            'يوضح أن list قد تحتاج المرور على عناصر عديدة.',
                        'max_score' => 2.00,
                        'requires' => [
                            'py_list_linear_membership',
                        ],
                        'blocked_by' => [
                            'py_list_direct_lookup_claim',
                        ],
                        'sample_good' =>
                            'في list قد يتم فحص العناصر واحدًا واحدًا.',
                        'sample_bad' =>
                            'list تستخدم lookup مباشرًا للعضوية.',
                        'feedback_pass' =>
                            'وضحت أن list قد تحتاج بحثًا تسلسليًا.',
                        'feedback_fail' =>
                            'لم توضّح سبب بطء list نسبيًا مع البحث المتكرر.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_SET_REPEATED_LOOKUP',
                        'name_ar' =>
                            'اختيار set لفحص العضوية المتكرر',
                        'description_ar' =>
                            'يربط set بحالة فحص المعرّفات المتكرر.',
                        'max_score' => 1.00,
                        'requires' => [
                            'py_set_suitable_repeated_lookup',
                        ],
                        'blocked_by' => [
                            'py_set_linear_membership_claim',
                        ],
                        'sample_good' =>
                            'أحوّل المعرّفات إلى set عندما أحتاج '
                            . 'فحص وجودها كثيرًا.',
                        'sample_bad' =>
                            'استخدم set لأنها تكرر العناصر أكثر.',
                        'feedback_pass' =>
                            'ربطت اختيار set بفحص العضوية المتكرر.',
                        'feedback_fail' =>
                            'لم توضّح متى يكون اختيار set مناسبًا.',
                        'order' => 3,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'PY_SET_CONFLICT_LINEAR',
                        'trigger_concept' =>
                            'py_set_linear_membership_claim',
                        'feedback_ar' =>
                            'الـ set تستخدم بنية hash غالبًا، لذلك لا '
                            . 'تحتاج عادةً إلى المرور التسلسلي على كل العناصر.',
                        'blocked_rubrics' => [
                            'PY_SET_FAST_MEMBERSHIP',
                            'PY_SET_REPEATED_LOOKUP',
                        ],
                    ],
                    [
                        'code' => 'PY_SET_CONFLICT_LIST_DIRECT',
                        'trigger_concept' =>
                            'py_list_direct_lookup_claim',
                        'feedback_ar' =>
                            'فحص وجود عنصر في list قد يتطلب المرور على '
                            . 'العناصر، وليس lookup مباشرًا للعضوية.',
                        'blocked_rubrics' => [
                            'PY_SET_LIST_LINEAR',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'لديك برنامج يجلب بيانات من عدة URLs بطيئة. '
                    . 'متى يكون asyncio مناسبًا، وما نوع المشكلة '
                    . 'التي يساعد على تحسينها؟',
                'topic' => 'asyncio',
                'rule_set_code' => 'PY_ASYNCIO_IO_V1',

                'concepts' => [
                    [
                        'code' => 'py_asyncio_io_bound_tasks',
                        'name_ar' =>
                            'asyncio مناسب لمهام I/O-bound',
                        'name_en' =>
                            'Asyncio is suitable for I/O-bound tasks',
                        'description_ar' =>
                            'يربط asyncio بالانتظار على الشبكة أو الملفات.',
                        'claim_ar' =>
                            'الطالب يذكر أن asyncio مناسب لمهام تنتظر '
                            . 'الشبكة أو URLs أو الملفات أو I/O.',
                        'claim_en' =>
                            'The student states that asyncio is suitable '
                            . 'for tasks waiting on network, URLs, files, '
                            . 'or I/O.',
                    ],
                    [
                        'code' => 'py_asyncio_concurrent_waiting',
                        'name_ar' =>
                            'asyncio يدير الانتظار المتزامن',
                        'name_en' =>
                            'Asyncio handles concurrent waiting',
                        'description_ar' =>
                            'يوضح أنه يسمح بمتابعة أعمال أخرى أثناء انتظار I/O.',
                        'claim_ar' =>
                            'الطالب يوضح أن asyncio يساعد على إدارة '
                            . 'عدة عمليات انتظار I/O معًا بدل انتظار كل '
                            . 'طلب بشكل متسلسل.',
                        'claim_en' =>
                            'The student explains that asyncio handles '
                            . 'multiple I/O waits together instead of '
                            . 'waiting for each request sequentially.',
                    ],
                    [
                        'code' => 'py_asyncio_multiple_urls_example',
                        'name_ar' =>
                            'مثال عدة URLs بطيئة',
                        'name_en' =>
                            'Multiple slow URLs example',
                        'description_ar' =>
                            'يربط asyncio بمثال جلب عدة URLs أو requests.',
                        'claim_ar' =>
                            'الطالب يذكر مثالًا صحيحًا مثل جلب بيانات '
                            . 'من عدة URLs أو requests بطيئة.',
                        'claim_en' =>
                            'The student gives a valid example such as '
                            . 'fetching data from multiple slow URLs or requests.',
                    ],
                    [
                        'code' => 'py_asyncio_cpu_parallelism_claim',
                        'name_ar' =>
                            'ادعاء أن asyncio يسرع CPU-bound تلقائيًا',
                        'name_en' =>
                            'Asyncio automatically parallelizes CPU work claim',
                        'description_ar' =>
                            'تناقض مع الاستخدام الأساسي لـ asyncio.',
                        'claim_ar' =>
                            'الطالب يذكر أن asyncio مناسب أساسًا لتسريع '
                            . 'حسابات CPU-heavy أو يجعلها تعمل بتوازي '
                            . 'حقيقي تلقائيًا.',
                        'claim_en' =>
                            'The student claims that asyncio is mainly for '
                            . 'speeding up CPU-heavy calculations or '
                            . 'automatically gives true CPU parallelism.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'PY_ASYNC_IO_BOUND',
                        'name_ar' =>
                            'تحديد مهام I/O-bound المناسبة',
                        'description_ar' =>
                            'يربط asyncio بالانتظار على الشبكة أو I/O.',
                        'max_score' => 2.00,
                        'requires' => [
                            'py_asyncio_io_bound_tasks',
                        ],
                        'blocked_by' => [
                            'py_asyncio_cpu_parallelism_claim',
                        ],
                        'sample_good' =>
                            'يكون مناسبًا عند انتظار responses من الشبكة.',
                        'sample_bad' =>
                            'يستخدم أساسًا لتسريع حسابات CPU-heavy.',
                        'feedback_pass' =>
                            'حددت بشكل صحيح أن asyncio مناسب لمهام I/O-bound.',
                        'feedback_fail' =>
                            'لم توضّح أن asyncio يناسب الانتظار على '
                            . 'الشبكة أو عمليات I/O.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_ASYNC_CONCURRENT_WAIT',
                        'name_ar' =>
                            'إدارة الانتظار المتزامن',
                        'description_ar' =>
                            'يوضح إدارة عدة عمليات انتظار بدل التنفيذ المتسلسل.',
                        'max_score' => 2.00,
                        'requires' => [
                            'py_asyncio_concurrent_waiting',
                        ],
                        'blocked_by' => [
                            'py_asyncio_cpu_parallelism_claim',
                        ],
                        'sample_good' =>
                            'أثناء انتظار URL يمكن متابعة طلب URL آخر.',
                        'sample_bad' =>
                            'ينفذ كل طلب بعد اكتمال السابق فقط.',
                        'feedback_pass' =>
                            'وضحت كيف يساعد asyncio على إدارة الانتظار '
                            . 'المتزامن.',
                        'feedback_fail' =>
                            'لم توضّح كيف يقلل asyncio وقت الانتظار '
                            . 'المتسلسل.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_ASYNC_URL_EXAMPLE',
                        'name_ar' =>
                            'تطبيق على عدة URLs',
                        'description_ar' =>
                            'يذكر مثالًا مناسبًا لجلب عدة URLs.',
                        'max_score' => 1.00,
                        'requires' => [
                            'py_asyncio_multiple_urls_example',
                        ],
                        'blocked_by' => [],
                        'sample_good' =>
                            'جلب بيانات من عدة APIs بطيئة في الوقت نفسه.',
                        'sample_bad' =>
                            'فرز قائمة أرقام كبيرة على CPU.',
                        'feedback_pass' =>
                            'قدمت مثالًا مناسبًا لاستخدام asyncio.',
                        'feedback_fail' =>
                            'اذكر مثالًا مثل جلب بيانات من عدة URLs بطيئة.',
                        'order' => 3,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'PY_ASYNC_CONFLICT_CPU',
                        'trigger_concept' =>
                            'py_asyncio_cpu_parallelism_claim',
                        'feedback_ar' =>
                            'asyncio يناسب غالبًا مهام I/O-bound والانتظار '
                            . 'على الشبكة، وليس تسريع حسابات CPU-heavy '
                            . 'بتوازي حقيقي تلقائيًا.',
                        'blocked_rubrics' => [
                            'PY_ASYNC_IO_BOUND',
                            'PY_ASYNC_CONCURRENT_WAIT',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'عند تجهيز مكتبة Python لتستخدمها مشاريع أخرى، '
                    . 'اذكر ثلاثة عناصر أساسية يجب تحديدها في إعداد الحزمة.',
                'topic' => 'packaging',
                'rule_set_code' => 'PY_PACKAGE_METADATA_V1',

                'concepts' => [
                    [
                        'code' => 'py_pkg_declares_name',
                        'name_ar' => 'تحديد اسم الحزمة',
                        'name_en' => 'Package declares a name',
                        'description_ar' =>
                            'يذكر اسم الحزمة كعنصر إعداد أساسي.',
                        'claim_ar' =>
                            'الطالب يذكر أن اسم الحزمة أو package name '
                            . 'يجب تحديده في إعداد الحزمة.',
                        'claim_en' =>
                            'The student states that the package name '
                            . 'should be specified in package configuration.',
                    ],
                    [
                        'code' => 'py_pkg_declares_version',
                        'name_ar' => 'تحديد إصدار الحزمة',
                        'name_en' => 'Package declares a version',
                        'description_ar' =>
                            'يذكر version كعنصر إعداد أساسي.',
                        'claim_ar' =>
                            'الطالب يذكر أن إصدار الحزمة أو version '
                            . 'يجب تحديده في إعداد الحزمة.',
                        'claim_en' =>
                            'The student states that the package version '
                            . 'should be specified in package configuration.',
                    ],
                    [
                        'code' => 'py_pkg_declares_dependencies',
                        'name_ar' => 'تحديد تبعيات الحزمة',
                        'name_en' => 'Package declares dependencies',
                        'description_ar' =>
                            'يذكر dependencies كعنصر إعداد أساسي.',
                        'claim_ar' =>
                            'الطالب يذكر أن تبعيات الحزمة أو dependencies '
                            . 'يجب تحديدها في إعداد الحزمة.',
                        'claim_en' =>
                            'The student states that package dependencies '
                            . 'should be declared in package configuration.',
                    ],
                    [
                        'code' => 'py_pkg_manual_dependencies_claim',
                        'name_ar' =>
                            'ادعاء أن التبعيات لا تُعلن في الحزمة',
                        'name_en' =>
                            'Dependencies should not be declared claim',
                        'description_ar' =>
                            'تناقض مع إعداد حزمة قابلة لإعادة الاستخدام.',
                        'claim_ar' =>
                            'الطالب يذكر أن التبعيات لا يجب إعلانها في '
                            . 'إعداد الحزمة وأن كل مستخدم يثبتها يدويًا فقط.',
                        'claim_en' =>
                            'The student claims that dependencies should not '
                            . 'be declared in package configuration and '
                            . 'every user should install them manually only.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'PY_PKG_NAME',
                        'name_ar' => 'اسم الحزمة',
                        'description_ar' =>
                            'يذكر اسم الحزمة كعنصر أساسي.',
                        'max_score' => 1.00,
                        'requires' => ['py_pkg_declares_name'],
                        'blocked_by' => [],
                        'sample_good' =>
                            'يجب تحديد اسم الحزمة.',
                        'sample_bad' =>
                            'لا حاجة لاسم للحزمة.',
                        'feedback_pass' =>
                            'ذكرت اسم الحزمة كعنصر إعداد أساسي.',
                        'feedback_fail' =>
                            'لم تذكر اسم الحزمة ضمن إعدادات الحزمة.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_PKG_VERSION',
                        'name_ar' => 'إصدار الحزمة',
                        'description_ar' =>
                            'يذكر إصدار الحزمة كعنصر أساسي.',
                        'max_score' => 1.00,
                        'requires' => ['py_pkg_declares_version'],
                        'blocked_by' => [],
                        'sample_good' =>
                            'يجب تحديد version مثل 1.0.0.',
                        'sample_bad' =>
                            'الحزمة لا تحتاج إصدارًا.',
                        'feedback_pass' =>
                            'ذكرت إصدار الحزمة كعنصر إعداد أساسي.',
                        'feedback_fail' =>
                            'لم تذكر إصدار الحزمة ضمن إعدادات الحزمة.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_PKG_DEPENDENCIES',
                        'name_ar' => 'تبعيات الحزمة',
                        'description_ar' =>
                            'يذكر dependencies كعنصر أساسي.',
                        'max_score' => 1.00,
                        'requires' => [
                            'py_pkg_declares_dependencies',
                        ],
                        'blocked_by' => [
                            'py_pkg_manual_dependencies_claim',
                        ],
                        'sample_good' =>
                            'يجب إعلان dependencies المطلوبة.',
                        'sample_bad' =>
                            'لا تعلن التبعيات واتركها للمستخدم يدويًا.',
                        'feedback_pass' =>
                            'ذكرت تبعيات الحزمة كعنصر إعداد أساسي.',
                        'feedback_fail' =>
                            'لم تذكر dependencies ضمن إعدادات الحزمة.',
                        'order' => 3,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'PY_PKG_CONFLICT_DEPENDENCIES',
                        'trigger_concept' =>
                            'py_pkg_manual_dependencies_claim',
                        'feedback_ar' =>
                            'يجب إعلان التبعيات المطلوبة في إعداد الحزمة '
                            . 'حتى يتمكن المستخدم من تثبيتها بطريقة صحيحة.',
                        'blocked_rubrics' => [
                            'PY_PKG_DEPENDENCIES',
                        ],
                    ],
                ],
            ],

            [
                'question_text' =>
                    'ما فائدة API versioning مثل /api/v1/users عند تغيير '
                    . 'عقد الـAPI؟ اذكر فائدتين.',
                'topic' => 'api_versioning',
                'rule_set_code' => 'PY_API_VERSIONING_V1',

                'concepts' => [
                    [
                        'code' => 'py_api_version_identifies_contract',
                        'name_ar' =>
                            'API versioning يميز عقد الواجهة',
                        'name_en' =>
                            'API versioning identifies the contract',
                        'description_ar' =>
                            'يوضح أن الرقم يميز نسخة الواجهة أو العقد.',
                        'claim_ar' =>
                            'الطالب يذكر أن API versioning يميز نسخة '
                            . 'واجهة الـAPI أو عقدها عند حدوث تغييرات.',
                        'claim_en' =>
                            'The student states that API versioning '
                            . 'identifies a version of the API interface '
                            . 'or contract when changes occur.',
                    ],
                    [
                        'code' => 'py_api_version_backward_compatibility',
                        'name_ar' =>
                            'API versioning يحافظ على العملاء القدامى',
                        'name_en' =>
                            'API versioning preserves backward compatibility',
                        'description_ar' =>
                            'يوضح أنه يمنع كسر التطبيقات التي تستخدم النسخة القديمة.',
                        'claim_ar' =>
                            'الطالب يذكر أن API versioning يساعد على '
                            . 'عدم كسر العملاء أو التطبيقات القديمة عند '
                            . 'تغيير الـAPI.',
                        'claim_en' =>
                            'The student states that API versioning helps '
                            . 'avoid breaking old clients or applications '
                            . 'when the API changes.',
                    ],
                    [
                        'code' => 'py_api_versioned_path_example',
                        'name_ar' =>
                            'مثال لمسار API م versioned',
                        'name_en' =>
                            'Versioned API path example',
                        'description_ar' =>
                            'يذكر مثالًا صحيحًا مثل /api/v1/users أو '
                            . 'تشغيل نسختين أثناء انتقال العملاء.',
                        'claim_ar' =>
                            'الطالب يذكر مثالًا لمسار versioned مثل '
                            . '/api/v1/users أو يذكر دعم v1 وv2 أثناء '
                            . 'انتقال العملاء.',
                        'claim_en' =>
                            'The student gives a versioned path example '
                            . 'such as /api/v1/users or mentions supporting '
                            . 'v1 and v2 during client migration.',
                    ],
                    [
                        'code' => 'py_api_version_db_only_claim',
                        'name_ar' =>
                            'ادعاء أن API versioning هو نسخة قاعدة البيانات',
                        'name_en' =>
                            'API versioning is database version only claim',
                        'description_ar' =>
                            'خلط بين عقد الـAPI وإصدار قاعدة البيانات.',
                        'claim_ar' =>
                            'الطالب يذكر أن API versioning يعني فقط تغيير '
                            . 'أو نسخ إصدار قاعدة البيانات.',
                        'claim_en' =>
                            'The student claims that API versioning means '
                            . 'only changing or copying the database version.',
                    ],
                    [
                        'code' => 'py_api_version_break_old_clients_claim',
                        'name_ar' =>
                            'ادعاء أن versioning يكسر العملاء القدامى',
                        'name_en' =>
                            'Versioning breaks old clients claim',
                        'description_ar' =>
                            'تناقض مع هدف backward compatibility.',
                        'claim_ar' =>
                            'الطالب يذكر أن API versioning هدفه إزالة '
                            . 'النسخة القديمة فورًا وكسر العملاء القدامى.',
                        'claim_en' =>
                            'The student claims that API versioning is meant '
                            . 'to immediately remove the old version and '
                            . 'break old clients.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'PY_API_VERSION_CONTRACT',
                        'name_ar' =>
                            'تمييز نسخة عقد الـAPI',
                        'description_ar' =>
                            'يوضح أن versioning يميز نسخة الواجهة أو العقد.',
                        'max_score' => 2.00,
                        'requires' => [
                            'py_api_version_identifies_contract',
                        ],
                        'blocked_by' => [
                            'py_api_version_db_only_claim',
                        ],
                        'sample_good' =>
                            'v1 وv2 يمثلان نسخًا مختلفة من عقد الـAPI.',
                        'sample_bad' =>
                            'versioning يعني فقط تغيير قاعدة البيانات.',
                        'feedback_pass' =>
                            'وضحت أن versioning يميز نسخة واجهة الـAPI '
                            . 'أو عقدها.',
                        'feedback_fail' =>
                            'لم توضّح أن API versioning يميز نسخة '
                            . 'واجهة الـAPI أو عقدها.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'PY_API_VERSION_COMPAT',
                        'name_ar' =>
                            'الحفاظ على توافق العملاء القدامى',
                        'description_ar' =>
                            'يوضح أن versioning يمنع كسر العملاء الحاليين.',
                        'max_score' => 2.00,
                        'requires' => [
                            'py_api_version_backward_compatibility',
                        ],
                        'blocked_by' => [
                            'py_api_version_db_only_claim',
                            'py_api_version_break_old_clients_claim',
                        ],
                        'sample_good' =>
                            'تبقى التطبيقات القديمة على v1 حتى تنتقل إلى v2.',
                        'sample_bad' =>
                            'نحذف v1 مباشرة لكي تتعطل التطبيقات القديمة.',
                        'feedback_pass' =>
                            'وضحت أن versioning يساعد على عدم كسر '
                            . 'العملاء القدامى.',
                        'feedback_fail' =>
                            'لم توضّح كيف يحافظ versioning على توافق '
                            . 'العملاء القدامى.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'PY_API_VERSION_PATH',
                        'name_ar' =>
                            'مثال لمسار API م versioned',
                        'description_ar' =>
                            'يذكر مثالًا لمسار أو خطة انتقال بين النسخ.',
                        'max_score' => 1.00,
                        'requires' => [
                            'py_api_versioned_path_example',
                        ],
                        'blocked_by' => [],
                        'sample_good' =>
                            'مثل /api/v1/users ثم /api/v2/users.',
                        'sample_bad' =>
                            'نغير جدول users فقط.',
                        'feedback_pass' =>
                            'قدمت مثالًا صحيحًا لنسخة API.',
                        'feedback_fail' =>
                            'اذكر مثالًا مثل /api/v1/users أو دعم v1 وv2.',
                        'order' => 3,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'PY_API_CONFLICT_DB_ONLY',
                        'trigger_concept' =>
                            'py_api_version_db_only_claim',
                        'feedback_ar' =>
                            'API versioning يتعلق بعقد الواجهة والعملاء، '
                            . 'وليس مجرد إصدار قاعدة البيانات.',
                        'blocked_rubrics' => [
                            'PY_API_VERSION_CONTRACT',
                            'PY_API_VERSION_COMPAT',
                        ],
                    ],
                    [
                        'code' => 'PY_API_CONFLICT_BREAK_CLIENTS',
                        'trigger_concept' =>
                            'py_api_version_break_old_clients_claim',
                        'feedback_ar' =>
                            'من أهداف API versioning السماح للعملاء '
                            . 'القدامى بالاستمرار مؤقتًا بدل كسرهم فورًا.',
                        'blocked_rubrics' => [
                            'PY_API_VERSION_COMPAT',
                        ],
                    ],
                ],
            ],
        ];
    }
}
