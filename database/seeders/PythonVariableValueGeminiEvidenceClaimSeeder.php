<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PythonVariableValueGeminiEvidenceClaimSeeder extends Seeder
{
    public function run(): void
    {
        $claims = [
            [
                'ConceptCode' => 'variable_is_identifier_or_reference',
                'ClaimAr' => 'الطالب يذكر أن المتغير اسم أو معرّف يشير إلى قيمة أو كائن أو بيانات.',
                'ClaimEn' => 'The student states that a variable is a name or identifier that refers to a value, object, or data.',
            ],
            [
                'ConceptCode' => 'variable_holds_value_simplified',
                'ClaimAr' => 'الطالب يذكر أن المتغير يحتفظ أو يخزن أو يحمل بيانات أو قيمة أو معلومة.',
                'ClaimEn' => 'The student states that a variable stores, holds, keeps, or contains data, a value, or information.',
            ],
            [
                'ConceptCode' => 'value_is_data_or_literal',
                'ClaimAr' => 'الطالب يذكر أن القيمة تمثل بيانات أو قيمة حرفية، مثل رقم أو نص أو قيمة منطقية.',
                'ClaimEn' => 'The student states that a value represents data or a literal, such as a number, string, or Boolean value.',
            ],
            [
                'ConceptCode' => 'assignment_binds_value_to_variable',
                'ClaimAr' => 'الطالب يشرح أن عملية الإسناد في Python تربط قيمة بمتغير، مثل x = 5 حيث x متغير و5 قيمة.',
                'ClaimEn' => 'The student explains that assignment in Python binds a value to a variable, such as x = 5 where x is the variable and 5 is the value.',
            ],
            [
                'ConceptCode' => 'valid_python_assignment_example',
                'ClaimAr' => 'إجابة الطالب تتضمن مثال إسناد صحيحًا في Python يربط اسم متغير بقيمة، مثل x = 5.',
                'ClaimEn' => 'The student answer contains a valid Python assignment example that binds a variable name to a value, such as x = 5.',
            ],
            [
                'ConceptCode' => 'variable_value_equivalence_claim',
                'ClaimAr' => 'الطالب يذكر أن المتغير والقيمة هما الشيء نفسه تمامًا.',
                'ClaimEn' => 'The student states that a variable and a value are exactly the same thing.',
            ],
            [
                'ConceptCode' => 'assignment_roles_reversed',
                'ClaimAr' => 'الطالب يذكر أن القيمة هي اسم المتغير أو أن اسم المتغير هو القيمة في إسناد مثل x = 5.',
                'ClaimEn' => 'The student states that the value is the variable name or that the variable name is the value in an assignment such as x = 5.',
            ],
            [
                'ConceptCode' => 'variable_cannot_refer_to_value_claim',
                'ClaimAr' => 'الطالب ينفي أن المتغير يمكن أن يرتبط بقيمة أو يشير إليها.',
                'ClaimEn' => 'The student denies that a variable can be linked to or refer to a value.',
            ],
            [
                'ConceptCode' => 'value_is_variable_name_claim',
                'ClaimAr' => 'الطالب يذكر أن القيمة هي اسم المتغير.',
                'ClaimEn' => 'The student states that a value is the name of the variable.',
            ],
        ];

        DB::transaction(function () use ($claims): void {
            foreach ($claims as $claim) {
                $conceptExists = DB::table('assessment_concepts')
                    ->where(
                        'ConceptCode',
                        $claim['ConceptCode']
                    )
                    ->exists();

                if (! $conceptExists) {
                    throw new RuntimeException(
                        "Concept not found: {$claim['ConceptCode']}"
                    );
                }

                DB::table('assessment_concepts')
                    ->where(
                        'ConceptCode',
                        $claim['ConceptCode']
                    )
                    ->update([
                        'ClaimAr' => $claim['ClaimAr'],
                        'ClaimEn' => $claim['ClaimEn'],
                    ]);
            }
        });
    }
}

/*




$attemptId = 131;

$attempt = \App\Models\AssessmentQuestionAttempt::query() ->with([ 'answer', 'evaluationRuns.evidence.concept', 'assessmentSkillSession',])->findOrFail($attemptId);

$run = $attempt->evaluationRuns->sortByDesc('EvaluationRunID')->first();

[
    'answer_saved' => $attempt->answer !== null,

    'attempt_engine' => $attempt->EvaluationEngine,
    'attempt_status' => $attempt->EvaluationStatus,
    'llm_status_compatibility' => $attempt->LlmEvaluationStatus,

    'raw_score' => $attempt->RawScore,
    'normalized_score' => $attempt->NormalizedScore,

    'evaluation_mode' => data_get(
        $attempt->EvaluationJson,
        'evaluation_mode'
    ),

    'evaluation_run_id' => $run?->EvaluationRunID,
    'run_status' => $run?->Status,
    'run_score' => $run?->TotalScore,
    'evidence_count' => $run?->evidence->count(),

    'evidence' => $run?->evidence->map(fn ($evidence) => [ 'concept' => $evidence->concept?->ConceptCode, 'text' => $evidence->EvidenceText,'is_contradiction' => $evidence->IsContradiction,])->values()->all(),];



$sessionId = 45;
$attemptId = 131;

\App\Models\QuestionBank::query()->whereKey(117)->update(['IsExpertReady' => false,]);

\App\Models\AssessmentSession::query()->findOrFail($sessionId)->delete();

['question_expert_ready' => \App\Models\QuestionBank::find(117)->IsExpertReady,'session_exists' => \App\Models\AssessmentSession::query()->whereKey($sessionId)->exists(),'attempt_exists' => \App\Models\AssessmentQuestionAttempt::query()->whereKey($attemptId)->exists(),'evaluation_run_exists' => \App\Models\AssessmentEvaluationRun::query()->where('AssessmentQuestionAttemptID', $attemptId)->exists(),];




*/
