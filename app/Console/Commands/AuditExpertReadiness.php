<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditExpertReadiness extends Command
{
    protected $signature = 'assessment:audit-expert-readiness
                            {--all : Include inactive questions too}';

    protected $description = 'Audits assessment questions for Expert Rules conversion readiness without changing data.';

    public function handle(): int
    {
        $questionsQuery = DB::table('question_bank')
            ->select([
                'QuestionID',
                'CareerPathID',
                'SkillID',
                'Level',
                'QuestionType',
                'Topic',
                'QuestionText',
                'IsActive',
                'EvaluationEngine',
                'RuleSetVersion',
                'IsExpertReady',
            ]);

        if (! $this->option('all')) {
            $questionsQuery->where('IsActive', true);
        }

        $questions = $questionsQuery
            ->orderBy('CareerPathID')
            ->orderBy('SkillID')
            ->orderBy('Level')
            ->orderBy('QuestionID')
            ->get();

        $rows = $questions->map(function ($question): array {
            $questionType = strtolower(
                trim((string) ($question->QuestionType ?? ''))
            );

            $isOpenText = $questionType === 'open_text';

            $ruleSetVersion = trim(
                (string) ($question->RuleSetVersion ?? '')
            );

            $rubricCount = DB::table('question_rubrics')
                ->where('QuestionID', $question->QuestionID)
                ->count();

            $matchingRuleSet = null;

            if ($ruleSetVersion !== '') {
                $matchingRuleSet = DB::table('assessment_rule_sets')
                    ->where('QuestionID', $question->QuestionID)
                    ->where('Version', $ruleSetVersion)
                    ->where('Status', 'active')
                    ->first();
            }

            $missing = [];

            if (! $isOpenText) {
                $status = 'NON_OPEN_TEXT_POLICY_REVIEW';

                $missing[] =
                    'حدد هل يحتاج تصحيحًا مباشرًا deterministic '
                    . 'أم Expert Rules خاصة';
            } else {
                if (
                    (string) $question->EvaluationEngine
                    !== 'expert_rules'
                ) {
                    $missing[] = 'EvaluationEngine = expert_rules';
                }

                if ($ruleSetVersion === '') {
                    $missing[] = 'RuleSetVersion';
                }

                if (! $matchingRuleSet) {
                    $missing[] =
                        'Active Rule Set مطابق لـ RuleSetVersion';
                }

                if ($rubricCount === 0) {
                    $missing[] = 'Rubrics';
                }

                if (empty($missing)) {
                    $status = (bool) $question->IsExpertReady
                        ? 'EXPERT_READY_FLAG_ON'
                        : 'EXPERT_CONFIGURED_STAGED';
                } elseif (
                    (string) $question->EvaluationEngine
                    === 'expert_rules'
                ) {
                    $status = 'EXPERT_PARTIALLY_CONFIGURED';
                } else {
                    $status = 'OPEN_TEXT_NEEDS_EXPERT_CONVERSION';
                }
            }

            return [
                'question_id' => $question->QuestionID,
                'career_path_id' => $question->CareerPathID,
                'skill_id' => $question->SkillID,
                'level' => $question->Level,
                'question_type' => $question->QuestionType,
                'topic' => $question->Topic,
                'question_text' => $question->QuestionText,
                'evaluation_engine' => $question->EvaluationEngine,
                'rule_set_version' => $question->RuleSetVersion,
                'expert_ready' => (bool) $question->IsExpertReady,
                'rubric_count' => $rubricCount,
                'active_rule_set' => $matchingRuleSet?->RuleSetCode,
                'status' => $status,
                'next_action' => empty($missing)
                    ? 'لا يوجد نقص هيكلي'
                    : implode(' | ', $missing),
            ];
        });

        $this->newLine();
        $this->info('Expert Rules Readiness Audit');
        $this->line('Read-only: no database changes were made.');

        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Questions scanned', $rows->count()],
                [
                    'Open text questions',
                    $rows->where(
                        'question_type',
                        'open_text'
                    )->count(),
                ],
                [
                    'Open text needing full conversion',
                    $rows->where(
                        'status',
                        'OPEN_TEXT_NEEDS_EXPERT_CONVERSION'
                    )->count(),
                ],
                [
                    'Partially configured Expert Rules',
                    $rows->where(
                        'status',
                        'EXPERT_PARTIALLY_CONFIGURED'
                    )->count(),
                ],
                [
                    'Structurally configured / staged',
                    $rows->where(
                        'status',
                        'EXPERT_CONFIGURED_STAGED'
                    )->count(),
                ],
                [
                    'Ready flag enabled',
                    $rows->where(
                        'status',
                        'EXPERT_READY_FLAG_ON'
                    )->count(),
                ],
                [
                    'Non-open-text policy review',
                    $rows->where(
                        'status',
                        'NON_OPEN_TEXT_POLICY_REVIEW'
                    )->count(),
                ],
            ]
        );

        $this->newLine();
        $this->info('All questions');

        $this->table(
            [
                'ID',
                'Path',
                'Skill',
                'Level',
                'Type',
                'Topic',
                'Engine',
                'Version',
                'Ready',
                'Rubrics',
                'Rule Set',
                'Status',
            ],
            $rows->map(function (array $row): array {
                return [
                    $row['question_id'],
                    $row['career_path_id'],
                    $row['skill_id'],
                    $row['level'],
                    $row['question_type'],
                    $row['topic'] ?? '-',
                    $row['evaluation_engine'] ?? '-',
                    $row['rule_set_version'] ?? '-',
                    $row['expert_ready'] ? 'yes' : 'no',
                    $row['rubric_count'],
                    $row['active_rule_set'] ?? '-',
                    $row['status'],
                ];
            })->all()
        );

        $needsWork = $rows->filter(function (array $row): bool {
            return in_array(
                $row['status'],
                [
                    'OPEN_TEXT_NEEDS_EXPERT_CONVERSION',
                    'EXPERT_PARTIALLY_CONFIGURED',
                    'NON_OPEN_TEXT_POLICY_REVIEW',
                ],
                true
            );
        });

        $this->newLine();
        $this->info('Questions requiring action');

        if ($needsWork->isEmpty()) {
            $this->line('No questions require structural action.');
        } else {
            $this->table(
                [
                    'ID',
                    'Question',
                    'Status',
                    'Required action',
                ],
                $needsWork->map(function (array $row): array {
                    return [
                        $row['question_id'],
                        Str::limit(
                            (string) $row['question_text'],
                            80
                        ),
                        $row['status'],
                        $row['next_action'],
                    ];
                })->all()
            );
        }

        return self::SUCCESS;
    }
}
