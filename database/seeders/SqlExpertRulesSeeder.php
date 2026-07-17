<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class SqlExpertRulesSeeder extends Seeder
{
    use ResolvesExpertQuestionsByTopic;

    private const SKILL_NAME = 'SQL';

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
                'SQL Expert Rules data was seeded successfully.'
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
                'Cannot seed SQL questions because one or more '
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
                'question_text' => 'اكتب استعلام SQL يعرض جميع البيانات من جدول students.',
                'topic' => 'sql_select_all',
                'rule_set_code' => 'SQL_SELECT_ALL_V1',

                'concepts' => [
                    [
                        'code' => 'sql_select_all_select_keyword',
                        'name_ar' => 'استخدام SELECT',
                        'name_en' => 'Uses SELECT',
                        'description_ar' => 'يذكر استخدام SELECT لاسترجاع البيانات.',
                        'claim_ar' => 'يستخدم الطالب SELECT في استعلام لاسترجاع البيانات من جدول.',
                        'claim_en' => 'The student uses SELECT in a query to retrieve data from a table.',
                    ],
                    [
                        'code' => 'sql_select_all_wildcard',
                        'name_ar' => 'استخدام النجمة لجميع الأعمدة',
                        'name_en' => 'Uses wildcard for all columns',
                        'description_ar' => 'يذكر استخدام * لطلب جميع أعمدة الجدول.',
                        'claim_ar' => 'يضع الطالب * بعد SELECT لطلب جميع الأعمدة.',
                        'claim_en' => 'The student places * after SELECT to request all columns.',
                    ],
                    [
                        'code' => 'sql_select_all_from_students',
                        'name_ar' => 'تحديد جدول students',
                        'name_en' => 'Selects students table',
                        'description_ar' => 'يحدد أن البيانات مصدرها جدول students.',
                        'claim_ar' => 'يكتب الطالب FROM students كمصدر للبيانات.',
                        'claim_en' => 'The student writes FROM students as the data source.',
                    ],
                    [
                        'code' => 'sql_select_all_valid_query',
                        'name_ar' => 'استعلام صحيح كامل',
                        'name_en' => 'Valid complete query',
                        'description_ar' => 'يقدم استعلامًا صحيحًا يختار كل البيانات من students.',
                        'claim_ar' => 'يكتب الطالب استعلامًا صحيحًا مثل SELECT * FROM students يجمع SELECT و* وFROM students.',
                        'claim_en' => 'The student writes a valid query such as SELECT * FROM students combining SELECT, *, and FROM students.',
                    ],
                    [
                        'code' => 'sql_select_all_insert_for_read_claim',
                        'name_ar' => 'ادعاء استخدام INSERT للعرض',
                        'name_en' => 'Claims INSERT is for reading',
                        'description_ar' => 'ادعاء خاطئ بأن INSERT هو الأمر المناسب لعرض البيانات.',
                        'claim_ar' => 'يدعي الطالب أن INSERT هو الاستعلام المناسب لعرض جميع بيانات students بدل SELECT.',
                        'claim_en' => 'The student claims INSERT is the proper query to display all students data instead of SELECT.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_SELECT_ALL_SELECT',
                        'name_ar' => 'استخدام SELECT',
                        'description_ar' => 'يستخدم SELECT لاسترجاع البيانات.',
                        'max_score' => 1.00,
                        'requires' => ['sql_select_all_select_keyword'],
                        'blocked_by' => ['sql_select_all_insert_for_read_claim'],
                        'sample_good' => 'SELECT * FROM students;',
                        'sample_bad' => 'INSERT INTO students ...',
                        'feedback_pass' => 'استخدمت SELECT لاسترجاع البيانات.',
                        'feedback_fail' => 'استخدم SELECT لاسترجاع البيانات بدل أوامر الإدخال.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_SELECT_ALL_WILDCARD',
                        'name_ar' => 'اختيار جميع الأعمدة',
                        'description_ar' => 'يستخدم * لاختيار جميع الأعمدة.',
                        'max_score' => 1.00,
                        'requires' => ['sql_select_all_wildcard'],
                        'blocked_by' => [],
                        'sample_good' => 'SELECT * FROM students;',
                        'sample_bad' => 'SELECT students FROM ...',
                        'feedback_pass' => 'وضحت اختيار جميع الأعمدة باستخدام *.',
                        'feedback_fail' => 'استخدم * بعد SELECT لعرض جميع الأعمدة.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_SELECT_ALL_SOURCE',
                        'name_ar' => 'تحديد جدول students',
                        'description_ar' => 'يحدد FROM students في الاستعلام.',
                        'max_score' => 1.00,
                        'requires' => ['sql_select_all_from_students'],
                        'blocked_by' => [],
                        'sample_good' => 'SELECT * FROM students;',
                        'sample_bad' => 'SELECT *;',
                        'feedback_pass' => 'حددت جدول students كمصدر للبيانات.',
                        'feedback_fail' => 'أضف FROM students لتحديد مصدر البيانات.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_SELECT_ALL_VALID_QUERY',
                        'name_ar' => 'صياغة استعلام صحيح كامل',
                        'description_ar' => 'يقدم استعلام SQL صحيحًا كاملًا.',
                        'max_score' => 2.00,
                        'requires' => ['sql_select_all_valid_query'],
                        'blocked_by' => ['sql_select_all_insert_for_read_claim'],
                        'sample_good' => 'SELECT * FROM students;',
                        'sample_bad' => 'SELECT FROM students',
                        'feedback_pass' => 'قدمت استعلامًا صحيحًا وكاملًا.',
                        'feedback_fail' => 'اكتب الاستعلام كاملًا بصيغة SELECT * FROM students.',
                        'order' => 4,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_SELECT_ALL_CONFLICT_INSERT_FOR_READ',
                        'trigger_concept' => 'sql_select_all_insert_for_read_claim',
                        'feedback_ar' => 'INSERT تستخدم لإضافة صفوف وليست لعرض البيانات؛ لعرض البيانات نستخدم SELECT.',
                        'blocked_rubrics' => ['SQL_SELECT_ALL_SELECT', 'SQL_SELECT_ALL_SOURCE', 'SQL_SELECT_ALL_VALID_QUERY'],
                    ],
                ],
            ],
            [
                'question_text' => 'ما وظيفة WHERE في SQL؟ أعط مثالًا بسيطًا.',
                'topic' => 'sql_where_filtering',
                'rule_set_code' => 'SQL_WHERE_FILTERING_V1',

                'concepts' => [
                    [
                        'code' => 'sql_where_filters_rows',
                        'name_ar' => 'تصفية الصفوف',
                        'name_en' => 'WHERE filters rows',
                        'description_ar' => 'يوضح أن WHERE تحدد الصفوف التي تحقق شرطًا.',
                        'claim_ar' => 'يذكر الطالب أن WHERE تستخدم لتصفية الصفوف أو اختيار الصفوف التي تحقق شرطًا.',
                        'claim_en' => 'The student states that WHERE filters rows or selects rows that satisfy a condition.',
                    ],
                    [
                        'code' => 'sql_where_uses_condition',
                        'name_ar' => 'استخدام شرط',
                        'name_en' => 'WHERE uses a condition',
                        'description_ar' => 'يوضح وجود شرط على عمود أو قيمة.',
                        'claim_ar' => 'يذكر الطالب شرطًا في WHERE مثل age >= 18 أو status = active.',
                        'claim_en' => 'The student mentions a WHERE condition such as age >= 18 or status = active.',
                    ],
                    [
                        'code' => 'sql_where_valid_example',
                        'name_ar' => 'مثال WHERE صحيح',
                        'name_en' => 'Valid WHERE example',
                        'description_ar' => 'يقدم مثالًا صحيحًا يجمع SELECT وWHERE وشرطًا.',
                        'claim_ar' => 'يقدم الطالب مثالًا صحيحًا مثل SELECT * FROM students WHERE age >= 18.',
                        'claim_en' => 'The student provides a valid example such as SELECT * FROM students WHERE age >= 18.',
                    ],
                    [
                        'code' => 'sql_where_returns_all_without_condition_claim',
                        'name_ar' => 'ادعاء أن WHERE لا تحتاج شرطًا',
                        'name_en' => 'Claims WHERE needs no condition',
                        'description_ar' => 'ادعاء خاطئ بأن WHERE تعرض كل الصفوف دون شرط.',
                        'claim_ar' => 'يدعي الطالب أن WHERE تعرض كل الصفوف دائمًا أو لا تحتاج إلى شرط.',
                        'claim_en' => 'The student claims WHERE always returns all rows or needs no condition.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_WHERE_FILTER_ROWS',
                        'name_ar' => 'وظيفة WHERE في التصفية',
                        'description_ar' => 'يوضح أن WHERE تصفي الصفوف وفق شرط.',
                        'max_score' => 2.00,
                        'requires' => ['sql_where_filters_rows'],
                        'blocked_by' => ['sql_where_returns_all_without_condition_claim'],
                        'sample_good' => 'WHERE filters rows by a condition.',
                        'sample_bad' => 'WHERE shows every row without a condition.',
                        'feedback_pass' => 'وضحت أن WHERE تستخدم لتصفية الصفوف.',
                        'feedback_fail' => 'وضح أن WHERE تختار الصفوف التي تحقق شرطًا.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_WHERE_CONDITION',
                        'name_ar' => 'تحديد شرط التصفية',
                        'description_ar' => 'يذكر شرطًا على عمود أو قيمة.',
                        'max_score' => 1.00,
                        'requires' => ['sql_where_uses_condition'],
                        'blocked_by' => ['sql_where_returns_all_without_condition_claim'],
                        'sample_good' => 'WHERE age >= 18',
                        'sample_bad' => 'WHERE without condition',
                        'feedback_pass' => 'حددت وجود شرط للتصفية.',
                        'feedback_fail' => 'أضف شرطًا مثل age >= 18 أو status = active.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_WHERE_VALID_EXAMPLE',
                        'name_ar' => 'مثال SQL صحيح',
                        'description_ar' => 'يقدم مثالًا صحيحًا لـWHERE.',
                        'max_score' => 2.00,
                        'requires' => ['sql_where_valid_example'],
                        'blocked_by' => ['sql_where_returns_all_without_condition_claim'],
                        'sample_good' => 'SELECT * FROM students WHERE age >= 18;',
                        'sample_bad' => 'SELECT WHERE age >= 18',
                        'feedback_pass' => 'قدمت مثالًا صحيحًا على WHERE.',
                        'feedback_fail' => 'أضف مثالًا كاملًا مثل SELECT * FROM students WHERE age >= 18.',
                        'order' => 3,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_WHERE_CONFLICT_NO_CONDITION',
                        'trigger_concept' => 'sql_where_returns_all_without_condition_claim',
                        'feedback_ar' => 'WHERE لا تعمل دون شرط للتصفية؛ لإرجاع كل الصفوف يمكن عدم كتابة WHERE أصلًا.',
                        'blocked_rubrics' => ['SQL_WHERE_FILTER_ROWS', 'SQL_WHERE_CONDITION', 'SQL_WHERE_VALID_EXAMPLE'],
                    ],
                ],
            ],
            [
                'question_text' => 'ما الفرق بين SELECT * و SELECT column_name؟',
                'topic' => 'sql_select_columns',
                'rule_set_code' => 'SQL_SELECT_COLUMNS_V1',

                'concepts' => [
                    [
                        'code' => 'sql_select_star_all_columns',
                        'name_ar' => 'SELECT * لجميع الأعمدة',
                        'name_en' => 'SELECT star returns all columns',
                        'description_ar' => 'يوضح أن SELECT * تسترجع كل أعمدة الجدول.',
                        'claim_ar' => 'يذكر الطالب أن SELECT * تعرض أو تسترجع جميع أعمدة الجدول.',
                        'claim_en' => 'The student states that SELECT * returns all columns of the table.',
                    ],
                    [
                        'code' => 'sql_select_named_specific_columns',
                        'name_ar' => 'SELECT للأعمدة المحددة',
                        'name_en' => 'SELECT named columns returns selected columns',
                        'description_ar' => 'يوضح أن SELECT column_name تختار عمودًا أو أعمدة محددة فقط.',
                        'claim_ar' => 'يذكر الطالب أن SELECT column_name تسترجع عمودًا محددًا أو أعمدة يختارها المطور.',
                        'claim_en' => 'The student states that SELECT column_name returns a specific column or developer-selected columns.',
                    ],
                    [
                        'code' => 'sql_select_specific_columns_efficiency',
                        'name_ar' => 'اختيار الأعمدة المطلوبة فقط',
                        'name_en' => 'Selecting needed columns is efficient',
                        'description_ar' => 'يوضح أن اختيار الأعمدة اللازمة أوضح وأقل نقلًا للبيانات.',
                        'claim_ar' => 'يذكر الطالب أن اختيار الأعمدة المطلوبة فقط أوضح أو يقلل البيانات المنقولة مقارنة بـSELECT *.',
                        'claim_en' => 'The student mentions that selecting only needed columns is clearer or reduces transferred data compared with SELECT *.',
                    ],
                    [
                        'code' => 'sql_select_star_one_column_claim',
                        'name_ar' => 'ادعاء معكوس حول SELECT *',
                        'name_en' => 'Reversed SELECT star claim',
                        'description_ar' => 'ادعاء خاطئ بأن SELECT * تعيد عمودًا واحدًا وأن الأعمدة المحددة تعيد الجميع.',
                        'claim_ar' => 'يدعي الطالب أن SELECT * تعيد عمودًا واحدًا فقط وأن SELECT column_name تعيد كل الأعمدة.',
                        'claim_en' => 'The student claims SELECT * returns only one column and SELECT column_name returns all columns.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_SELECT_STAR_ALL',
                        'name_ar' => 'معنى SELECT *',
                        'description_ar' => 'يوضح أن SELECT * تجلب كل الأعمدة.',
                        'max_score' => 2.00,
                        'requires' => ['sql_select_star_all_columns'],
                        'blocked_by' => ['sql_select_star_one_column_claim'],
                        'sample_good' => 'SELECT * FROM students;',
                        'sample_bad' => 'SELECT * returns one column.',
                        'feedback_pass' => 'وضحت أن SELECT * تسترجع جميع الأعمدة.',
                        'feedback_fail' => 'وضح أن SELECT * تعني جميع أعمدة الجدول.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_SELECT_NAMED_COLUMNS',
                        'name_ar' => 'معنى SELECT column_name',
                        'description_ar' => 'يوضح أن SELECT column_name تختار أعمدة محددة.',
                        'max_score' => 2.00,
                        'requires' => ['sql_select_named_specific_columns'],
                        'blocked_by' => ['sql_select_star_one_column_claim'],
                        'sample_good' => 'SELECT name, email FROM students;',
                        'sample_bad' => 'SELECT name returns all columns.',
                        'feedback_pass' => 'وضحت أن الأعمدة المسماة تحدد ما نريد إرجاعه.',
                        'feedback_fail' => 'وضح أن SELECT column_name تختار عمودًا أو أعمدة محددة.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_SELECT_COLUMN_CHOICE',
                        'name_ar' => 'فائدة اختيار الأعمدة المطلوبة',
                        'description_ar' => 'يذكر أثر اختيار الأعمدة المحددة على الوضوح أو حجم البيانات.',
                        'max_score' => 1.00,
                        'requires' => ['sql_select_specific_columns_efficiency'],
                        'blocked_by' => [],
                        'sample_good' => 'Select only columns needed by the screen.',
                        'sample_bad' => 'Always use SELECT * for every query.',
                        'feedback_pass' => 'وضحت فائدة اختيار الأعمدة المطلوبة فقط.',
                        'feedback_fail' => 'اذكر أن اختيار الأعمدة المطلوبة أوضح وقد يقلل نقل البيانات.',
                        'order' => 3,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_SELECT_COLUMNS_CONFLICT_REVERSED',
                        'trigger_concept' => 'sql_select_star_one_column_claim',
                        'feedback_ar' => 'SELECT * تعني جميع الأعمدة، بينما SELECT column_name تعني أعمدة محددة فقط.',
                        'blocked_rubrics' => ['SQL_SELECT_STAR_ALL', 'SQL_SELECT_NAMED_COLUMNS'],
                    ],
                ],
            ],
            [
                'question_text' => 'اشرح ما هو JOIN في SQL ولماذا نستخدمه.',
                'topic' => 'sql_joins',
                'rule_set_code' => 'SQL_JOINS_V1',

                'concepts' => [
                    [
                        'code' => 'sql_join_combines_related_tables',
                        'name_ar' => 'ربط الجداول المرتبطة',
                        'name_en' => 'JOIN combines related tables',
                        'description_ar' => 'يوضح أن JOIN تجمع بيانات من جدولين أو أكثر بينهما علاقة.',
                        'claim_ar' => 'يذكر الطالب أن JOIN تربط أو تجمع بيانات من جدولين أو أكثر مرتبطين.',
                        'claim_en' => 'The student states that JOIN combines data from two or more related tables.',
                    ],
                    [
                        'code' => 'sql_join_matches_related_keys',
                        'name_ar' => 'شرط الربط بالمفاتيح',
                        'name_en' => 'JOIN matches related keys',
                        'description_ar' => 'يوضح أن الربط يعتمد على شرط بين مفاتيح مرتبطة.',
                        'claim_ar' => 'يذكر الطالب شرط ربط مثل orders.user_id = users.id أو يشرح المطابقة بين المفتاح الأجنبي والرئيسي.',
                        'claim_en' => 'The student mentions a join condition such as orders.user_id = users.id or matching foreign and primary keys.',
                    ],
                    [
                        'code' => 'sql_join_retrieves_related_data',
                        'name_ar' => 'جلب بيانات مرتبطة',
                        'name_en' => 'JOIN retrieves related data',
                        'description_ar' => 'يوضح سبب استخدام JOIN في استرجاع معلومات مترابطة.',
                        'claim_ar' => 'يذكر الطالب أن JOIN تستخدم لجلب بيانات مرتبطة مثل الطلبات مع معلومات المستخدمين.',
                        'claim_en' => 'The student states that JOIN is used to retrieve related data such as orders with user information.',
                    ],
                    [
                        'code' => 'sql_join_valid_example',
                        'name_ar' => 'مثال JOIN صحيح',
                        'name_en' => 'Valid JOIN example',
                        'description_ar' => 'يقدم مثالًا صحيحًا لـJOIN مع شرط ON.',
                        'claim_ar' => 'يقدم الطالب مثالًا صحيحًا مثل SELECT users.name FROM users JOIN orders ON orders.user_id = users.id.',
                        'claim_en' => 'The student provides a valid JOIN example with an ON condition.',
                    ],
                    [
                        'code' => 'sql_join_without_relation_claim',
                        'name_ar' => 'ادعاء JOIN بلا علاقة',
                        'name_en' => 'Claims JOIN needs no relation',
                        'description_ar' => 'ادعاء خاطئ بأن JOIN تجمع جداول عشوائية دون شرط أو علاقة.',
                        'claim_ar' => 'يدعي الطالب أن JOIN تجمع جداول عشوائية دون شرط ON أو دون علاقة بين المفاتيح.',
                        'claim_en' => 'The student claims JOIN combines arbitrary tables without an ON condition or key relationship.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_JOIN_RELATED_TABLES',
                        'name_ar' => 'فكرة JOIN',
                        'description_ar' => 'يوضح أن JOIN تربط جداول مرتبطة.',
                        'max_score' => 2.00,
                        'requires' => ['sql_join_combines_related_tables'],
                        'blocked_by' => ['sql_join_without_relation_claim'],
                        'sample_good' => 'JOIN combines related tables.',
                        'sample_bad' => 'JOIN combines random tables without a relation.',
                        'feedback_pass' => 'وضحت أن JOIN تربط بيانات من جداول مرتبطة.',
                        'feedback_fail' => 'وضح أن JOIN تجمع بيانات من جداول بينها علاقة.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_JOIN_KEY_CONDITION',
                        'name_ar' => 'شرط ON والمفاتيح',
                        'description_ar' => 'يذكر شرط الربط بالمفاتيح.',
                        'max_score' => 1.00,
                        'requires' => ['sql_join_matches_related_keys'],
                        'blocked_by' => ['sql_join_without_relation_claim'],
                        'sample_good' => 'ON orders.user_id = users.id',
                        'sample_bad' => 'JOIN without ON or key condition',
                        'feedback_pass' => 'وضحت شرط الربط بين المفاتيح.',
                        'feedback_fail' => 'أضف شرط ON يطابق مفاتيح مرتبطة.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_JOIN_PURPOSE',
                        'name_ar' => 'سبب استخدام JOIN',
                        'description_ar' => 'يذكر جلب البيانات المرتبطة كسبب للاستخدام.',
                        'max_score' => 1.00,
                        'requires' => ['sql_join_retrieves_related_data'],
                        'blocked_by' => [],
                        'sample_good' => 'Retrieve orders with user names.',
                        'sample_bad' => 'Use JOIN only to rename a table.',
                        'feedback_pass' => 'وضحت فائدة JOIN في جلب البيانات المرتبطة.',
                        'feedback_fail' => 'اذكر مثالًا للبيانات المرتبطة التي نحتاج جمعها.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_JOIN_VALID_EXAMPLE',
                        'name_ar' => 'مثال JOIN صحيح',
                        'description_ar' => 'يقدم مثال SQL صحيحًا مع JOIN وON.',
                        'max_score' => 1.00,
                        'requires' => ['sql_join_valid_example'],
                        'blocked_by' => ['sql_join_without_relation_claim'],
                        'sample_good' => 'SELECT users.name FROM users JOIN orders ON orders.user_id = users.id;',
                        'sample_bad' => 'SELECT ... JOIN orders;',
                        'feedback_pass' => 'قدمت مثالًا صحيحًا يحتوي JOIN وON.',
                        'feedback_fail' => 'أضف مثالًا كاملًا يحتوي JOIN وشرط ON.',
                        'order' => 4,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_JOIN_CONFLICT_NO_RELATION',
                        'trigger_concept' => 'sql_join_without_relation_claim',
                        'feedback_ar' => 'JOIN تحتاج علاقة وشرط ربط مناسبًا بين الجداول، غالبًا عبر مفاتيح مرتبطة وON.',
                        'blocked_rubrics' => ['SQL_JOIN_RELATED_TABLES', 'SQL_JOIN_KEY_CONDITION', 'SQL_JOIN_VALID_EXAMPLE'],
                    ],
                ],
            ],
            [
                'question_text' => 'ما الفرق بين WHERE و HAVING؟',
                'topic' => 'sql_where_having',
                'rule_set_code' => 'SQL_WHERE_HAVING_V1',

                'concepts' => [
                    [
                        'code' => 'sql_where_before_grouping_rows',
                        'name_ar' => 'WHERE قبل التجميع',
                        'name_en' => 'WHERE filters rows before grouping',
                        'description_ar' => 'يوضح أن WHERE تصفي الصفوف قبل GROUP BY أو التجميع.',
                        'claim_ar' => 'يذكر الطالب أن WHERE تصفي الصفوف قبل التجميع أو قبل حساب التجميعات.',
                        'claim_en' => 'The student states that WHERE filters rows before grouping or aggregate calculation.',
                    ],
                    [
                        'code' => 'sql_having_after_grouping_aggregates',
                        'name_ar' => 'HAVING بعد التجميع',
                        'name_en' => 'HAVING filters groups after grouping',
                        'description_ar' => 'يوضح أن HAVING تصفي المجموعات أو نتائج التجميع بعد GROUP BY.',
                        'claim_ar' => 'يذكر الطالب أن HAVING تستخدم بعد GROUP BY لتصفية المجموعات أو نتائج مثل COUNT وSUM.',
                        'claim_en' => 'The student states that HAVING is used after GROUP BY to filter groups or aggregate results such as COUNT and SUM.',
                    ],
                    [
                        'code' => 'sql_where_having_valid_difference_example',
                        'name_ar' => 'مثال يوضح الفرق',
                        'name_en' => 'Valid WHERE versus HAVING example',
                        'description_ar' => 'يقدم مثالًا يفرق بين WHERE على صفوف وHAVING على aggregate.',
                        'claim_ar' => 'يقدم الطالب مثالًا صحيحًا يوضح WHERE قبل GROUP BY وHAVING مثل HAVING COUNT(*) > 1 بعده.',
                        'claim_en' => 'The student provides a valid example showing WHERE before GROUP BY and HAVING such as HAVING COUNT(*) > 1 after it.',
                    ],
                    [
                        'code' => 'sql_where_having_identical_claim',
                        'name_ar' => 'ادعاء التطابق بين WHERE وHAVING',
                        'name_en' => 'Claims WHERE and HAVING are identical',
                        'description_ar' => 'ادعاء خاطئ بأن WHERE وHAVING متطابقتان في كل الحالات.',
                        'claim_ar' => 'يدعي الطالب أن WHERE وHAVING متطابقتان تمامًا ويمكن استخدام أي منهما في أي موضع دون فرق.',
                        'claim_en' => 'The student claims WHERE and HAVING are identical and interchangeable everywhere.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_WHERE_BEFORE_GROUPING',
                        'name_ar' => 'WHERE لتصفية الصفوف قبل التجميع',
                        'description_ar' => 'يوضح موضع WHERE ووظيفتها قبل التجميع.',
                        'max_score' => 2.00,
                        'requires' => ['sql_where_before_grouping_rows'],
                        'blocked_by' => ['sql_where_having_identical_claim'],
                        'sample_good' => 'WHERE status = active before GROUP BY.',
                        'sample_bad' => 'Use HAVING before grouping for row filtering.',
                        'feedback_pass' => 'وضحت أن WHERE تصفي الصفوف قبل التجميع.',
                        'feedback_fail' => 'وضح أن WHERE تعمل على الصفوف قبل GROUP BY.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_HAVING_AFTER_GROUPING',
                        'name_ar' => 'HAVING لتصفية المجموعات',
                        'description_ar' => 'يوضح موضع HAVING ووظيفتها بعد التجميع.',
                        'max_score' => 2.00,
                        'requires' => ['sql_having_after_grouping_aggregates'],
                        'blocked_by' => ['sql_where_having_identical_claim'],
                        'sample_good' => 'HAVING COUNT(*) > 1 after GROUP BY.',
                        'sample_bad' => 'WHERE COUNT(*) > 1 after grouping.',
                        'feedback_pass' => 'وضحت أن HAVING تصفي المجموعات أو نتائج التجميع.',
                        'feedback_fail' => 'وضح أن HAVING تستخدم بعد GROUP BY مع النتائج المجمعة.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_WHERE_HAVING_EXAMPLE',
                        'name_ar' => 'مثال يوضح الفرق',
                        'description_ar' => 'يقدم مثالًا صحيحًا أو تمييزًا عمليًا بينهما.',
                        'max_score' => 1.00,
                        'requires' => ['sql_where_having_valid_difference_example'],
                        'blocked_by' => ['sql_where_having_identical_claim'],
                        'sample_good' => 'WHERE age >= 18 GROUP BY city HAVING COUNT(*) > 2;',
                        'sample_bad' => 'WHERE and HAVING are identical.',
                        'feedback_pass' => 'قدمت مثالًا يوضح الفرق بين WHERE وHAVING.',
                        'feedback_fail' => 'أضف مثالًا يبيّن WHERE قبل GROUP BY وHAVING بعده.',
                        'order' => 3,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_WHERE_HAVING_CONFLICT_IDENTICAL',
                        'trigger_concept' => 'sql_where_having_identical_claim',
                        'feedback_ar' => 'WHERE تصفي الصفوف قبل التجميع، بينما HAVING تصفي المجموعات أو النتائج المجمعة بعد GROUP BY.',
                        'blocked_rubrics' => ['SQL_WHERE_BEFORE_GROUPING', 'SQL_HAVING_AFTER_GROUPING', 'SQL_WHERE_HAVING_EXAMPLE'],
                    ],
                ],
            ],
            [
                'question_text' => 'كيف تستخدم GROUP BY مع COUNT لحساب عدد الطلبات لكل مستخدم؟',
                'topic' => 'sql_group_by_count',
                'rule_set_code' => 'SQL_GROUP_BY_COUNT_V1',

                'concepts' => [
                    [
                        'code' => 'sql_group_by_user_identifier',
                        'name_ar' => 'التجميع حسب المستخدم',
                        'name_en' => 'Groups by user identifier',
                        'description_ar' => 'يوضح استخدام GROUP BY user_id أو معرف المستخدم.',
                        'claim_ar' => 'يذكر الطالب GROUP BY user_id أو يشرح تجميع الطلبات حسب كل مستخدم.',
                        'claim_en' => 'The student mentions GROUP BY user_id or grouping orders by each user.',
                    ],
                    [
                        'code' => 'sql_group_by_count_orders',
                        'name_ar' => 'عد الطلبات باستخدام COUNT',
                        'name_en' => 'Counts orders using COUNT',
                        'description_ar' => 'يوضح استخدام COUNT أو COUNT(*) لحساب الطلبات.',
                        'claim_ar' => 'يذكر الطالب COUNT(*) أو COUNT(order_id) لحساب عدد الطلبات.',
                        'claim_en' => 'The student mentions COUNT(*) or COUNT(order_id) to calculate the number of orders.',
                    ],
                    [
                        'code' => 'sql_group_by_select_user_and_count',
                        'name_ar' => 'إظهار المستخدم والعدد',
                        'name_en' => 'Selects user and count',
                        'description_ar' => 'يوضح اختيار user_id مع قيمة COUNT واسم مستعار للعدد.',
                        'claim_ar' => 'يذكر الطالب اختيار user_id مع COUNT(*) مثل SELECT user_id, COUNT(*) AS order_count.',
                        'claim_en' => 'The student mentions selecting user_id with COUNT(*) such as SELECT user_id, COUNT(*) AS order_count.',
                    ],
                    [
                        'code' => 'sql_group_by_valid_count_query',
                        'name_ar' => 'استعلام GROUP BY صحيح',
                        'name_en' => 'Valid GROUP BY COUNT query',
                        'description_ar' => 'يقدم استعلامًا صحيحًا يجمع SELECT وCOUNT وGROUP BY.',
                        'claim_ar' => 'يقدم الطالب استعلامًا صحيحًا مثل SELECT user_id, COUNT(*) AS order_count FROM orders GROUP BY user_id.',
                        'claim_en' => 'The student provides a valid query such as SELECT user_id, COUNT(*) AS order_count FROM orders GROUP BY user_id.',
                    ],
                    [
                        'code' => 'sql_group_by_count_incompatible_claim',
                        'name_ar' => 'ادعاء عدم توافق COUNT وGROUP BY',
                        'name_en' => 'Claims COUNT and GROUP BY are incompatible',
                        'description_ar' => 'ادعاء خاطئ بأن COUNT لا يمكن استخدامها مع GROUP BY.',
                        'claim_ar' => 'يدعي الطالب أنه لا يمكن استخدام COUNT مع GROUP BY لحساب عدد الطلبات لكل مستخدم.',
                        'claim_en' => 'The student claims COUNT cannot be used with GROUP BY to count orders per user.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_GROUP_BY_USER',
                        'name_ar' => 'التجميع حسب user_id',
                        'description_ar' => 'يحدد GROUP BY user_id.',
                        'max_score' => 1.00,
                        'requires' => ['sql_group_by_user_identifier'],
                        'blocked_by' => ['sql_group_by_count_incompatible_claim'],
                        'sample_good' => 'GROUP BY user_id',
                        'sample_bad' => 'GROUP BY order_id for orders per user',
                        'feedback_pass' => 'وضحت التجميع حسب المستخدم.',
                        'feedback_fail' => 'أضف GROUP BY user_id لتجميع الطلبات لكل مستخدم.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_GROUP_BY_COUNT',
                        'name_ar' => 'استخدام COUNT للطلبات',
                        'description_ar' => 'يستخدم COUNT لحساب عدد الطلبات.',
                        'max_score' => 2.00,
                        'requires' => ['sql_group_by_count_orders'],
                        'blocked_by' => ['sql_group_by_count_incompatible_claim'],
                        'sample_good' => 'COUNT(*) AS order_count',
                        'sample_bad' => 'SUM(user_id)',
                        'feedback_pass' => 'وضحت استخدام COUNT لحساب الطلبات.',
                        'feedback_fail' => 'استخدم COUNT(*) أو COUNT(order_id) لحساب عدد الطلبات.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_GROUP_BY_SELECT_RESULT',
                        'name_ar' => 'عرض المستخدم والعدد',
                        'description_ar' => 'يعرض user_id مع العدد.',
                        'max_score' => 1.00,
                        'requires' => ['sql_group_by_select_user_and_count'],
                        'blocked_by' => [],
                        'sample_good' => 'SELECT user_id, COUNT(*) AS order_count',
                        'sample_bad' => 'SELECT only COUNT(*) without user context',
                        'feedback_pass' => 'وضحت عرض المستخدم مع عدد طلباته.',
                        'feedback_fail' => 'اعرض user_id مع COUNT لتعرف عدد الطلبات لكل مستخدم.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_GROUP_BY_VALID_QUERY',
                        'name_ar' => 'استعلام صحيح كامل',
                        'description_ar' => 'يقدم استعلام GROUP BY وCOUNT صحيحًا.',
                        'max_score' => 1.00,
                        'requires' => ['sql_group_by_valid_count_query'],
                        'blocked_by' => ['sql_group_by_count_incompatible_claim'],
                        'sample_good' => 'SELECT user_id, COUNT(*) AS order_count FROM orders GROUP BY user_id;',
                        'sample_bad' => 'SELECT user_id, COUNT(*) FROM orders;',
                        'feedback_pass' => 'قدمت استعلام GROUP BY وCOUNT صحيحًا.',
                        'feedback_fail' => 'اكتب استعلامًا كاملًا يتضمن SELECT وCOUNT وFROM orders وGROUP BY user_id.',
                        'order' => 4,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_GROUP_BY_CONFLICT_INCOMPATIBLE',
                        'trigger_concept' => 'sql_group_by_count_incompatible_claim',
                        'feedback_ar' => 'COUNT تستخدم عادة مع GROUP BY لحساب عدد الصفوف داخل كل مجموعة، مثل عدد الطلبات لكل مستخدم.',
                        'blocked_rubrics' => ['SQL_GROUP_BY_USER', 'SQL_GROUP_BY_COUNT', 'SQL_GROUP_BY_VALID_QUERY'],
                    ],
                ],
            ],
            [
                'question_text' => 'ما هو subquery في SQL؟ ومتى قد تحتاجه؟',
                'topic' => 'sql_subqueries',
                'rule_set_code' => 'SQL_SUBQUERY_V1',

                'concepts' => [
                    [
                        'code' => 'sql_subquery_nested_query',
                        'name_ar' => 'استعلام داخل استعلام',
                        'name_en' => 'Subquery is nested query',
                        'description_ar' => 'يوضح أن subquery هي استعلام SQL داخل استعلام آخر.',
                        'claim_ar' => 'يذكر الطالب أن subquery هي استعلام داخل استعلام آخر أو nested query.',
                        'claim_en' => 'The student states that a subquery is a query inside another query.',
                    ],
                    [
                        'code' => 'sql_subquery_used_for_filter_or_comparison',
                        'name_ar' => 'استخدام subquery للتصفية أو المقارنة',
                        'name_en' => 'Subquery used for filtering or comparison',
                        'description_ar' => 'يوضح استخدامها للحصول على قيمة أو مجموعة تستخدمها الاستعلام الخارجي.',
                        'claim_ar' => 'يذكر الطالب أن subquery تستخدم للتصفية أو المقارنة أو لاستخراج قيمة أو مجموعة يعتمد عليها الاستعلام الخارجي.',
                        'claim_en' => 'The student states that a subquery is used for filtering, comparison, or producing a value/set used by the outer query.',
                    ],
                    [
                        'code' => 'sql_subquery_valid_location_or_example',
                        'name_ar' => 'موضع أو مثال صحيح لـsubquery',
                        'name_en' => 'Valid subquery location or example',
                        'description_ar' => 'يذكر موضعًا صحيحًا مثل WHERE أو FROM أو يقدم مثالًا صحيحًا.',
                        'claim_ar' => 'يذكر الطالب أن subquery يمكن أن تكون في WHERE أو FROM أو يقدم مثالًا مثل WHERE user_id IN (SELECT id FROM users).',
                        'claim_en' => 'The student mentions that a subquery can be in WHERE or FROM, or gives an example such as WHERE user_id IN (SELECT id FROM users).',
                    ],
                    [
                        'code' => 'sql_subquery_valid_outer_inner_relation',
                        'name_ar' => 'علاقة الاستعلام الخارجي والداخلي',
                        'name_en' => 'Outer and inner query relation',
                        'description_ar' => 'يوضح أن الاستعلام الخارجي يستخدم نتيجة الاستعلام الداخلي.',
                        'claim_ar' => 'يوضح الطالب أن الاستعلام الخارجي يستخدم النتيجة التي يعيدها الاستعلام الداخلي.',
                        'claim_en' => 'The student explains that the outer query uses the result returned by the inner query.',
                    ],
                    [
                        'code' => 'sql_subquery_cannot_be_nested_claim',
                        'name_ar' => 'ادعاء عدم إمكان التداخل',
                        'name_en' => 'Claims subquery cannot be nested',
                        'description_ar' => 'ادعاء خاطئ بأن الاستعلام لا يمكن أن يوجد داخل استعلام آخر.',
                        'claim_ar' => 'يدعي الطالب أن SQL لا تسمح بوضع استعلام داخل استعلام آخر وأن subquery يجب أن تكون مستقلة تمامًا.',
                        'claim_en' => 'The student claims SQL does not allow a query inside another query and a subquery must be completely independent.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_SUBQUERY_DEFINITION',
                        'name_ar' => 'تعريف subquery',
                        'description_ar' => 'يوضح أنها استعلام داخل استعلام.',
                        'max_score' => 2.00,
                        'requires' => ['sql_subquery_nested_query'],
                        'blocked_by' => ['sql_subquery_cannot_be_nested_claim'],
                        'sample_good' => 'A subquery is a query inside another query.',
                        'sample_bad' => 'A subquery cannot be nested.',
                        'feedback_pass' => 'وضحت أن subquery هي استعلام داخل استعلام آخر.',
                        'feedback_fail' => 'عرّف subquery بأنها استعلام SQL داخل استعلام آخر.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_SUBQUERY_PURPOSE',
                        'name_ar' => 'سبب استخدام subquery',
                        'description_ar' => 'يذكر استخدامها للتصفية أو المقارنة أو توفير نتيجة للخارجي.',
                        'max_score' => 1.00,
                        'requires' => ['sql_subquery_used_for_filter_or_comparison'],
                        'blocked_by' => [],
                        'sample_good' => 'Use it to filter rows using a derived set.',
                        'sample_bad' => 'Use it only to format output text.',
                        'feedback_pass' => 'وضحت سبب استخدام subquery.',
                        'feedback_fail' => 'اذكر أنها قد تستخدم للتصفية أو المقارنة أو الحصول على نتائج يعتمد عليها الاستعلام الخارجي.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_SUBQUERY_LOCATION_EXAMPLE',
                        'name_ar' => 'موضع أو مثال صحيح',
                        'description_ar' => 'يذكر WHERE أو FROM أو يقدم مثالًا صحيحًا.',
                        'max_score' => 1.00,
                        'requires' => ['sql_subquery_valid_location_or_example'],
                        'blocked_by' => ['sql_subquery_cannot_be_nested_claim'],
                        'sample_good' => 'WHERE user_id IN (SELECT id FROM users)',
                        'sample_bad' => 'SELECT without an outer query relation',
                        'feedback_pass' => 'قدمت موضعًا أو مثالًا صحيحًا لـsubquery.',
                        'feedback_fail' => 'اذكر موضعًا مثل WHERE أو FROM أو اكتب مثالًا صحيحًا.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_SUBQUERY_OUTER_RESULT',
                        'name_ar' => 'علاقة الداخلي بالخارجي',
                        'description_ar' => 'يوضح استخدام الخارجي لنتيجة الداخلي.',
                        'max_score' => 1.00,
                        'requires' => ['sql_subquery_valid_outer_inner_relation'],
                        'blocked_by' => [],
                        'sample_good' => 'Outer query uses the inner query result.',
                        'sample_bad' => 'Inner query has no relation to outer query.',
                        'feedback_pass' => 'وضحت علاقة نتيجة الاستعلام الداخلي بالخارجي.',
                        'feedback_fail' => 'وضح أن الاستعلام الخارجي يستخدم نتيجة الاستعلام الداخلي.',
                        'order' => 4,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_SUBQUERY_CONFLICT_NO_NESTING',
                        'trigger_concept' => 'sql_subquery_cannot_be_nested_claim',
                        'feedback_ar' => 'subquery هي بالضبط استعلام داخل استعلام آخر؛ يمكن أن تستخدم نتيجتها في WHERE أو FROM أو مواضع صحيحة أخرى.',
                        'blocked_rubrics' => ['SQL_SUBQUERY_DEFINITION', 'SQL_SUBQUERY_LOCATION_EXAMPLE'],
                    ],
                ],
            ],
            [
                'question_text' => 'ما المقصود بتطبيع الجداول (Normalization) بشكل مبسط؟',
                'topic' => 'sql_normalization',
                'rule_set_code' => 'SQL_NORMALIZATION_V1',

                'concepts' => [
                    [
                        'code' => 'sql_normalization_reduces_redundancy',
                        'name_ar' => 'تقليل التكرار',
                        'name_en' => 'Normalization reduces redundancy',
                        'description_ar' => 'يوضح أن التطبيع ينظم البيانات لتقليل التكرار غير الضروري.',
                        'claim_ar' => 'يذكر الطالب أن Normalization تنظم الجداول أو تقلل تكرار البيانات غير الضروري.',
                        'claim_en' => 'The student states that normalization organizes tables or reduces unnecessary data duplication.',
                    ],
                    [
                        'code' => 'sql_normalization_prevents_anomalies',
                        'name_ar' => 'تقليل مشاكل التحديث',
                        'name_en' => 'Normalization prevents anomalies',
                        'description_ar' => 'يوضح تقليل مشاكل الإدخال والتحديث والحذف الناتجة عن التكرار.',
                        'claim_ar' => 'يذكر الطالب أن التطبيع يقلل مشاكل أو anomalies التحديث أو الإدخال أو الحذف.',
                        'claim_en' => 'The student states that normalization reduces update, insert, or delete anomalies.',
                    ],
                    [
                        'code' => 'sql_normalization_separates_entities_relationships',
                        'name_ar' => 'فصل الكيانات والعلاقات',
                        'name_en' => 'Normalization separates entities and relationships',
                        'description_ar' => 'يوضح فصل الكيانات مثل users وorders في جداول مترابطة بمفاتيح.',
                        'claim_ar' => 'يذكر الطالب فصل كيانات مثل users وorders في جداول منفصلة مرتبطة بمفاتيح.',
                        'claim_en' => 'The student mentions separating entities such as users and orders into related tables using keys.',
                    ],
                    [
                        'code' => 'sql_normalization_valid_example',
                        'name_ar' => 'مثال تطبيع صحيح',
                        'name_en' => 'Valid normalization example',
                        'description_ar' => 'يقدم مثالًا بسيطًا على فصل البيانات المتكررة.',
                        'claim_ar' => 'يقدم الطالب مثالًا مثل وضع بيانات المستخدم في users والطلبات في orders بدل تكرار اسم المستخدم في كل طلب.',
                        'claim_en' => 'The student provides an example such as storing user data in users and orders in orders rather than repeating the user name in every order.',
                    ],
                    [
                        'code' => 'sql_normalization_duplicate_everything_claim',
                        'name_ar' => 'ادعاء أن التطبيع يعني تكرار البيانات',
                        'name_en' => 'Claims normalization means duplication',
                        'description_ar' => 'ادعاء خاطئ بأن التطبيع يعني نسخ نفس البيانات في كل جدول أو صف.',
                        'claim_ar' => 'يدعي الطالب أن Normalization تعني تكرار أو نسخ نفس البيانات في كل جدول أو كل صف.',
                        'claim_en' => 'The student claims normalization means repeating or copying the same data in every table or row.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_NORMALIZATION_REDUNDANCY',
                        'name_ar' => 'تقليل التكرار',
                        'description_ar' => 'يوضح أن التطبيع يقلل التكرار غير الضروري.',
                        'max_score' => 2.00,
                        'requires' => ['sql_normalization_reduces_redundancy'],
                        'blocked_by' => ['sql_normalization_duplicate_everything_claim'],
                        'sample_good' => 'Reduce repeated user data.',
                        'sample_bad' => 'Duplicate the same data in every row.',
                        'feedback_pass' => 'وضحت أن التطبيع يقلل التكرار.',
                        'feedback_fail' => 'وضح أن التطبيع ينظم البيانات ويقلل التكرار غير الضروري.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_NORMALIZATION_ANOMALIES',
                        'name_ar' => 'تقليل مشاكل التحديث',
                        'description_ar' => 'يذكر تقليل مشاكل الإدخال أو التحديث أو الحذف.',
                        'max_score' => 1.00,
                        'requires' => ['sql_normalization_prevents_anomalies'],
                        'blocked_by' => [],
                        'sample_good' => 'Avoid update anomalies.',
                        'sample_bad' => 'Increase update anomalies.',
                        'feedback_pass' => 'وضحت أن التطبيع يقلل مشاكل تحديث البيانات.',
                        'feedback_fail' => 'اذكر أن التطبيع يقلل مشاكل الإدخال أو التحديث أو الحذف.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_NORMALIZATION_ENTITIES',
                        'name_ar' => 'فصل الكيانات المرتبطة',
                        'description_ar' => 'يوضح فصل الكيانات في جداول مترابطة.',
                        'max_score' => 1.00,
                        'requires' => ['sql_normalization_separates_entities_relationships'],
                        'blocked_by' => [],
                        'sample_good' => 'Users and orders in related tables.',
                        'sample_bad' => 'Store every entity in one flat table.',
                        'feedback_pass' => 'وضحت فصل الكيانات في جداول مترابطة.',
                        'feedback_fail' => 'اذكر فصل كيانات مثل users وorders وربطها بالمفاتيح.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_NORMALIZATION_EXAMPLE',
                        'name_ar' => 'مثال بسيط صحيح',
                        'description_ar' => 'يقدم مثالًا مناسبًا للتطبيع.',
                        'max_score' => 1.00,
                        'requires' => ['sql_normalization_valid_example'],
                        'blocked_by' => ['sql_normalization_duplicate_everything_claim'],
                        'sample_good' => 'Store user details once in users and orders separately.',
                        'sample_bad' => 'Repeat user details in every order row.',
                        'feedback_pass' => 'قدمت مثالًا صحيحًا للتطبيع.',
                        'feedback_fail' => 'أضف مثالًا على فصل البيانات المتكررة في جداول مترابطة.',
                        'order' => 4,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_NORMALIZATION_CONFLICT_DUPLICATE',
                        'trigger_concept' => 'sql_normalization_duplicate_everything_claim',
                        'feedback_ar' => 'Normalization تهدف لتقليل تكرار البيانات غير الضروري، لا إلى نسخها في كل جدول أو صف.',
                        'blocked_rubrics' => ['SQL_NORMALIZATION_REDUNDANCY', 'SQL_NORMALIZATION_EXAMPLE'],
                    ],
                ],
            ],
            [
                'question_text' => 'كيف تكتب استعلامًا يحتوي على أكثر من شرط باستخدام AND و OR دون الوقوع في أخطاء منطقية؟',
                'topic' => 'sql_and_or_logic',
                'rule_set_code' => 'SQL_AND_OR_LOGIC_V1',

                'concepts' => [
                    [
                        'code' => 'sql_and_requires_all_conditions',
                        'name_ar' => 'معنى AND',
                        'name_en' => 'AND requires all conditions',
                        'description_ar' => 'يوضح أن AND تتطلب تحقق كل الشروط المرتبطة.',
                        'claim_ar' => 'يذكر الطالب أن AND تعني أن جميع الشروط يجب أن تكون صحيحة.',
                        'claim_en' => 'The student states that AND means all connected conditions must be true.',
                    ],
                    [
                        'code' => 'sql_or_requires_any_condition',
                        'name_ar' => 'معنى OR',
                        'name_en' => 'OR requires any condition',
                        'description_ar' => 'يوضح أن OR تقبل تحقق شرط واحد على الأقل.',
                        'claim_ar' => 'يذكر الطالب أن OR تعني أن تحقق شرط واحد على الأقل يكفي.',
                        'claim_en' => 'The student states that OR means at least one condition must be true.',
                    ],
                    [
                        'code' => 'sql_and_or_parentheses_precedence',
                        'name_ar' => 'الأقواس لضبط الأولوية',
                        'name_en' => 'Parentheses control AND OR precedence',
                        'description_ar' => 'يوضح استخدام الأقواس لتحديد ترتيب التقييم عند مزج AND وOR.',
                        'claim_ar' => 'يذكر الطالب استخدام الأقواس عند مزج AND وOR لتحديد المنطق والأولوية بوضوح.',
                        'claim_en' => 'The student mentions using parentheses when mixing AND and OR to make logic and precedence explicit.',
                    ],
                    [
                        'code' => 'sql_and_or_valid_example',
                        'name_ar' => 'مثال منطقي صحيح',
                        'name_en' => 'Valid AND OR example',
                        'description_ar' => 'يقدم مثالًا صحيحًا يستخدم الأقواس عند المزج.',
                        'claim_ar' => 'يقدم الطالب مثالًا مثل WHERE status = active AND (city = Damascus OR city = Homs).',
                        'claim_en' => 'The student provides an example such as WHERE status = active AND (city = Damascus OR city = Homs).',
                    ],
                    [
                        'code' => 'sql_and_or_no_parentheses_needed_claim',
                        'name_ar' => 'ادعاء عدم الحاجة للأقواس',
                        'name_en' => 'Claims parentheses are never needed',
                        'description_ar' => 'ادعاء خاطئ بأن AND وOR لهما دائمًا نفس الأولوية ولا تحتاجان أقواسًا.',
                        'claim_ar' => 'يدعي الطالب أن AND وOR لهما نفس الأولوية دائمًا ولا حاجة للأقواس عند مزجهما.',
                        'claim_en' => 'The student claims AND and OR always have the same precedence and parentheses are never needed when mixing them.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_AND_OR_AND',
                        'name_ar' => 'معنى AND',
                        'description_ar' => 'يوضح أن AND تتطلب تحقق جميع الشروط.',
                        'max_score' => 1.00,
                        'requires' => ['sql_and_requires_all_conditions'],
                        'blocked_by' => [],
                        'sample_good' => 'age >= 18 AND status = active',
                        'sample_bad' => 'AND means one condition is enough.',
                        'feedback_pass' => 'وضحت أن AND تتطلب تحقق جميع الشروط.',
                        'feedback_fail' => 'وضح أن AND تتطلب تحقق كل الشروط المرتبطة.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_AND_OR_OR',
                        'name_ar' => 'معنى OR',
                        'description_ar' => 'يوضح أن OR يكفي معها تحقق شرط واحد.',
                        'max_score' => 1.00,
                        'requires' => ['sql_or_requires_any_condition'],
                        'blocked_by' => [],
                        'sample_good' => 'city = Damascus OR city = Homs',
                        'sample_bad' => 'OR requires every condition.',
                        'feedback_pass' => 'وضحت أن OR يكفي معها تحقق شرط واحد على الأقل.',
                        'feedback_fail' => 'وضح أن OR تكفي معها صحة شرط واحد على الأقل.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_AND_OR_PARENTHESES',
                        'name_ar' => 'استخدام الأقواس لضبط المنطق',
                        'description_ar' => 'يذكر الأقواس لتحديد أولوية AND وOR.',
                        'max_score' => 2.00,
                        'requires' => ['sql_and_or_parentheses_precedence'],
                        'blocked_by' => ['sql_and_or_no_parentheses_needed_claim'],
                        'sample_good' => 'AND (city = Damascus OR city = Homs)',
                        'sample_bad' => 'Mix AND and OR with no thought for precedence.',
                        'feedback_pass' => 'وضحت دور الأقواس في ضبط الأولوية والمنطق.',
                        'feedback_fail' => 'استخدم الأقواس عند مزج AND وOR لتوضيح أولوية التقييم.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_AND_OR_EXAMPLE',
                        'name_ar' => 'مثال صحيح',
                        'description_ar' => 'يقدم استعلامًا أو شرطًا صحيحًا يجمع AND وOR.',
                        'max_score' => 1.00,
                        'requires' => ['sql_and_or_valid_example'],
                        'blocked_by' => ['sql_and_or_no_parentheses_needed_claim'],
                        'sample_good' => 'WHERE status = active AND (city = Damascus OR city = Homs);',
                        'sample_bad' => 'WHERE status = active AND city = Damascus OR city = Homs without clear intent.',
                        'feedback_pass' => 'قدمت مثالًا صحيحًا يجمع AND وOR.',
                        'feedback_fail' => 'أضف مثالًا يحتوي AND وOR مع أقواس توضح المنطق.',
                        'order' => 4,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_AND_OR_CONFLICT_NO_PARENTHESES',
                        'trigger_concept' => 'sql_and_or_no_parentheses_needed_claim',
                        'feedback_ar' => 'عند مزج AND وOR قد تغير الأولوية النتيجة؛ استخدم الأقواس لتحديد المنطق المقصود بوضوح.',
                        'blocked_rubrics' => ['SQL_AND_OR_PARENTHESES', 'SQL_AND_OR_EXAMPLE'],
                    ],
                ],
            ],
            [
                'question_text' => 'ما فائدة index في قواعد البيانات العلائقية؟ وما أثره على الأداء؟',
                'topic' => 'sql_indexes',
                'rule_set_code' => 'SQL_INDEXES_V1',

                'concepts' => [
                    [
                        'code' => 'sql_index_speeds_lookup_filter_join',
                        'name_ar' => 'تسريع البحث والتصفية',
                        'name_en' => 'Index speeds lookup filtering joins',
                        'description_ar' => 'يوضح أن index تساعد على تسريع البحث والتصفية والربط أو الفرز في الحالات المناسبة.',
                        'claim_ar' => 'يذكر الطالب أن index تسرع البحث أو التصفية أو JOIN أو ORDER BY في الاستعلامات المناسبة.',
                        'claim_en' => 'The student states that an index speeds up lookup, filtering, JOIN, or ORDER BY in suitable queries.',
                    ],
                    [
                        'code' => 'sql_index_has_write_storage_tradeoff',
                        'name_ar' => 'تكلفة الكتابة والتخزين',
                        'name_en' => 'Index has write and storage tradeoffs',
                        'description_ar' => 'يوضح أن index تستهلك مساحة وقد تبطئ عمليات INSERT أو UPDATE أو DELETE.',
                        'claim_ar' => 'يذكر الطالب أن index تحتاج مساحة أو قد تضيف تكلفة على INSERT وUPDATE وDELETE.',
                        'claim_en' => 'The student states that indexes consume space or can add cost to INSERT, UPDATE, and DELETE.',
                    ],
                    [
                        'code' => 'sql_index_choose_frequent_selective_columns',
                        'name_ar' => 'اختيار أعمدة مناسبة للفهرسة',
                        'name_en' => 'Choose frequent selective columns for indexing',
                        'description_ar' => 'يوضح اختيار الأعمدة المستخدمة بكثرة في شروط التصفية أو الربط.',
                        'claim_ar' => 'يذكر الطالب اختيار أعمدة تستخدم بكثرة في WHERE أو JOIN أو أعمدة ذات انتقائية جيدة للفهرسة.',
                        'claim_en' => 'The student mentions choosing columns frequently used in WHERE or JOIN or with good selectivity for indexing.',
                    ],
                    [
                        'code' => 'sql_index_verify_with_explain',
                        'name_ar' => 'التحقق بخطة التنفيذ',
                        'name_en' => 'Verify index with EXPLAIN',
                        'description_ar' => 'يوضح استخدام EXPLAIN أو خطة التنفيذ للتحقق من الاستفادة.',
                        'claim_ar' => 'يذكر الطالب استخدام EXPLAIN أو execution plan للتحقق من استخدام index وأثرها.',
                        'claim_en' => 'The student mentions using EXPLAIN or an execution plan to verify index use and impact.',
                    ],
                    [
                        'code' => 'sql_index_no_cost_always_faster_claim',
                        'name_ar' => 'ادعاء أن index بلا تكلفة ودائمًا أسرع',
                        'name_en' => 'Claims indexes have no cost and always faster',
                        'description_ar' => 'ادعاء خاطئ بأن index بلا أثر جانبي وتسرع كل عملية دائمًا.',
                        'claim_ar' => 'يدعي الطالب أن index لا تستهلك مساحة ولا تؤثر على الكتابة وتسرع كل عملية دائمًا دون استثناء.',
                        'claim_en' => 'The student claims indexes use no space, never affect writes, and always speed every operation without exception.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_INDEX_SPEED',
                        'name_ar' => 'فائدة index في التسريع',
                        'description_ar' => 'يوضح تسريع البحث أو التصفية أو الربط.',
                        'max_score' => 2.00,
                        'requires' => ['sql_index_speeds_lookup_filter_join'],
                        'blocked_by' => ['sql_index_no_cost_always_faster_claim'],
                        'sample_good' => 'Index on user_id speeds WHERE and JOIN queries.',
                        'sample_bad' => 'Index has no use for lookup queries.',
                        'feedback_pass' => 'وضحت دور index في تسريع الاستعلامات المناسبة.',
                        'feedback_fail' => 'وضح أن index تساعد في البحث والتصفية أو الربط على الأعمدة المناسبة.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_INDEX_TRADEOFF',
                        'name_ar' => 'تكلفة index',
                        'description_ar' => 'يذكر مساحة التخزين أو تكلفة عمليات الكتابة.',
                        'max_score' => 1.00,
                        'requires' => ['sql_index_has_write_storage_tradeoff'],
                        'blocked_by' => ['sql_index_no_cost_always_faster_claim'],
                        'sample_good' => 'Indexes use storage and add write cost.',
                        'sample_bad' => 'Indexes have no cost.',
                        'feedback_pass' => 'وضحت وجود مفاضلة بين سرعة القراءة وكلفة الكتابة أو التخزين.',
                        'feedback_fail' => 'اذكر أن index تستهلك مساحة وقد تزيد تكلفة INSERT وUPDATE وDELETE.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_INDEX_COLUMN_CHOICE',
                        'name_ar' => 'اختيار الأعمدة المناسبة',
                        'description_ar' => 'يذكر اختيار أعمدة WHERE أو JOIN المتكررة أو الانتقائية.',
                        'max_score' => 1.00,
                        'requires' => ['sql_index_choose_frequent_selective_columns'],
                        'blocked_by' => [],
                        'sample_good' => 'Index a frequently filtered foreign key.',
                        'sample_bad' => 'Index every column by default.',
                        'feedback_pass' => 'وضحت اختيار أعمدة مناسبة للفهرسة.',
                        'feedback_fail' => 'اذكر اختيار أعمدة تستخدم بكثرة في WHERE أو JOIN أو ذات انتقائية جيدة.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_INDEX_EXPLAIN',
                        'name_ar' => 'التحقق من الأثر',
                        'description_ar' => 'يذكر EXPLAIN أو خطة التنفيذ للتحقق من الفهرس.',
                        'max_score' => 1.00,
                        'requires' => ['sql_index_verify_with_explain'],
                        'blocked_by' => [],
                        'sample_good' => 'Use EXPLAIN before and after adding an index.',
                        'sample_bad' => 'Assume every index is used without checking.',
                        'feedback_pass' => 'وضحت التحقق من استخدام index وخطتها.',
                        'feedback_fail' => 'اذكر EXPLAIN أو execution plan للتحقق من أثر index.',
                        'order' => 4,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_INDEX_CONFLICT_NO_COST_ALWAYS_FAST',
                        'trigger_concept' => 'sql_index_no_cost_always_faster_claim',
                        'feedback_ar' => 'index قد تسرع بعض قراءات الاستعلامات، لكنها تستهلك مساحة وقد تضيف كلفة على عمليات الكتابة؛ يجب اختيارها وقياس أثرها.',
                        'blocked_rubrics' => ['SQL_INDEX_SPEED', 'SQL_INDEX_TRADEOFF'],
                    ],
                ],
            ],
            [
                'question_text' => 'كيف تفسّر بطء استعلام SQL بشكل مبدئي؟',
                'topic' => 'sql_query_performance_diagnosis',
                'rule_set_code' => 'SQL_QUERY_PERFORMANCE_DIAGNOSIS_V1',

                'concepts' => [
                    [
                        'code' => 'sql_slow_query_use_explain_plan',
                        'name_ar' => 'استخدام EXPLAIN',
                        'name_en' => 'Use EXPLAIN plan',
                        'description_ar' => 'يوضح بدء التشخيص عبر EXPLAIN أو execution plan.',
                        'claim_ar' => 'يذكر الطالب استخدام EXPLAIN أو execution plan لفهم خطة الاستعلام.',
                        'claim_en' => 'The student mentions using EXPLAIN or an execution plan to understand the query plan.',
                    ],
                    [
                        'code' => 'sql_slow_query_check_scans_joins_indexes',
                        'name_ar' => 'فحص scans وJOIN وindexes',
                        'name_en' => 'Check scans joins indexes',
                        'description_ar' => 'يوضح فحص full scans وjoins وشروط التصفية والفهارس.',
                        'claim_ar' => 'يذكر الطالب فحص full table scans أو joins أو شروط WHERE أو غياب indexes.',
                        'claim_en' => 'The student mentions checking full table scans, joins, WHERE conditions, or missing indexes.',
                    ],
                    [
                        'code' => 'sql_slow_query_measure_data_cardinality',
                        'name_ar' => 'قياس البيانات والانتقائية',
                        'name_en' => 'Measure data and cardinality',
                        'description_ar' => 'يوضح النظر لحجم البيانات والانتقائية أو عدد الصفوف المرجعة.',
                        'claim_ar' => 'يذكر الطالب حجم البيانات أو cardinality أو عدد الصفوف التي يقرأها أو يعيدها الاستعلام.',
                        'claim_en' => 'The student mentions data volume, cardinality, or the number of rows read or returned by the query.',
                    ],
                    [
                        'code' => 'sql_slow_query_improve_and_remeasure',
                        'name_ar' => 'تحسين ثم إعادة القياس',
                        'name_en' => 'Improve and remeasure',
                        'description_ar' => 'يوضح تحسين الاستعلام أو الفهرس ثم إعادة القياس.',
                        'claim_ar' => 'يذكر الطالب تعديل الاستعلام أو index ثم إعادة القياس أو المقارنة قبل وبعد.',
                        'claim_en' => 'The student mentions changing the query or index and then remeasuring or comparing before and after.',
                    ],
                    [
                        'code' => 'sql_slow_query_no_plan_needed_claim',
                        'name_ar' => 'ادعاء عدم الحاجة لخطة التنفيذ',
                        'name_en' => 'Claims no execution plan needed',
                        'description_ar' => 'ادعاء خاطئ بأن بطء SQL يعالج بالتخمين دون EXPLAIN أو فحص.',
                        'claim_ar' => 'يدعي الطالب أنه لا حاجة إلى EXPLAIN أو فحص الخطة عند تشخيص بطء الاستعلام وأن التخمين يكفي.',
                        'claim_en' => 'The student claims there is no need for EXPLAIN or plan inspection when diagnosing slow queries and guessing is enough.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_SLOW_EXPLAIN',
                        'name_ar' => 'بدء التشخيص بـEXPLAIN',
                        'description_ar' => 'يذكر EXPLAIN أو execution plan.',
                        'max_score' => 2.00,
                        'requires' => ['sql_slow_query_use_explain_plan'],
                        'blocked_by' => ['sql_slow_query_no_plan_needed_claim'],
                        'sample_good' => 'Run EXPLAIN for the slow query.',
                        'sample_bad' => 'Guess without inspecting a plan.',
                        'feedback_pass' => 'وضحت بدء التشخيص عبر خطة التنفيذ.',
                        'feedback_fail' => 'استخدم EXPLAIN أو execution plan لفهم طريقة تنفيذ الاستعلام.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_SLOW_SCANS_JOINS_INDEXES',
                        'name_ar' => 'فحص أسباب التنفيذ',
                        'description_ar' => 'يفحص scans وjoins وشروط WHERE والفهارس.',
                        'max_score' => 1.00,
                        'requires' => ['sql_slow_query_check_scans_joins_indexes'],
                        'blocked_by' => [],
                        'sample_good' => 'Check for full scans and missing indexes.',
                        'sample_bad' => 'Ignore joins and filters.',
                        'feedback_pass' => 'وضحت فحص أسباب مثل full scans وjoins والفهارس.',
                        'feedback_fail' => 'افحص full scans والـjoins والشروط والفهارس.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_SLOW_DATA_CARDINALITY',
                        'name_ar' => 'فحص حجم البيانات والانتقائية',
                        'description_ar' => 'يذكر حجم البيانات أو عدد الصفوف أو cardinality.',
                        'max_score' => 1.00,
                        'requires' => ['sql_slow_query_measure_data_cardinality'],
                        'blocked_by' => [],
                        'sample_good' => 'Inspect rows scanned and selectivity.',
                        'sample_bad' => 'Ignore number of rows read.',
                        'feedback_pass' => 'وضحت أهمية حجم البيانات والانتقائية.',
                        'feedback_fail' => 'اذكر عدد الصفوف أو حجم البيانات أو selectivity عند تفسير البطء.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_SLOW_IMPROVE_REMEASURE',
                        'name_ar' => 'تحسين وإعادة قياس',
                        'description_ar' => 'يذكر تحسينًا ثم قياس أثره.',
                        'max_score' => 1.00,
                        'requires' => ['sql_slow_query_improve_and_remeasure'],
                        'blocked_by' => [],
                        'sample_good' => 'Add an index then compare plan and time.',
                        'sample_bad' => 'Apply a change without validating it.',
                        'feedback_pass' => 'وضحت التحسين ثم قياس أثره.',
                        'feedback_fail' => 'عدّل الاستعلام أو الفهرس ثم قارن الأداء قبل وبعد.',
                        'order' => 4,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_SLOW_QUERY_CONFLICT_NO_PLAN',
                        'trigger_concept' => 'sql_slow_query_no_plan_needed_claim',
                        'feedback_ar' => 'EXPLAIN وخطة التنفيذ من أهم أدوات تشخيص بطء SQL؛ التخمين وحده لا يوضح أين يقرأ الاستعلام البيانات أو كيف ينفذ joins.',
                        'blocked_rubrics' => ['SQL_SLOW_EXPLAIN'],
                    ],
                ],
            ],
            [
                'question_text' => 'ما الأخطاء الشائعة التي قد تجعل استعلام SQL غير فعال؟',
                'topic' => 'sql_query_inefficiencies',
                'rule_set_code' => 'SQL_QUERY_INEFFICIENCIES_V1',

                'concepts' => [
                    [
                        'code' => 'sql_inefficient_avoid_select_star',
                        'name_ar' => 'تجنب SELECT * غير الضرورية',
                        'name_en' => 'Avoid unnecessary SELECT star',
                        'description_ar' => 'يوضح أن SELECT * قد تجلب أعمدة لا يحتاجها التطبيق.',
                        'claim_ar' => 'يذكر الطالب أن SELECT * قد تجلب أعمدة أو بيانات غير مطلوبة ويُفضل اختيار الأعمدة اللازمة.',
                        'claim_en' => 'The student states that SELECT * can fetch unneeded columns/data and selecting required columns is preferable.',
                    ],
                    [
                        'code' => 'sql_inefficient_missing_indexes_full_scans',
                        'name_ar' => 'غياب الفهارس وfull scans',
                        'name_en' => 'Missing indexes and full scans',
                        'description_ar' => 'يوضح أن غياب index مناسب قد يسبب full table scans مكلفة.',
                        'claim_ar' => 'يذكر الطالب أن غياب index مناسب أو full table scan على بيانات كبيرة قد يسبب بطئًا.',
                        'claim_en' => 'The student states that missing suitable indexes or full table scans on large data can cause slowness.',
                    ],
                    [
                        'code' => 'sql_inefficient_joins_filters_conditions',
                        'name_ar' => 'joins وشروط غير فعالة',
                        'name_en' => 'Inefficient joins filters conditions',
                        'description_ar' => 'يوضح أن joins أو conditions سيئة أو غير دقيقة قد تسبب كلفة عالية.',
                        'claim_ar' => 'يذكر الطالب أن joins غير المدروسة أو شروط WHERE غير فعالة أو functions على الأعمدة المفهرسة قد تبطئ الاستعلام.',
                        'claim_en' => 'The student mentions poorly designed joins, inefficient WHERE conditions, or functions on indexed columns that can slow a query.',
                    ],
                    [
                        'code' => 'sql_inefficient_unbounded_results_pagination',
                        'name_ar' => 'نتائج غير محدودة',
                        'name_en' => 'Unbounded results need pagination',
                        'description_ar' => 'يوضح أن جلب عدد كبير من الصفوف بلا LIMIT أو pagination قد يكون مكلفًا.',
                        'claim_ar' => 'يذكر الطالب أن جلب كل الصفوف بلا LIMIT أو pagination قد يجعل الاستعلام غير فعال.',
                        'claim_en' => 'The student states that fetching all rows without LIMIT or pagination can make a query inefficient.',
                    ],
                    [
                        'code' => 'sql_inefficient_use_explain_measure',
                        'name_ar' => 'استخدام EXPLAIN للقياس',
                        'name_en' => 'Use EXPLAIN to measure inefficiency',
                        'description_ar' => 'يوضح استخدام EXPLAIN للتحقق من المشكلة وتحسينها.',
                        'claim_ar' => 'يذكر الطالب استخدام EXPLAIN أو execution plan لاكتشاف مصادر عدم الكفاءة.',
                        'claim_en' => 'The student mentions using EXPLAIN or an execution plan to discover inefficiency sources.',
                    ],
                    [
                        'code' => 'sql_inefficient_all_data_no_indexes_claim',
                        'name_ar' => 'ادعاء أن جلب كل البيانات بلا فهارس أفضل',
                        'name_en' => 'Claims all data no indexes is best',
                        'description_ar' => 'ادعاء خاطئ بأن SELECT * والصفوف بلا حدود وغياب الفهارس أفضل للأداء.',
                        'claim_ar' => 'يدعي الطالب أن SELECT * وجلب كل الصفوف دون LIMIT وغياب indexes هي دائمًا أفضل للأداء.',
                        'claim_en' => 'The student claims SELECT *, fetching all rows without LIMIT, and no indexes are always best for performance.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_INEFFICIENT_SELECT_STAR',
                        'name_ar' => 'تجنب الأعمدة غير اللازمة',
                        'description_ar' => 'يذكر أثر SELECT * غير الضرورية.',
                        'max_score' => 1.00,
                        'requires' => ['sql_inefficient_avoid_select_star'],
                        'blocked_by' => ['sql_inefficient_all_data_no_indexes_claim'],
                        'sample_good' => 'Select only name and email needed by the page.',
                        'sample_bad' => 'Always SELECT * regardless of need.',
                        'feedback_pass' => 'وضحت أثر جلب أعمدة غير لازمة.',
                        'feedback_fail' => 'اذكر أن SELECT * قد تجلب بيانات غير مطلوبة وأن اختيار الأعمدة اللازمة أفضل.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_INEFFICIENT_INDEX_SCANS',
                        'name_ar' => 'الفهارس وfull scans',
                        'description_ar' => 'يذكر غياب الفهارس أو full scans كسبب للبطء.',
                        'max_score' => 1.00,
                        'requires' => ['sql_inefficient_missing_indexes_full_scans'],
                        'blocked_by' => ['sql_inefficient_all_data_no_indexes_claim'],
                        'sample_good' => 'Missing index can cause a full scan.',
                        'sample_bad' => 'No indexes always improves performance.',
                        'feedback_pass' => 'وضحت أثر غياب الفهارس أو full scans.',
                        'feedback_fail' => 'اذكر أن غياب index مناسب أو full scan على بيانات كبيرة قد يبطئ الاستعلام.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_INEFFICIENT_JOINS_CONDITIONS',
                        'name_ar' => 'joins وشروط WHERE',
                        'description_ar' => 'يذكر joins أو conditions غير فعالة.',
                        'max_score' => 1.00,
                        'requires' => ['sql_inefficient_joins_filters_conditions'],
                        'blocked_by' => [],
                        'sample_good' => 'Avoid functions on indexed columns when possible.',
                        'sample_bad' => 'Ignore join conditions.',
                        'feedback_pass' => 'وضحت أثر joins أو شروط غير فعالة.',
                        'feedback_fail' => 'اذكر joins أو شروط WHERE أو functions قد تمنع الاستفادة من index.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_INEFFICIENT_UNBOUNDED_RESULTS',
                        'name_ar' => 'النتائج الكبيرة غير المحدودة',
                        'description_ar' => 'يذكر LIMIT أو pagination عند جلب نتائج كبيرة.',
                        'max_score' => 1.00,
                        'requires' => ['sql_inefficient_unbounded_results_pagination'],
                        'blocked_by' => ['sql_inefficient_all_data_no_indexes_claim'],
                        'sample_good' => 'Use LIMIT and pagination for large result sets.',
                        'sample_bad' => 'Return all rows always.',
                        'feedback_pass' => 'وضحت أثر النتائج غير المحدودة.',
                        'feedback_fail' => 'اذكر LIMIT أو pagination عند عرض بيانات كبيرة.',
                        'order' => 4,
                    ],
                    [
                        'code' => 'SQL_INEFFICIENT_EXPLAIN',
                        'name_ar' => 'القياس بخطة التنفيذ',
                        'description_ar' => 'يذكر EXPLAIN لتحديد المشكلة.',
                        'max_score' => 1.00,
                        'requires' => ['sql_inefficient_use_explain_measure'],
                        'blocked_by' => [],
                        'sample_good' => 'Use EXPLAIN to identify expensive operations.',
                        'sample_bad' => 'Do not inspect execution plan.',
                        'feedback_pass' => 'وضحت دور EXPLAIN في اكتشاف عدم الكفاءة.',
                        'feedback_fail' => 'اذكر EXPLAIN أو execution plan لتحديد موضع الكلفة.',
                        'order' => 5,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_INEFFICIENT_CONFLICT_ALL_DATA_NO_INDEXES',
                        'trigger_concept' => 'sql_inefficient_all_data_no_indexes_claim',
                        'feedback_ar' => 'جلب كل الأعمدة وكل الصفوف بلا LIMIT وغياب indexes ليست أفضلية عامة؛ يجب اختيار البيانات اللازمة وقياس خطة التنفيذ.',
                        'blocked_rubrics' => ['SQL_INEFFICIENT_SELECT_STAR', 'SQL_INEFFICIENT_INDEX_SCANS', 'SQL_INEFFICIENT_UNBOUNDED_RESULTS'],
                    ],
                ],
            ],
            [
                'question_text' => 'كيف تصمم schema جيدة لتطبيق Backend يدير مستخدمين وطلبات ومنتجات؟',
                'topic' => 'sql_schema_design',
                'rule_set_code' => 'SQL_SCHEMA_DESIGN_V1',

                'concepts' => [
                    [
                        'code' => 'sql_schema_entities_primary_keys',
                        'name_ar' => 'الكيانات والمفاتيح الأساسية',
                        'name_en' => 'Entities and primary keys',
                        'description_ar' => 'يوضح تحديد جداول users وorders وproducts ومفاتيح أساسية.',
                        'claim_ar' => 'يذكر الطالب جداول مثل users وorders وproducts مع primary key أو id لكل كيان.',
                        'claim_en' => 'The student mentions tables such as users, orders, and products with a primary key or id for each entity.',
                    ],
                    [
                        'code' => 'sql_schema_foreign_keys_relationships',
                        'name_ar' => 'المفاتيح الأجنبية والعلاقات',
                        'name_en' => 'Foreign keys and relationships',
                        'description_ar' => 'يوضح ربط الطلب بالمستخدم وبالمنتجات بمفاتيح أجنبية أو جدول وسيط.',
                        'claim_ar' => 'يذكر الطالب foreign key مثل orders.user_id أو جدول order_items لربط الطلبات بالمنتجات.',
                        'claim_en' => 'The student mentions a foreign key such as orders.user_id or an order_items table to connect orders and products.',
                    ],
                    [
                        'code' => 'sql_schema_normalization_integrity',
                        'name_ar' => 'التطبيع وسلامة البيانات',
                        'name_en' => 'Normalization and integrity',
                        'description_ar' => 'يوضح تقليل التكرار والحفاظ على سلامة العلاقات والقيود.',
                        'claim_ar' => 'يذكر الطالب normalization أو constraints أو منع تكرار البيانات للحفاظ على سلامة البيانات.',
                        'claim_en' => 'The student mentions normalization, constraints, or avoiding duplicated data to preserve data integrity.',
                    ],
                    [
                        'code' => 'sql_schema_indexes_constraints',
                        'name_ar' => 'الفهارس والقيود المناسبة',
                        'name_en' => 'Appropriate indexes and constraints',
                        'description_ar' => 'يوضح إضافة indexes وunique وnot null حيث تكون مناسبة.',
                        'claim_ar' => 'يذكر الطالب indexes على مفاتيح الربط أو قيود مثل UNIQUE وNOT NULL عند الحاجة.',
                        'claim_en' => 'The student mentions indexes on relationship keys or constraints such as UNIQUE and NOT NULL when appropriate.',
                    ],
                    [
                        'code' => 'sql_schema_all_in_one_table_claim',
                        'name_ar' => 'ادعاء وضع كل البيانات في جدول واحد',
                        'name_en' => 'Claims everything belongs in one table',
                        'description_ar' => 'ادعاء خاطئ بأن المستخدمين والطلبات والمنتجات كلها يجب أن تكون في جدول واحد بلا علاقات.',
                        'claim_ar' => 'يدعي الطالب أن users وorders وproducts يجب أن تكون كلها في جدول واحد دون مفاتيح أجنبية أو علاقات.',
                        'claim_en' => 'The student claims users, orders, and products should all be in one table without foreign keys or relationships.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_SCHEMA_ENTITIES_KEYS',
                        'name_ar' => 'تحديد الكيانات والمفاتيح',
                        'description_ar' => 'يحدد الجداول الأساسية ومفاتيحها.',
                        'max_score' => 1.00,
                        'requires' => ['sql_schema_entities_primary_keys'],
                        'blocked_by' => ['sql_schema_all_in_one_table_claim'],
                        'sample_good' => 'users, orders, products each with id.',
                        'sample_bad' => 'Everything in one table.',
                        'feedback_pass' => 'وضحت الكيانات الأساسية ومفاتيحها.',
                        'feedback_fail' => 'اذكر جداول users وorders وproducts ومفتاحًا أساسيًا لكل جدول.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_SCHEMA_RELATIONSHIPS',
                        'name_ar' => 'تصميم العلاقات',
                        'description_ar' => 'يوضح foreign keys أو order_items للعلاقات.',
                        'max_score' => 2.00,
                        'requires' => ['sql_schema_foreign_keys_relationships'],
                        'blocked_by' => ['sql_schema_all_in_one_table_claim'],
                        'sample_good' => 'orders.user_id references users.id and order_items links products.',
                        'sample_bad' => 'No relations or foreign keys.',
                        'feedback_pass' => 'وضحت العلاقات باستخدام المفاتيح الأجنبية أو جدول وسيط.',
                        'feedback_fail' => 'اذكر ربط orders بالمستخدم وربط المنتجات بالطلبات عبر foreign keys أو order_items.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_SCHEMA_NORMALIZATION',
                        'name_ar' => 'التطبيع وسلامة البيانات',
                        'description_ar' => 'يذكر تقليل التكرار والقيود أو سلامة البيانات.',
                        'max_score' => 1.00,
                        'requires' => ['sql_schema_normalization_integrity'],
                        'blocked_by' => [],
                        'sample_good' => 'Normalize user and product data to avoid repetition.',
                        'sample_bad' => 'Repeat every user field in each order.',
                        'feedback_pass' => 'وضحت التطبيع أو سلامة البيانات.',
                        'feedback_fail' => 'اذكر تقليل التكرار أو constraints للحفاظ على سلامة البيانات.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_SCHEMA_INDEXES_CONSTRAINTS',
                        'name_ar' => 'الفهارس والقيود',
                        'description_ar' => 'يذكر indexes أو constraints مناسبة.',
                        'max_score' => 1.00,
                        'requires' => ['sql_schema_indexes_constraints'],
                        'blocked_by' => [],
                        'sample_good' => 'Index foreign keys and use UNIQUE for email.',
                        'sample_bad' => 'No constraints are needed.',
                        'feedback_pass' => 'وضحت إضافة فهارس أو قيود مناسبة.',
                        'feedback_fail' => 'اذكر index على مفاتيح الربط أو constraints مثل UNIQUE وNOT NULL عند الحاجة.',
                        'order' => 4,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_SCHEMA_CONFLICT_ONE_TABLE',
                        'trigger_concept' => 'sql_schema_all_in_one_table_claim',
                        'feedback_ar' => 'لتطبيق يدير مستخدمين وطلبات ومنتجات، من الأفضل فصل الكيانات في جداول مترابطة بمفاتيح أجنبية بدل جدول واحد بلا علاقات.',
                        'blocked_rubrics' => ['SQL_SCHEMA_ENTITIES_KEYS', 'SQL_SCHEMA_RELATIONSHIPS'],
                    ],
                ],
            ],
            [
                'question_text' => 'ما الاعتبارات المهمة عند تحسين استعلامات وتقليل الحمل على قاعدة البيانات في نظام كبير؟',
                'topic' => 'sql_query_optimization_large_system',
                'rule_set_code' => 'SQL_LARGE_SYSTEM_OPTIMIZATION_V1',

                'concepts' => [
                    [
                        'code' => 'sql_optimize_select_needed_filter_paginate',
                        'name_ar' => 'اختيار البيانات والتصفية وpagination',
                        'name_en' => 'Select needed data filter paginate',
                        'description_ar' => 'يوضح اختيار الأعمدة اللازمة والتصفية وpagination لتقليل البيانات.',
                        'claim_ar' => 'يذكر الطالب اختيار الأعمدة اللازمة أو شروط WHERE أو LIMIT وpagination لتقليل البيانات المقروءة والمنقولة.',
                        'claim_en' => 'The student mentions selecting needed columns, WHERE filters, or LIMIT and pagination to reduce data read and transferred.',
                    ],
                    [
                        'code' => 'sql_optimize_use_appropriate_indexes',
                        'name_ar' => 'استخدام indexes مناسبة',
                        'name_en' => 'Use appropriate indexes',
                        'description_ar' => 'يوضح اختيار indexes مناسبة لأنماط الاستعلام.',
                        'claim_ar' => 'يذكر الطالب indexes على أعمدة WHERE أو JOIN أو ORDER BY التي تستخدم فعلًا في الاستعلامات.',
                        'claim_en' => 'The student mentions indexes on WHERE, JOIN, or ORDER BY columns actually used by queries.',
                    ],
                    [
                        'code' => 'sql_optimize_explain_measure',
                        'name_ar' => 'القياس باستخدام EXPLAIN',
                        'name_en' => 'Measure using EXPLAIN',
                        'description_ar' => 'يوضح استخدام EXPLAIN وقياس الأداء قبل وبعد.',
                        'claim_ar' => 'يذكر الطالب EXPLAIN أو execution plan أو قياس latency وrows قبل وبعد التحسين.',
                        'claim_en' => 'The student mentions EXPLAIN, an execution plan, or measuring latency/rows before and after optimization.',
                    ],
                    [
                        'code' => 'sql_optimize_cache_batch_reduce_roundtrips',
                        'name_ar' => 'cache وbatch وتقليل round trips',
                        'name_en' => 'Cache batch reduce round trips',
                        'description_ar' => 'يوضح caching أو batching أو تقليل N+1/round trips.',
                        'claim_ar' => 'يذكر الطالب cache أو batching أو تقليل N+1 queries أو تقليل round trips بين التطبيق وقاعدة البيانات.',
                        'claim_en' => 'The student mentions caching, batching, reducing N+1 queries, or reducing round trips between the app and database.',
                    ],
                    [
                        'code' => 'sql_optimize_scale_operationally',
                        'name_ar' => 'التوسع التشغيلي والمراقبة',
                        'name_en' => 'Operational scaling and monitoring',
                        'description_ar' => 'يوضح monitoring أو read replicas أو connection pooling بحسب الحاجة.',
                        'claim_ar' => 'يذكر الطالب monitoring أو connection pooling أو read replicas أو حدود التشغيل حسب الحمل الفعلي.',
                        'claim_en' => 'The student mentions monitoring, connection pooling, read replicas, or operational limits based on actual load.',
                    ],
                    [
                        'code' => 'sql_optimize_fetch_everything_no_measure_claim',
                        'name_ar' => 'ادعاء جلب كل البيانات بلا قياس',
                        'name_en' => 'Claims fetch everything with no measurement',
                        'description_ar' => 'ادعاء خاطئ بأن جلب كل الأعمدة والصفوف بلا قياس أو فهارس أو cache هو الأفضل دائمًا.',
                        'claim_ar' => 'يدعي الطالب أن جلب كل الأعمدة والصفوف دائمًا دون EXPLAIN أو indexes أو cache هو أفضل طريقة لتقليل الحمل.',
                        'claim_en' => 'The student claims fetching every column and row without EXPLAIN, indexes, or cache is always the best way to reduce load.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_OPTIMIZE_QUERY_SHAPE',
                        'name_ar' => 'تقليل شكل البيانات',
                        'description_ar' => 'يذكر اختيار الأعمدة والتصفية وpagination.',
                        'max_score' => 1.00,
                        'requires' => ['sql_optimize_select_needed_filter_paginate'],
                        'blocked_by' => ['sql_optimize_fetch_everything_no_measure_claim'],
                        'sample_good' => 'Select needed columns and paginate results.',
                        'sample_bad' => 'Always fetch every row and column.',
                        'feedback_pass' => 'وضحت تقليل البيانات المقروءة والمنقولة.',
                        'feedback_fail' => 'اذكر اختيار الأعمدة اللازمة والتصفية أو LIMIT وpagination.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_OPTIMIZE_INDEXES',
                        'name_ar' => 'استخدام indexes مناسبة',
                        'description_ar' => 'يذكر indexes على أعمدة الاستعلامات الفعلية.',
                        'max_score' => 1.00,
                        'requires' => ['sql_optimize_use_appropriate_indexes'],
                        'blocked_by' => ['sql_optimize_fetch_everything_no_measure_claim'],
                        'sample_good' => 'Index a frequent WHERE or JOIN column.',
                        'sample_bad' => 'Avoid every index without analysis.',
                        'feedback_pass' => 'وضحت اختيار indexes مناسبة للاستعلامات.',
                        'feedback_fail' => 'اذكر indexes على أعمدة WHERE أو JOIN أو ORDER BY المستخدمة فعليًا.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_OPTIMIZE_MEASURE',
                        'name_ar' => 'EXPLAIN والقياس',
                        'description_ar' => 'يذكر EXPLAIN وخطة التنفيذ أو المقارنة قبل وبعد.',
                        'max_score' => 1.00,
                        'requires' => ['sql_optimize_explain_measure'],
                        'blocked_by' => ['sql_optimize_fetch_everything_no_measure_claim'],
                        'sample_good' => 'Use EXPLAIN and compare latency before and after.',
                        'sample_bad' => 'Optimize by guessing only.',
                        'feedback_pass' => 'وضحت دور EXPLAIN والقياس قبل وبعد.',
                        'feedback_fail' => 'استخدم EXPLAIN أو قياسًا واضحًا للتحقق من أثر التحسين.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_OPTIMIZE_CACHE_BATCH',
                        'name_ar' => 'تقليل round trips',
                        'description_ar' => 'يذكر cache أو batching أو معالجة N+1.',
                        'max_score' => 1.00,
                        'requires' => ['sql_optimize_cache_batch_reduce_roundtrips'],
                        'blocked_by' => [],
                        'sample_good' => 'Cache repeated reads and batch related queries.',
                        'sample_bad' => 'Make one query per item without considering N+1.',
                        'feedback_pass' => 'وضحت cache أو batching أو تقليل N+1.',
                        'feedback_fail' => 'اذكر cache أو batching أو تقليل N+1/round trips عند الحاجة.',
                        'order' => 4,
                    ],
                    [
                        'code' => 'SQL_OPTIMIZE_OPERATIONS',
                        'name_ar' => 'المراقبة والتوسع التشغيلي',
                        'description_ar' => 'يذكر monitoring أو pooling أو replicas عند الحاجة.',
                        'max_score' => 1.00,
                        'requires' => ['sql_optimize_scale_operationally'],
                        'blocked_by' => [],
                        'sample_good' => 'Monitor load and consider pooling or read replicas.',
                        'sample_bad' => 'Never observe database load.',
                        'feedback_pass' => 'وضحت المراقبة أو قرارات التشغيل حسب الحمل.',
                        'feedback_fail' => 'اذكر monitoring أو connection pooling أو read replicas حسب الحاجة.',
                        'order' => 5,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_OPTIMIZE_CONFLICT_FETCH_ALL_NO_MEASURE',
                        'trigger_concept' => 'sql_optimize_fetch_everything_no_measure_claim',
                        'feedback_ar' => 'تقليل الحمل يتطلب اختيار البيانات اللازمة وقياس خطة التنفيذ واستخدام فهارس أو cache مناسبة؛ جلب كل شيء بلا قياس يزيد الحمل غالبًا.',
                        'blocked_rubrics' => ['SQL_OPTIMIZE_QUERY_SHAPE', 'SQL_OPTIMIZE_INDEXES', 'SQL_OPTIMIZE_MEASURE'],
                    ],
                ],
            ],
            [
                'question_text' => 'كيف توازن بين سهولة تصميم قاعدة البيانات وبين الأداء وقابلية التوسع؟',
                'topic' => 'sql_design_performance_scalability_balance',
                'rule_set_code' => 'SQL_DESIGN_BALANCE_V1',

                'concepts' => [
                    [
                        'code' => 'sql_balance_simple_normalized_maintainable',
                        'name_ar' => 'تصميم بسيط ومنظم',
                        'name_en' => 'Simple normalized maintainable design',
                        'description_ar' => 'يوضح البدء بتصميم واضح ومنظم يقلل التكرار ويسهل الصيانة.',
                        'claim_ar' => 'يذكر الطالب البدء بتصميم بسيط ومنظم أو normalized يحافظ على قابلية الصيانة.',
                        'claim_en' => 'The student mentions starting with a simple, organized, or normalized design that remains maintainable.',
                    ],
                    [
                        'code' => 'sql_balance_measure_real_workload',
                        'name_ar' => 'القياس حسب الحمل الحقيقي',
                        'name_en' => 'Measure real workload',
                        'description_ar' => 'يوضح اتخاذ قرارات الأداء بناء على الاستعلامات والحمل الفعلي.',
                        'claim_ar' => 'يذكر الطالب قياس الاستعلامات أو الحمل الفعلي أو query patterns قبل تطبيق تحسينات الأداء.',
                        'claim_en' => 'The student mentions measuring queries, actual load, or query patterns before applying performance optimizations.',
                    ],
                    [
                        'code' => 'sql_balance_selective_indexes_cache_denormalization',
                        'name_ar' => 'تحسينات انتقائية',
                        'name_en' => 'Selective indexes cache denormalization',
                        'description_ar' => 'يوضح إضافة indexes أو cache أو denormalization فقط عند وجود سبب مقاس.',
                        'claim_ar' => 'يذكر الطالب indexes أو cache أو denormalization بشكل انتقائي بعد قياس الحاجة وليس افتراضيًا لكل شيء.',
                        'claim_en' => 'The student mentions indexes, cache, or denormalization selectively after measuring need rather than by default everywhere.',
                    ],
                    [
                        'code' => 'sql_balance_scaling_strategy',
                        'name_ar' => 'استراتيجية قابلية التوسع',
                        'name_en' => 'Scalability strategy',
                        'description_ar' => 'يوضح حلول توسع مثل replicas أو partitioning أو caching وفق نمط الحمل.',
                        'claim_ar' => 'يذكر الطالب read replicas أو partitioning أو caching أو scaling بحسب نمو البيانات ونمط القراءة والكتابة.',
                        'claim_en' => 'The student mentions read replicas, partitioning, caching, or scaling based on data growth and read/write patterns.',
                    ],
                    [
                        'code' => 'sql_balance_constraints_migrations_observability',
                        'name_ar' => 'القيود والتغييرات والمراقبة',
                        'name_en' => 'Constraints migrations observability',
                        'description_ar' => 'يوضح الحفاظ على constraints وmigrations ومراقبة الأداء عند التطوير.',
                        'claim_ar' => 'يذكر الطالب constraints أو migrations أو monitoring للحفاظ على سلامة التصميم أثناء التوسع.',
                        'claim_en' => 'The student mentions constraints, migrations, or monitoring to preserve design integrity during scaling.',
                    ],
                    [
                        'code' => 'sql_balance_no_measure_single_rule_claim',
                        'name_ar' => 'ادعاء قاعدة واحدة بلا قياس',
                        'name_en' => 'Claims one rule no measurement',
                        'description_ar' => 'ادعاء خاطئ بأن نفس التصميم أو أعلى تطبيع دائمًا هو الأفضل دون قياس أو تحسينات.',
                        'claim_ar' => 'يدعي الطالب أن قاعدة واحدة مثل أقصى تطبيع دائمًا أو عدم استخدام indexes وcache أبدًا هي الأفضل دون قياس الحمل.',
                        'claim_en' => 'The student claims one rule such as always maximum normalization or never using indexes/cache is best without measuring workload.',
                    ],
                ],

                'rubrics' => [
                    [
                        'code' => 'SQL_BALANCE_MAINTAINABLE_DESIGN',
                        'name_ar' => 'تصميم واضح وقابل للصيانة',
                        'description_ar' => 'يذكر تصميمًا بسيطًا ومنظمًا أو normalized.',
                        'max_score' => 1.00,
                        'requires' => ['sql_balance_simple_normalized_maintainable'],
                        'blocked_by' => ['sql_balance_no_measure_single_rule_claim'],
                        'sample_good' => 'Start with a clean normalized schema.',
                        'sample_bad' => 'Use a rigid design regardless of maintenance.',
                        'feedback_pass' => 'وضحت أهمية تصميم واضح وقابل للصيانة.',
                        'feedback_fail' => 'اذكر البدء بتصميم منظم أو normalized يسهل صيانته.',
                        'order' => 1,
                    ],
                    [
                        'code' => 'SQL_BALANCE_MEASURE_WORKLOAD',
                        'name_ar' => 'القياس قبل التحسين',
                        'description_ar' => 'يذكر قياس الحمل أو الاستعلامات قبل اتخاذ قرار.',
                        'max_score' => 1.00,
                        'requires' => ['sql_balance_measure_real_workload'],
                        'blocked_by' => ['sql_balance_no_measure_single_rule_claim'],
                        'sample_good' => 'Measure slow queries and workload first.',
                        'sample_bad' => 'Optimize without any measurement.',
                        'feedback_pass' => 'وضحت أن التحسين يعتمد على القياس والحمل الحقيقي.',
                        'feedback_fail' => 'اذكر قياس الاستعلامات أو الحمل الفعلي قبل تحسين الأداء.',
                        'order' => 2,
                    ],
                    [
                        'code' => 'SQL_BALANCE_SELECTIVE_OPTIMIZATION',
                        'name_ar' => 'تحسينات انتقائية',
                        'description_ar' => 'يذكر indexes أو cache أو denormalization بصورة انتقائية.',
                        'max_score' => 1.00,
                        'requires' => ['sql_balance_selective_indexes_cache_denormalization'],
                        'blocked_by' => [],
                        'sample_good' => 'Add an index or cache only when measured need exists.',
                        'sample_bad' => 'Apply every optimization by default.',
                        'feedback_pass' => 'وضحت استخدام التحسينات بصورة انتقائية.',
                        'feedback_fail' => 'اذكر indexes أو cache أو denormalization عندما يثبت القياس الحاجة إليها.',
                        'order' => 3,
                    ],
                    [
                        'code' => 'SQL_BALANCE_SCALABILITY',
                        'name_ar' => 'قابلية التوسع',
                        'description_ar' => 'يذكر استراتيجية توسع مرتبطة بنمط الحمل.',
                        'max_score' => 1.00,
                        'requires' => ['sql_balance_scaling_strategy'],
                        'blocked_by' => [],
                        'sample_good' => 'Use read replicas or partitioning if workload needs them.',
                        'sample_bad' => 'Never plan for growth.',
                        'feedback_pass' => 'وضحت أمثلة لاستراتيجية قابلية التوسع.',
                        'feedback_fail' => 'اذكر caching أو replicas أو partitioning وفق نمو البيانات والحمل.',
                        'order' => 4,
                    ],
                    [
                        'code' => 'SQL_BALANCE_GOVERNANCE',
                        'name_ar' => 'سلامة التطوير والمراقبة',
                        'description_ar' => 'يذكر constraints أو migrations أو monitoring.',
                        'max_score' => 1.00,
                        'requires' => ['sql_balance_constraints_migrations_observability'],
                        'blocked_by' => [],
                        'sample_good' => 'Use migrations and monitoring while evolving schema.',
                        'sample_bad' => 'Change schema manually with no constraints.',
                        'feedback_pass' => 'وضحت دور القيود أو migrations أو monitoring.',
                        'feedback_fail' => 'اذكر constraints أو migrations أو monitoring لحماية التصميم أثناء التوسع.',
                        'order' => 5,
                    ],
                ],

                'contradictions' => [
                    [
                        'code' => 'SQL_BALANCE_CONFLICT_ONE_RULE_NO_MEASURE',
                        'trigger_concept' => 'sql_balance_no_measure_single_rule_claim',
                        'feedback_ar' => 'لا توجد قاعدة أداء واحدة تناسب كل الأنظمة؛ ابدأ بتصميم واضح ثم استخدم القياس لتقرر متى تحتاج index أو cache أو denormalization أو توسعًا إضافيًا.',
                        'blocked_rubrics' => ['SQL_BALANCE_MAINTAINABLE_DESIGN', 'SQL_BALANCE_MEASURE_WORKLOAD'],
                    ],
                ],
            ],
        ];
    }
}
