<?php

namespace App\Services\Complaints;

use App\Data\Complaints\ResolvedComplaintTarget;
use App\Enums\ComplaintContextType;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class ComplaintService
{
    private const ALLOWED_COMPLAINANT_ROLES = [
        'student',
        'company',
        'supervisor',
    ];

    public function __construct(
        private readonly ComplaintContextResolver $contextResolver,
    ) {}

    public function create(User $complainant, array $data): Complaint
    {
        $this->ensureComplainantRoleIsAllowed($complainant);

        $contextType = ComplaintContextType::from($data['context_type']);
        $contextId = (int) $data['context_id'];

        $target = $this->contextResolver->resolve(
            complainant: $complainant,
            contextType: $contextType,
            contextId: $contextId,
            targetUserId: isset($data['target_user_id'])
                ? (int) $data['target_user_id']
                : null,
        );

        $this->ensureUserIsNotReportingSelf($complainant, $target);

        $deduplicationKey = $this->makeDeduplicationKey(
            complainant: $complainant,
            target: $target,
            contextType: $contextType,
            contextId: $contextId,
        );

        if (
            Complaint::query()
                ->where('deduplication_key', $deduplicationKey)
                ->exists()
        ) {
            $this->throwDuplicateComplaintValidationException();
        }

        try {
            $complaint = Complaint::query()->create([
                'complainant_user_id' => $complainant->id,
                'reported_user_id' => $target->reportedUserId,
                'reported_mentor_profile_id' => $target->reportedMentorProfileId,
                'context_type' => $contextType->value,
                'context_id' => $contextId,
                'reason' => $data['reason'],
                'status' => 'pending',
                'deduplication_key' => $deduplicationKey,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $this->throwDuplicateComplaintValidationException();
            }

            throw $exception;
        }

        return $complaint->load([
            'complainant:id,name,email',
            'reportedUser:id,name,email',
            'reportedMentorProfile:id,user_id,full_name,email,specialization,professional_title',
        ]);
    }

    private function ensureComplainantRoleIsAllowed(User $complainant): void
    {
        if (! $complainant->hasAnyRole(self::ALLOWED_COMPLAINANT_ROLES)) {
            throw new AuthorizationException(
                'Only students, companies, and supervisors can submit complaints.'
            );
        }
    }

    private function ensureUserIsNotReportingSelf(
        User $complainant,
        ResolvedComplaintTarget $target,
    ): void {
        if (
            $target->reportedUserId !== null
            && (int) $target->reportedUserId === (int) $complainant->id
        ) {
            throw ValidationException::withMessages([
                'complaint' => [
                    'لا يمكنك تقديم شكوى على نفسك. | You cannot submit a complaint against yourself.',
                ],
            ]);
        }
    }

    private function makeDeduplicationKey(
        User $complainant,
        ResolvedComplaintTarget $target,
        ComplaintContextType $contextType,
        int $contextId,
    ): string {
        return hash(
            'sha256',
            implode('|', [
                'complainant:'.$complainant->id,
                'target:'.$target->identity(),
                'context:'.$contextType->value.':'.$contextId,
            ])
        );
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();

        if (in_array($sqlState, ['23000', '23505'], true)) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint');
    }

    private function throwDuplicateComplaintValidationException(): never
    {
        throw ValidationException::withMessages([
            'complaint' => [
                'لديك شكوى نشطة مسبقاً على نفس الطرف ضمن نفس السياق. انتظر حتى تتم معالجتها قبل تقديم شكوى جديدة. | You already have an active complaint against the same target in this context. Wait until it is reviewed before submitting another complaint.',
            ],
        ]);
    }
}
