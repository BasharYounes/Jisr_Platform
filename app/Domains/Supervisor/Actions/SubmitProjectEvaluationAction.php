<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\ProjectAssignmentStatus;
use App\Domains\Supervisor\Enums\ProjectAssignmentTaskStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationItem;
use App\Models\ProjectAssignment;
use App\Models\ProjectEvaluation;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class SubmitProjectEvaluationAction
{
    private const EVIDENCE_DISK = 'public';

    public function execute(
        ProjectAssignment $assignment,
        User $student,
        array $data
    ): ProjectEvaluation {
        $newlyStoredFiles = [];
        $oldEvidenceFiles = [];

        try {
            $evaluation = DB::transaction(function () use (
                $assignment,
                $student,
                $data,
                &$newlyStoredFiles,
                &$oldEvidenceFiles
            ) {
                $isActiveMember = $assignment->members()
                    ->where('student_id', $student->id)
                    ->where('status', 'active')
                    ->exists();

                if (! $isActiveMember) {
                    throw new DomainException(
                        'الطالب المحدد ليس عضواً نشطاً في فريق هذا المشروع. '.
                        '| The selected student is not an active member of this project team.'
                    );
                }

                if (
                    $assignment->status !==
                    ProjectAssignmentStatus::UNDER_REVIEW
                ) {
                    throw new DomainException(
                        'لا يمكن التقييم النهائي إلا عندما يكون المشروع قيد المراجعة. '.
                        '| Final evaluation is allowed only when the project is under review.'
                    );
                }

                $studentTasksQuery = $assignment->assignmentTasks()
                    ->where('assigned_student_id', $student->id);

                $studentTasksCount = (clone $studentTasksQuery)->count();

                if ($studentTasksCount === 0) {
                    throw new DomainException(
                        'لا يمكن تقييم طالب لم تُسند إليه أي مهمة في المشروع. '.
                        '| A student with no assigned project tasks cannot be evaluated.'
                    );
                }

                $unfinishedStudentTasks = (clone $studentTasksQuery)
                    ->where(
                        'status',
                        '!=',
                        ProjectAssignmentTaskStatus::DONE->value
                    )
                    ->count();

                if ($unfinishedStudentTasks > 0) {
                    throw new DomainException(
                        'لا يمكن تقييم الطالب قبل اكتمال جميع مهامه المسندة إليه. '.
                        '| All tasks assigned to this student must be completed before evaluation.'
                    );
                }

                $criteria = EvaluationCriteria::query()
                    ->whereIn(
                        'id',
                        collect($data['items'])
                            ->pluck('evaluation_criteria_id')
                    )
                    ->get()
                    ->keyBy('id');

                $totalWeightedScore = 0;
                $totalWeights = 0;

                foreach ($data['items'] as $item) {
                    $criterion = $criteria->get(
                        $item['evaluation_criteria_id']
                    );

                    if (! $criterion) {
                        throw new InvalidArgumentException(
                            'Invalid evaluation criterion.'
                        );
                    }

                    if ($item['score'] > $criterion->max_score) {
                        throw new InvalidArgumentException(
                            "Score cannot exceed max score for criterion: {$criterion->name}"
                        );
                    }

                    $normalizedScore =
                        $item['score'] / $criterion->max_score;

                    $totalWeightedScore +=
                        $normalizedScore * $criterion->weight;

                    $totalWeights += $criterion->weight;
                }

                $finalGrade = $totalWeights > 0
                    ? round(
                        ($totalWeightedScore / $totalWeights) * 100,
                        2
                    )
                    : 0;

                $evaluation = ProjectEvaluation::query()
                    ->where('project_assignment_id', $assignment->id)
                    ->where('student_id', $student->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    $evaluation !== null &&
                    $evaluation->status ===
                    ProjectEvaluationStatus::APPROVED->value
                ) {
                    throw new DomainException(
                        'لا يمكن تعديل تقييم تمت الموافقة عليه. '.
                        '| An approved evaluation cannot be modified.'
                    );
                }

                if ($evaluation === null) {
                    $evaluation = new ProjectEvaluation([
                        'project_assignment_id' => $assignment->id,
                        'student_id' => $student->id,
                    ]);
                }

                $evaluation->fill([
                    'supervisor_id' => auth()->id(),
                    'total_score' => round($totalWeightedScore, 2),
                    'final_grade' => $finalGrade,
                    'status' => ProjectEvaluationStatus::SUBMITTED->value,
                    'general_comment' => $data['general_comment'] ?? null,
                    'summary_metrics' => [
                        'criteria_count' => count($data['items']),
                        'total_weight' => $totalWeights,
                        'calculated_at' => now()->toISOString(),
                    ],
                    'evaluated_at' => now(),
                ]);

                $evaluation->save();

                /*
                 * نحفظ مسارات الصور القديمة قبل حذف عناصر التقييم.
                 * نحذف الملفات فعلياً فقط بعد نجاح الـTransaction.
                 */
                $oldEvidenceFiles = $evaluation->items()
                    ->with('evidences')
                    ->get()
                    ->flatMap(
                        fn (EvaluationItem $item) => $item->evidences
                    )
                    ->map(fn ($evidence) => [
                        'disk' => $evidence->disk,
                        'file_path' => $evidence->file_path,
                    ])
                    ->values()
                    ->all();

                $evaluation->items()->delete();

                foreach ($data['items'] as $item) {
                    $evaluationItem = $evaluation->items()->create([
                        'evaluation_criteria_id' => $item['evaluation_criteria_id'],
                        'score' => $item['score'],
                        'comment' => $item['comment'] ?? null,
                        'evidence' => $item['evidence'] ?? null,
                        'evidence_urls' => null,
                    ]);

                    $this->storeEvidenceImages(
                        evaluationItem: $evaluationItem,
                        assignmentId: $assignment->id,
                        studentId: $student->id,
                        images: $item['evidence_images'],
                        newlyStoredFiles: $newlyStoredFiles
                    );
                }

                return $evaluation->load([
                    'assignment.projectTemplate',
                    'student',
                    'supervisor',
                    'items.criteria',
                    'items.evidences',
                ]);
            });
        } catch (Throwable $exception) {
            /*
             * إن فشل حفظ التقييم في قاعدة البيانات،
             * لا نترك صوراً جديدة بلا سجلات.
             */
            foreach ($newlyStoredFiles as $file) {
                Storage::disk($file['disk'])->delete($file['file_path']);
            }

            throw $exception;
        }

        /*
         * بعد نجاح قاعدة البيانات نحذف فقط ملفات الإثبات القديمة
         * التي استُبدلت عند إعادة إرسال التقييم.
         */
        foreach ($oldEvidenceFiles as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['file_path']);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $evaluation;
    }

    private function storeEvidenceImages(
        EvaluationItem $evaluationItem,
        int $assignmentId,
        int $studentId,
        array $images,
        array &$newlyStoredFiles
    ): void {
        foreach ($images as $image) {
            if (! $image instanceof UploadedFile) {
                throw new InvalidArgumentException(
                    'Each evidence image must be a valid uploaded file.'
                );
            }

            $path = $image->store(
                "project-evaluation-evidences/{$assignmentId}/{$studentId}/{$evaluationItem->id}",
                self::EVIDENCE_DISK
            );

            $newlyStoredFiles[] = [
                'disk' => self::EVIDENCE_DISK,
                'file_path' => $path,
            ];

            $evaluationItem->evidences()->create([
                'disk' => self::EVIDENCE_DISK,
                'file_path' => $path,
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => $image->getMimeType(),
                'size_bytes' => $image->getSize(),
            ]);
        }
    }
}
