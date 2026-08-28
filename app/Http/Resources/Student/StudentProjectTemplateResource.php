<?php

namespace App\Http\Resources\Student;

use App\Domains\Student\Enums\ProjectTemplateApplicationStatus;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProjectTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $application = $this->relationLoaded('applications')
            ? $this->applications->first()
            : null;

        $applicationStatus = $application?->status;

        if ($applicationStatus instanceof BackedEnum) {
            $applicationStatus = $applicationStatus->value;
        }

        $specialization = $this->creator?->supervisorProfile?->specialization;

        if ($specialization instanceof BackedEnum) {
            $specialization = $specialization->value;
        }

        $maxStudents = is_null($this->max_students)
            ? null
            : (int) $this->max_students;

        $activeApplicationsCount = (int) ($this->active_applications_count ?? 0);

        $isFull = $maxStudents !== null
            && $activeApplicationsCount >= $maxStudents;

        $remainingSlots = $maxStudents === null
            ? null
            : max(0, $maxStudents - $activeApplicationsCount);

        $canApply = $application === null && ! $isFull;

        $applyBlockReason = match (true) {
            $application !== null => 'already_applied',
            $isFull => 'capacity_reached',
            default => null,
        };

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'level' => $this->level,
            'expected_outcome' => $this->expected_outcome,

            'supervisor' => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
                'specialization' => $specialization,
                'is_volunteer' => $this->creator?->supervisorProfile?->is_volunteer,
            ],

            'tasks_summary' => [
                'count' => (int) ($this->tasks_count ?? 0),
                'estimated_total_hours' => is_null($this->estimated_total_hours)
                    ? 0
                    : (int) $this->estimated_total_hours,
            ],

            'capacity' => [
                'max_students' => $maxStudents,
                'active_applications_count' => $activeApplicationsCount,
                'remaining_slots' => $remainingSlots,
                'is_full' => $isFull,
            ],

            'application' => $application === null
                ? null
                : [
                    'application_id' => $application->id,
                    'status' => $applicationStatus,
                    'project_assignment_id' => $application->project_assignment_id,
                    'applied_at' => $application->applied_at?->toISOString(),
                ],

            'actions' => [
                'can_apply' => $canApply,
                'apply_block_reason' => $applyBlockReason,
                'can_open_assignment' => $applicationStatus === ProjectTemplateApplicationStatus::ACCEPTED->value
                    && $application?->project_assignment_id !== null,
            ],

            'tasks' => $this->whenLoaded('tasks', function (): array {
                return $this->tasks
                    ->map(fn ($task): array => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'estimated_hours' => $task->estimated_hours,
                        'order_index' => $task->order_index,
                    ])
                    ->values()
                    ->all();
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
