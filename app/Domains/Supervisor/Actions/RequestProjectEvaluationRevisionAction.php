<?php

namespace App\Domains\Supervisor\Actions;

use App\Domains\Supervisor\Enums\EvaluationRevisionRequestSource;
use App\Domains\Supervisor\Enums\EvaluationRevisionRequestStatus;
use App\Domains\Supervisor\Enums\ProjectEvaluationStatus;
use App\Models\EvaluationRevisionRequest;
use App\Models\ProjectEvaluation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestProjectEvaluationRevisionAction
{
    public function execute(
        ProjectEvaluation $evaluation,
        User $requestedBy,
        string $reason
    ): EvaluationRevisionRequest {
        return DB::transaction(function () use (
            $evaluation,
            $requestedBy,
            $reason
        ): EvaluationRevisionRequest {
            /*
             * نقفل التقييم أثناء العملية لمنع إنشاء
             * طلبي تعديل متزامنين للتقييم نفسه.
             */
            $lockedEvaluation =
                ProjectEvaluation::query()
                    ->whereKey($evaluation->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

            /*
             * المشرف الرئيسي لا يطلب التعديل إلا
             * بعد أن يرسل المشرف العادي التقييم.
             */
            if (
                $lockedEvaluation->status
                !== ProjectEvaluationStatus::SUBMITTED->value
            ) {
                throw ValidationException::withMessages([
                    'status' => [
                        'Only submitted evaluations can be returned for revision.',
                    ],
                ]);
            }

            /*
             * لا نسمح بوجود طلب تعديل pending ثانٍ
             * للتقييم نفسه.
             */
            $pendingRequest =
                EvaluationRevisionRequest::query()
                    ->where(
                        'project_evaluation_id',
                        $lockedEvaluation->id
                    )
                    ->where(
                        'status',
                        EvaluationRevisionRequestStatus::Pending->value
                    )
                    ->lockForUpdate()
                    ->first();

            if ($pendingRequest !== null) {
                throw ValidationException::withMessages([
                    'revision_request' => [
                        'This evaluation already has a pending revision request.',
                    ],
                ]);
            }

            $revisionRequest =
                EvaluationRevisionRequest::create([
                    'project_evaluation_id' => $lockedEvaluation->id,

                    'requested_by' => $requestedBy->id,

                    'assigned_to' => $lockedEvaluation->supervisor_id,

                    'source' => EvaluationRevisionRequestSource::LeadReview->value,

                    'source_reference_id' => null,

                    'reason' => trim($reason),

                    'status' => EvaluationRevisionRequestStatus::Pending->value,
                ]);

            /*
             * بعد إنشاء الطلب يصبح التقييم متاحًا
             * للمشرف الأصلي كي يعدله.
             */
            $lockedEvaluation->update([
                'status' => ProjectEvaluationStatus::NEEDS_REVISION->value,
            ]);

            return $revisionRequest->load([
                'requestedBy:id,name,email',
                'assignedTo:id,name,email',
            ]);
        });
    }
}
