<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use BackedEnum;
use Illuminate\Support\Collection;

final class GetProjectAssignmentEvaluationSummaryAction
{
    public function execute(ProjectAssignment $projectAssignment): array
    {
        $projectAssignment->loadMissing('projectTemplate');

        $activeStudentIds = $projectAssignment
            ->members()
            ->where('status', 'active')
            ->pluck('student_id')
            ->unique()
            ->values();

        $evaluations = $activeStudentIds->isEmpty()
            ? new Collection()
            : $projectAssignment
                ->evaluations()
                ->whereIn('student_id', $activeStudentIds)
                ->with([
                    'student:id,name,email',
                    'supervisor:id,name,email',
                ])
                ->orderBy('student_id')
                ->get();

        $gradedEvaluations = $evaluations
            ->filter(
                static fn (ProjectEvaluation $evaluation): bool =>
                    $evaluation->final_grade !== null
            )
            ->values();

        return [
            'project_assignment_id' => $projectAssignment->id,

            'project' => [
                'id' => $projectAssignment->id,
                'project_template_id' => $projectAssignment->project_template_id,
                'title' => $projectAssignment->projectTemplate?->title,
                'level' => $projectAssignment->projectTemplate?->level,
                'status' => $this->enumValue($projectAssignment->status),
                'progress_percentage' => (int) $projectAssignment->progress_percentage,
            ],

            'summary' => [
                'students_count' => $activeStudentIds->count(),
                'evaluated_students_count' => $gradedEvaluations
                    ->pluck('student_id')
                    ->unique()
                    ->count(),
                'average_final_grade' => $this->decimal(
                    $gradedEvaluations->avg('final_grade')
                ),
                'highest_final_grade' => $this->decimal(
                    $gradedEvaluations->max('final_grade')
                ),
                'lowest_final_grade' => $this->decimal(
                    $gradedEvaluations->min('final_grade')
                ),
                'approved_evaluations_count' => $this->countByStatus(
                    $evaluations,
                    ProjectEvaluationStatus::APPROVED
                ),
                'submitted_evaluations_count' => $this->countByStatus(
                    $evaluations,
                    ProjectEvaluationStatus::SUBMITTED
                ),
                'needs_revision_evaluations_count' => $this->countByStatus(
                    $evaluations,
                    ProjectEvaluationStatus::NEEDS_REVISION
                ),
                'draft_evaluations_count' => $this->countByStatus(
                    $evaluations,
                    ProjectEvaluationStatus::DRAFT
                ),
            ],

            'evaluations' => $evaluations
                ->map(fn (ProjectEvaluation $evaluation): array => [
                    'id' => $evaluation->id,
                    'student' => [
                        'id' => $evaluation->student?->id,
                        'name' => $evaluation->student?->name,
                        'email' => $evaluation->student?->email,
                    ],
                    'supervisor' => [
                        'id' => $evaluation->supervisor?->id,
                        'name' => $evaluation->supervisor?->name,
                        'email' => $evaluation->supervisor?->email,
                    ],
                    'final_grade' => $evaluation->final_grade,
                    'total_score' => $evaluation->total_score,
                    'status' => $this->enumValue($evaluation->status),
                    'evaluated_at' => $evaluation->evaluated_at?->toISOString(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function countByStatus(
        Collection $evaluations,
        ProjectEvaluationStatus $status
    ): int {
        return $evaluations
            ->filter(
                fn (ProjectEvaluation $evaluation): bool =>
                    $this->enumValue($evaluation->status) === $status->value
            )
            ->count();
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum
            ? $value->value
            : $value;
    }
}
