<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PythonVariableValueDetectionPolicySeeder extends Seeder
{
    private const RULE_SET_CODE = 'PY_VARIABLE_VALUE_V1';

    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();

            $ruleSetExists = DB::table('assessment_rule_sets')
                ->where('RuleSetCode', self::RULE_SET_CODE)
                ->exists();

            if (! $ruleSetExists) {
                throw new RuntimeException(
                    'Run PythonVariableValueExpertRuleSeeder first.'
                );
            }

            $requiredConceptCodes = [
                'variable_is_identifier_or_reference',
                'variable_holds_value_simplified',
                'value_is_data_or_literal',
                'assignment_binds_value_to_variable',
                'valid_python_assignment_example',
                'variable_value_equivalence_claim',
                'assignment_roles_reversed',
                'variable_cannot_refer_to_value_claim',
                'value_is_variable_name_claim',
            ];

            $conceptIds = DB::table('assessment_concepts')
                ->whereIn('ConceptCode', $requiredConceptCodes)
                ->pluck('ConceptID', 'ConceptCode')
                ->map(fn ($id) => (int) $id)
                ->all();

            $missingConceptCodes = array_values(
                array_diff(
                    $requiredConceptCodes,
                    array_keys($conceptIds)
                )
            );

            if (! empty($missingConceptCodes)) {
                throw new RuntimeException(
                    'Missing required concepts: '
                    .implode(', ', $missingConceptCodes)
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 1. Positive concepts: Alias + guarded Semantic Similarity
            |--------------------------------------------------------------------------
            */

            $semanticPolicies = [
                'variable_is_identifier_or_reference' => [
                    'ar' => [
                        'anchor_terms' => [
                            'المتغير',
                            'معرف',
                            'اسم',
                        ],
                    ],
                    'en' => [
                        'anchor_terms' => [
                            'variable',
                            'identifier',
                            'name',
                        ],
                    ],
                ],

                'variable_holds_value_simplified' => [
                    'ar' => [
                        'anchor_terms' => [
                            'المتغير',
                            'بيانات',
                            'قيمة',
                        ],
                    ],
                    'en' => [
                        'anchor_terms' => [
                            'variable',
                            'data',
                            'value',
                        ],
                    ],
                ],

                'value_is_data_or_literal' => [
                    'ar' => [
                        'anchor_terms' => [
                            'القيمة',
                            'بيانات',
                            'رقم',
                            'نص',
                        ],
                    ],
                    'en' => [
                        'anchor_terms' => [
                            'value',
                            'data',
                            'number',
                            'string',
                            'literal',
                        ],
                    ],
                ],

                'assignment_binds_value_to_variable' => [
                    'ar' => [
                        'anchor_terms' => [
                            'إسناد',
                            'اسناد',
                            'المتغير',
                            'القيمة',
                        ],
                    ],
                    'en' => [
                        'anchor_terms' => [
                            'assignment',
                            'variable',
                            'value',
                            'bound',
                        ],
                    ],
                ],
            ];

            foreach ($semanticPolicies as $conceptCode => $byLanguage) {
                $this->ensurePolicy(
                    conceptId: $conceptIds[$conceptCode],
                    detectorType: 'alias',
                    language: 'any',
                    configuration: [],
                    now: $now
                );

                foreach ($byLanguage as $language => $configuration) {
                    $this->ensurePolicy(
                        conceptId: $conceptIds[$conceptCode],
                        detectorType: 'semantic',
                        language: $language,
                        configuration: $configuration,
                        now: $now
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Python code example: Code pattern only
            |--------------------------------------------------------------------------
            |
            | Laravel will later send this key to Python.
            | Python will map it to a safe internal pattern,
            | never to a free regex from the database.
            */

            $this->ensurePolicy(
                conceptId: $conceptIds[
                    'valid_python_assignment_example'
                ],
                detectorType: 'code_pattern',
                language: 'any',
                configuration: [
                    'pattern_key' => 'python_assignment_literal',
                ],
                now: $now
            );

            /*
            |--------------------------------------------------------------------------
            | 3. Contradictions: Alias only, never semantic similarity
            |--------------------------------------------------------------------------
            */

            $contradictionConceptCodes = [
                'variable_value_equivalence_claim',
                'assignment_roles_reversed',
                'variable_cannot_refer_to_value_claim',
                'value_is_variable_name_claim',
            ];

            foreach ($contradictionConceptCodes as $conceptCode) {
                $this->ensurePolicy(
                    conceptId: $conceptIds[$conceptCode],
                    detectorType: 'alias',
                    language: 'any',
                    configuration: [],
                    now: $now
                );
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Apply approved pilot semantic thresholds
            |--------------------------------------------------------------------------
            |
            | Arabic: 0.60
            | English: 0.80
            */

            $semanticConceptIds = array_map(
                fn (string $conceptCode) => $conceptIds[$conceptCode],
                array_keys($semanticPolicies)
            );

            DB::table('assessment_concept_examples')
                ->whereIn('ConceptID', $semanticConceptIds)
                ->where('IsActive', true)
                ->where('IsPositive', true)
                ->where('Language', 'ar')
                ->update([
                    'MinimumSimilarity' => '0.6000',
                    'updated_at' => $now,
                ]);

            DB::table('assessment_concept_examples')
                ->whereIn('ConceptID', $semanticConceptIds)
                ->where('IsActive', true)
                ->where('IsPositive', true)
                ->where('Language', 'en')
                ->update([
                    'MinimumSimilarity' => '0.8000',
                    'updated_at' => $now,
                ]);

            if ($this->command) {
                $this->command->info(
                    'Python variable/value detection policies seeded successfully.'
                );
            }
        });
    }

    private function ensurePolicy(
        int $conceptId,
        string $detectorType,
        string $language,
        array $configuration,
        $now
    ): void {
        $query = DB::table(
            'assessment_concept_detection_policies'
        )
            ->where('ConceptID', $conceptId)
            ->where('DetectorType', $detectorType)
            ->where('Language', $language);

        $existingPolicyId = $query->value('DetectionPolicyID');

        $payload = [
            'ConfigurationJson' => json_encode(
                $configuration,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            'IsActive' => true,
            'updated_at' => $now,
        ];

        if ($existingPolicyId) {
            $query->update($payload);

            return;
        }

        DB::table(
            'assessment_concept_detection_policies'
        )->insert([
            'ConceptID' => $conceptId,
            'DetectorType' => $detectorType,
            'Language' => $language,
            ...$payload,
            'created_at' => $now,
        ]);
    }
}
