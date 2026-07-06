<?php

namespace App\Services\Opportunities;

use App\Interfaces\OpportunityApplicationRepositoryInterface;
use App\Interfaces\OpportunityInterviewRepositoryInterface;
use App\Interfaces\OpportunityRepositoryInterface;
use App\Models\OpportunityInterview;
use App\Services\Conversations\ConversationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpportunityInterviewService
{
    public function __construct(
        private readonly OpportunityRepositoryInterface $opportunityRepository,
        private readonly OpportunityApplicationRepositoryInterface $applicationRepository,
        private readonly OpportunityInterviewRepositoryInterface $interviewRepository,
        private readonly ConversationService $conversationService,
    ) {}

    public function schedule(
        int $companyId,
        int $companyUserId,
        int $opportunityId,
        int $applicationId,
        array $data
    ): OpportunityInterview {
        return DB::transaction(function () use (
            $companyId,
            $companyUserId,
            $opportunityId,
            $applicationId,
            $data
        ): OpportunityInterview {
            $this->opportunityRepository->findCompanyOpportunityOrFail(
                companyId: $companyId,
                opportunityId: $opportunityId
            );

            $application = $this->applicationRepository->findCompanyCandidateOrFail(
                companyId: $companyId,
                opportunityId: $opportunityId,
                applicationId: $applicationId
            );

            if ($application->status !== 'pending') {
                throw ValidationException::withMessages([
                    'application' => [
                        'يمكن جدولة مقابلة فقط لطلب قيد المراجعة. | Interview can only be scheduled for a pending application.',
                    ],
                ]);
            }

            if ($this->interviewRepository->findByApplicationId($application->id)) {
                throw ValidationException::withMessages([
                    'interview' => [
                        'تمت جدولة مقابلة لهذا الطلب مسبقًا. | An interview has already been scheduled for this application.',
                    ],
                ]);
            }

            $interview = $this->interviewRepository->create([
                'application_id' => $application->id,
                'opportunity_id' => $opportunityId,
                'company_id' => $companyId,
                'student_user_id' => $application->user_id,
                'scheduled_at' => $data['scheduled_at'],
                'meeting_type' => $data['meeting_type'],
                'meeting_link' => $data['meeting_link'] ?? null,
                'location' => $data['location'] ?? null,
                'status' => 'scheduled',
                'notes' => $data['notes'] ?? null,
            ]);

            $conversation = $this->conversationService->openForConversationable(
                conversationable: $interview,
                participants: [
                    [
                        'user_id' => $companyUserId,
                        'role' => 'company',
                    ],
                    [
                        'user_id' => (int) $interview->student_user_id,
                        'role' => 'student',
                    ],
                ]
            );

            $interview->conversation_data = [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'participants_count' => $conversation->participants->count(),
            ];

            // TODO: Dispatch notification to student when interview is scheduled.

            return $interview;
        });
    }

    public function reschedule(
        int $companyId,
        int $opportunityId,
        int $interviewId,
        array $data
    ): OpportunityInterview {
        return DB::transaction(function () use (
            $companyId,
            $opportunityId,
            $interviewId,
            $data
        ): OpportunityInterview {
            $interview = $this->interviewRepository->findCompanyInterviewOrFail(
                companyId: $companyId,
                opportunityId: $opportunityId,
                interviewId: $interviewId
            );

            if (! in_array($interview->status, ['scheduled', 'rescheduled'], true)) {
                throw ValidationException::withMessages([
                    'interview' => [
                        'لا يمكن إعادة جدولة هذه المقابلة بحالتها الحالية. | This interview cannot be rescheduled in its current status.',
                    ],
                ]);
            }

            $interview = $this->interviewRepository->update($interview, [
                'scheduled_at' => $data['scheduled_at'],
                'meeting_type' => $data['meeting_type'] ?? $interview->meeting_type,
                'meeting_link' => array_key_exists('meeting_link', $data)
                    ? $data['meeting_link']
                    : $interview->meeting_link,
                'location' => array_key_exists('location', $data)
                    ? $data['location']
                    : $interview->location,
                'status' => 'rescheduled',
                'notes' => $data['notes'] ?? $interview->notes,
            ]);

            // TODO: Dispatch notification to student when interview is rescheduled.

            return $interview;
        });
    }

    public function cancel(
        int $companyId,
        int $opportunityId,
        int $interviewId
    ): OpportunityInterview {
        return DB::transaction(function () use (
            $companyId,
            $opportunityId,
            $interviewId
        ): OpportunityInterview {
            $interview = $this->interviewRepository->findCompanyInterviewOrFail(
                companyId: $companyId,
                opportunityId: $opportunityId,
                interviewId: $interviewId
            );

            if (! in_array($interview->status, ['scheduled', 'rescheduled'], true)) {
                throw ValidationException::withMessages([
                    'interview' => [
                        'لا يمكن إلغاء هذه المقابلة بحالتها الحالية. | This interview cannot be cancelled in its current status.',
                    ],
                ]);
            }

            $interview = $this->interviewRepository->update($interview, [
                'status' => 'cancelled',
            ]);

            // TODO: Dispatch notification to student when interview is cancelled.

            return $interview;
        });
    }

    public function complete(
        int $companyId,
        int $opportunityId,
        int $interviewId,
        array $data = []
    ): OpportunityInterview {
        return DB::transaction(function () use (
            $companyId,
            $opportunityId,
            $interviewId,
            $data
        ): OpportunityInterview {
            $interview = $this->interviewRepository->findCompanyInterviewOrFail(
                companyId: $companyId,
                opportunityId: $opportunityId,
                interviewId: $interviewId
            );

            if (! in_array($interview->status, ['scheduled', 'rescheduled'], true)) {
                throw ValidationException::withMessages([
                    'interview' => [
                        'لا يمكن إنهاء هذه المقابلة بحالتها الحالية. | This interview cannot be completed in its current status.',
                    ],
                ]);
            }

            if ($interview->scheduled_at->isFuture()) {
                throw ValidationException::withMessages([
                    'scheduled_at' => [
                        'لا يمكن إنهاء المقابلة قبل موعدها. | Interview cannot be completed before its scheduled time.',
                    ],
                ]);
            }

            $interview = $this->interviewRepository->update($interview, [
                'status' => 'completed',
                'notes' => $data['notes'] ?? $interview->notes,
            ]);

            // TODO: Dispatch notification or internal event when interview is completed.

            return $interview;
        });
    }
}
