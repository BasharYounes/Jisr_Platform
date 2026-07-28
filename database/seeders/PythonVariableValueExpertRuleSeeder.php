<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class PythonVariableValueExpertRuleSeeder extends Seeder
{
    use ResolvesExpertQuestionsByTopic;

    private const SKILL_NAME = 'Python';

    private const TOPIC = 'variables';

    private const RULE_SET_CODE = 'PY_VARIABLE_VALUE_V1';

    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();

            $question = $this->resolveExpertQuestionByTopic(
                skillName: self::SKILL_NAME,
                topic: self::TOPIC,
            );

            $questionId = (int) $question->QuestionID;

            $attemptCount = DB::table('assessment_question_attempts')
                ->where('QuestionID', $questionId)
                ->count();

            if ($attemptCount > 0) {
                throw new RuntimeException(
                    'Cannot seed Python variable/value rules because this question '
                    . 'already has assessment attempts. Create a new Rule Set version instead.'
                );
            }

            $ruleSetCodeUsedElsewhere = DB::table('assessment_rule_sets')
                ->where('RuleSetCode', self::RULE_SET_CODE)
                ->where('QuestionID', '<>', $questionId)
                ->exists();

            if ($ruleSetCodeUsedElsewhere) {
                throw new RuntimeException(
                    'PY_VARIABLE_VALUE_V1 is already assigned to another question.'
                );
            }

            $this->clearQuestionStructure($questionId);

            DB::table('question_bank')
                ->where('QuestionID', $questionId)
                ->update([
                    'QuestionText' =>
                        'ما الفرق بين المتغير والقيمة في Python؟ أعط مثالًا بسيطًا.',
                    'Topic' => self::TOPIC,
                    'EvaluationEngine' => 'expert_rules',
                    'RuleSetVersion' => 'v1',
                    'IsExpertReady' => false,
                    'updated_at' => $now,
                ]);

            $concepts = $this->concepts();
            $conceptIds = [];

            foreach ($concepts as $concept) {
                $conceptIds[$concept['code']] = $this->ensureConcept(
                    concept: $concept,
                    now: $now,
                );
            }

            $rubrics = $this->rubrics();
            $rubricIds = [];

            foreach ($rubrics as $rubric) {
                $rubricIds[$rubric['code']] = DB::table('question_rubrics')
                    ->insertGetId([
                        'QuestionID' => $questionId,
                        'CriterionCode' => $rubric['code'],
                        'CriterionName' => $rubric['name_ar'],
                        'CriterionDescription' => $rubric['description_ar'],
                        'MaxScore' => $rubric['max_score'],
                        'Weight' => 1.00,
                        'KeywordsJson' => $this->json($rubric['keywords']),
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
                    'RuleSetCode' => self::RULE_SET_CODE,
                    'Version' => 'v1',
                    'Status' => 'active',
                    'CreatedByUserId' => null,
                    'ActivatedAt' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'RuleSetID');

            foreach ($this->rules() as $rule) {
                DB::table('criterion_rules')->insert([
                    'RuleSetID' => $ruleSetId,
                    'QuestionRubricID' => $rubricIds[$rule['rubric_code']],
                    'RuleCode' => $rule['code'],
                    'RuleType' => $rule['type'],
                    'Priority' => $rule['priority'],
                    'ConditionsJson' => $this->json([
                        'all' => array_map(
                            fn (string $concept): array => [
                                'concept' => $concept,
                                'expected' => true,
                                'not_negated' => true,
                            ],
                            $rule['requires'],
                        ),
                        'none' => array_map(
                            fn (string $concept): array => [
                                'concept' => $concept,
                                'expected' => true,
                            ],
                            $rule['blocked_by'],
                        ),
                    ]),
                    'AwardScore' => $rule['award_score'],
                    'FeedbackTemplate' => $rule['feedback'],
                    'IsActive' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($this->contradictions() as $contradiction) {
                $contradictionRuleId = DB::table(
                    'assessment_contradiction_rules'
                )->insertGetId([
                    'RuleSetID' => $ruleSetId,
                    'TriggerConceptID' =>
                        $conceptIds[$contradiction['trigger_concept']],
                    'Code' => $contradiction['code'],
                    'Severity' => 'block_criterion',
                    'FeedbackAr' => $contradiction['feedback_ar'],
                    'IsActive' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'ContradictionRuleID');

                foreach ($contradiction['blocked_rubrics'] as $rubricCode) {
                    DB::table('assessment_contradiction_rule_rubrics')->insert([
                        'ContradictionRuleID' => $contradictionRuleId,
                        'QuestionRubricID' => $rubricIds[$rubricCode],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });

        if ($this->command) {
            $this->command->info(
                'Python variable/value Expert Rules data was seeded successfully.'
            );
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
                        $contradictionRuleIds,
                    )
                    ->delete();

                DB::table('assessment_contradiction_rules')
                    ->whereIn(
                        'ContradictionRuleID',
                        $contradictionRuleIds,
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

    private function ensureConcept(array $concept, $now): int
    {
        $existingId = DB::table('assessment_concepts')
            ->where('ConceptCode', $concept['code'])
            ->value('ConceptID');

        $values = [
            'NameAr' => $concept['name_ar'],
            'NameEn' => $concept['name_en'],
            'Description' => $concept['description_ar'],
            'ClaimAr' => $concept['claim_ar'],
            'ClaimEn' => $concept['claim_en'],
            'IsActive' => true,
            'updated_at' => $now,
        ];

        if ($existingId) {
            DB::table('assessment_concepts')
                ->where('ConceptID', $existingId)
                ->update($values);

            return (int) $existingId;
        }

        $values['ConceptCode'] = $concept['code'];
        $values['created_at'] = $now;

        return (int) DB::table('assessment_concepts')
            ->insertGetId($values, 'ConceptID');
    }

    private function json(array $data): string
    {
        try {
            return json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $exception) {
            throw new LogicException(
                'Unable to encode Expert Rules JSON.',
                previous: $exception,
            );
        }
    }

    private function concepts(): array
    {
        return [
            [
                'code' => 'variable_is_identifier_or_reference',
                'name_ar' => 'المتغير اسم أو معرّف يشير إلى قيمة',
                'name_en' => 'Variable is an identifier or reference',
                'description_ar' =>
                    'يوضح أن المتغير اسم أو معرّف مرتبط بقيمة أو كائن.',
                'claim_ar' =>
                    'الطالب يذكر أن المتغير اسم أو معرّف يشير إلى قيمة أو كائن.',
                'claim_en' =>
                    'The student states that a variable is a name or identifier that refers to a value or object.',
            ],
            [
                'code' => 'variable_holds_value_simplified',
                'name_ar' => 'المتغير يحمل بيانات بصياغة مبسطة',
                'name_en' => 'Variable holds a value in simplified form',
                'description_ar' =>
                    'صياغة مبسطة مقبولة جزئيًا لتعريف المتغير.',
                'claim_ar' =>
                    'الطالب يذكر أن المتغير يحمل أو يخزن بيانات أو قيمة دون تعريف أدق.',
                'claim_en' =>
                    'The student states, less precisely, that a variable holds or stores data or a value.',
            ],
            [
                'code' => 'value_is_data_or_literal',
                'name_ar' => 'القيمة تمثل البيانات أو literal',
                'name_en' => 'Value is data or a literal',
                'description_ar' =>
                    'يوضح أن القيمة هي البيانات نفسها مثل رقم أو نص.',
                'claim_ar' =>
                    'الطالب يذكر أن القيمة تمثل البيانات نفسها مثل رقم أو نص أو literal.',
                'claim_en' =>
                    'The student states that a value is data itself, such as a number, string, or literal.',
            ],
            [
                'code' => 'assignment_binds_value_to_variable',
                'name_ar' => 'الإسناد يربط القيمة بالمتغير',
                'name_en' => 'Assignment binds a value to a variable',
                'description_ar' =>
                    'يوضح أن x = 5 تربط القيمة 5 بالاسم x.',
                'claim_ar' =>
                    'الطالب يوضح أن عملية الإسناد تربط قيمة باسم متغير، مثل x = 5.',
                'claim_en' =>
                    'The student explains that assignment binds a value to a variable name, such as x = 5.',
            ],
            [
                'code' => 'valid_python_assignment_example',
                'name_ar' => 'مثال إسناد Python صحيح',
                'name_en' => 'Valid Python assignment example',
                'description_ar' =>
                    'يقدم مثال إسناد صحيحًا مثل x = 5.',
                'claim_ar' =>
                    'الطالب يقدم مثال Python صحيحًا يربط قيمة بمتغير، مثل x = 5.',
                'claim_en' =>
                    'The student provides a valid Python assignment example such as x = 5.',
            ],
            [
                'code' => 'variable_value_equivalence_claim',
                'name_ar' => 'ادعاء أن المتغير والقيمة شيء واحد',
                'name_en' => 'Variable and value are the same claim',
                'description_ar' =>
                    'خلط صريح بين مفهوم المتغير والقيمة.',
                'claim_ar' =>
                    'الطالب يدعي أن المتغير والقيمة شيء واحد أو لا فرق بينهما.',
                'claim_en' =>
                    'The student claims that a variable and a value are the same thing.',
            ],
            [
                'code' => 'assignment_roles_reversed',
                'name_ar' => 'عكس أدوار المتغير والقيمة',
                'name_en' => 'Assignment roles are reversed',
                'description_ar' =>
                    'مثل اعتبار 5 متغيرًا وx قيمة في x = 5.',
                'claim_ar' =>
                    'الطالب يقول إن 5 هو المتغير وx هي القيمة في x = 5.',
                'claim_en' =>
                    'The student says that 5 is the variable and x is the value in x = 5.',
            ],
            [
                'code' => 'variable_cannot_refer_to_value_claim',
                'name_ar' => 'ادعاء أن المتغير لا يشير إلى قيمة',
                'name_en' => 'Variable cannot refer to a value claim',
                'description_ar' =>
                    'تناقض مع تعريف المتغير الصحيح.',
                'claim_ar' =>
                    'الطالب يدعي أن المتغير لا يشير أو لا يرتبط بقيمة.',
                'claim_en' =>
                    'The student claims that a variable does not refer to or relate to a value.',
            ],
            [
                'code' => 'value_is_variable_name_claim',
                'name_ar' => 'ادعاء أن القيمة هي اسم المتغير',
                'name_en' => 'Value is the variable name claim',
                'description_ar' =>
                    'تناقض مع تعريف القيمة الصحيح.',
                'claim_ar' =>
                    'الطالب يدعي أن القيمة هي اسم المتغير.',
                'claim_en' =>
                    'The student claims that the value is the variable name.',
            ],
        ];
    }

    private function rubrics(): array
    {
        return [
            [
                'code' => 'PY_VAR_DEFINE_VARIABLE',
                'name_ar' => 'تعريف المتغير',
                'description_ar' =>
                    'يوضح أن المتغير اسم أو معرّف يشير إلى قيمة أو كائن.',
                'max_score' => 2.00,
                'keywords' => [
                    'variable_is_identifier_or_reference',
                    'variable_holds_value_simplified',
                ],
                'sample_good' => 'المتغير اسم يشير إلى قيمة أو كائن.',
                'sample_bad' => 'المتغير والقيمة شيء واحد.',
                'feedback_pass' => 'شرحت مفهوم المتغير بشكل صحيح.',
                'feedback_fail' =>
                    'وضح أن المتغير اسم أو معرّف يرتبط بقيمة أو كائن.',
                'order' => 1,
            ],
            [
                'code' => 'PY_VAR_DEFINE_VALUE',
                'name_ar' => 'تعريف القيمة',
                'description_ar' =>
                    'يوضح أن القيمة تمثل البيانات مثل رقم أو نص أو كائن.',
                'max_score' => 1.00,
                'keywords' => ['value_is_data_or_literal'],
                'sample_good' => 'القيمة هي البيانات مثل 5 أو النص Ali.',
                'sample_bad' => 'القيمة هي اسم المتغير.',
                'feedback_pass' =>
                    'وضحت أن القيمة تمثل البيانات بشكل صحيح.',
                'feedback_fail' =>
                    'وضح أن القيمة تمثل البيانات مثل رقم أو نص.',
                'order' => 2,
            ],
            [
                'code' => 'PY_VAR_EXPLAIN_ASSIGNMENT',
                'name_ar' => 'توضيح العلاقة بين المتغير والقيمة',
                'description_ar' =>
                    'يوضح أن الإسناد يربط قيمة باسم متغير، مثل x = 5.',
                'max_score' => 1.00,
                'keywords' => ['assignment_binds_value_to_variable'],
                'sample_good' => 'في x = 5، x متغير و5 قيمة.',
                'sample_bad' => 'في x = 5، الرقم 5 هو المتغير.',
                'feedback_pass' =>
                    'وضحت العلاقة بين المتغير والقيمة بصورة صحيحة.',
                'feedback_fail' =>
                    'وضح أن x متغير و5 قيمة في المثال x = 5.',
                'order' => 3,
            ],
            [
                'code' => 'PY_VAR_VALID_EXAMPLE',
                'name_ar' => 'مثال Python صحيح',
                'description_ar' => 'يقدم مثال إسناد Python صحيحًا.',
                'max_score' => 1.00,
                'keywords' => ['valid_python_assignment_example'],
                'sample_good' => 'x = 5',
                'sample_bad' => '5 = x',
                'feedback_pass' => 'قدمت مثال Python صحيحًا.',
                'feedback_fail' =>
                    'أضف مثال Python صحيحًا مثل: x = 5.',
                'order' => 4,
            ],
        ];
    }

    private function rules(): array
    {
        return [
            [
                'rubric_code' => 'PY_VAR_DEFINE_VARIABLE',
                'code' => 'PY_VAR_01_FULL',
                'type' => 'award_full',
                'priority' => 10,
                'award_score' => 2.00,
                'feedback' =>
                    'شرحت أن المتغير اسم أو معرّف يرتبط بقيمة.',
                'requires' => ['variable_is_identifier_or_reference'],
                'blocked_by' => [
                    'variable_value_equivalence_claim',
                    'variable_cannot_refer_to_value_claim',
                ],
            ],
            [
                'rubric_code' => 'PY_VAR_DEFINE_VARIABLE',
                'code' => 'PY_VAR_02_PARTIAL',
                'type' => 'award_partial',
                'priority' => 20,
                'award_score' => 1.00,
                'feedback' =>
                    'ذكرت أن المتغير يحمل بيانات، لكن التعريف يحتاج دقة أكبر.',
                'requires' => ['variable_holds_value_simplified'],
                'blocked_by' => [
                    'variable_is_identifier_or_reference',
                    'variable_value_equivalence_claim',
                    'variable_cannot_refer_to_value_claim',
                ],
            ],
            [
                'rubric_code' => 'PY_VAR_DEFINE_VALUE',
                'code' => 'PY_VAL_01_FULL',
                'type' => 'award_full',
                'priority' => 10,
                'award_score' => 1.00,
                'feedback' =>
                    'وضحت أن القيمة تمثل البيانات مثل رقم أو نص.',
                'requires' => ['value_is_data_or_literal'],
                'blocked_by' => [
                    'variable_value_equivalence_claim',
                    'value_is_variable_name_claim',
                ],
            ],
            [
                'rubric_code' => 'PY_VAR_EXPLAIN_ASSIGNMENT',
                'code' => 'PY_REL_01_FULL',
                'type' => 'award_full',
                'priority' => 10,
                'award_score' => 1.00,
                'feedback' =>
                    'وضحت أن x هو المتغير و5 هي القيمة في عملية الإسناد.',
                'requires' => ['assignment_binds_value_to_variable'],
                'blocked_by' => [
                    'variable_value_equivalence_claim',
                    'assignment_roles_reversed',
                    'value_is_variable_name_claim',
                ],
            ],
            [
                'rubric_code' => 'PY_VAR_VALID_EXAMPLE',
                'code' => 'PY_EX_01_FULL',
                'type' => 'award_full',
                'priority' => 10,
                'award_score' => 1.00,
                'feedback' => 'قدمت مثال إسناد Python صحيحًا.',
                'requires' => ['valid_python_assignment_example'],
                'blocked_by' => ['assignment_roles_reversed'],
            ],
        ];
    }

    private function contradictions(): array
    {
        return [
            [
                'code' => 'PY_VAR_CONFLICT_SAME',
                'trigger_concept' => 'variable_value_equivalence_claim',
                'feedback_ar' =>
                    'يوجد خلط بين المتغير والقيمة؛ المتغير اسم أو معرّف، بينما القيمة هي البيانات المرتبطة به.',
                'blocked_rubrics' => [
                    'PY_VAR_DEFINE_VARIABLE',
                    'PY_VAR_DEFINE_VALUE',
                    'PY_VAR_EXPLAIN_ASSIGNMENT',
                ],
            ],
            [
                'code' => 'PY_VAR_CONFLICT_REVERSED_ASSIGNMENT',
                'trigger_concept' => 'assignment_roles_reversed',
                'feedback_ar' =>
                    'في x = 5، يكون x هو المتغير و5 هي القيمة.',
                'blocked_rubrics' => [
                    'PY_VAR_EXPLAIN_ASSIGNMENT',
                    'PY_VAR_VALID_EXAMPLE',
                ],
            ],
            [
                'code' => 'PY_VAR_CONFLICT_NO_REFERENCE',
                'trigger_concept' => 'variable_cannot_refer_to_value_claim',
                'feedback_ar' =>
                    'المتغير يمكن أن يشير إلى قيمة أو كائن؛ لا يصح القول إنه لا يرتبط بقيمة.',
                'blocked_rubrics' => ['PY_VAR_DEFINE_VARIABLE'],
            ],
            [
                'code' => 'PY_VAR_CONFLICT_VALUE_NAME',
                'trigger_concept' => 'value_is_variable_name_claim',
                'feedback_ar' =>
                    'القيمة ليست اسم المتغير؛ القيمة هي البيانات، واسم المتغير هو المعرّف مثل x.',
                'blocked_rubrics' => [
                    'PY_VAR_DEFINE_VALUE',
                    'PY_VAR_EXPLAIN_ASSIGNMENT',
                ],
            ],
        ];
    }
}
