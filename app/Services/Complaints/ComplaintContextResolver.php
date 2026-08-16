<?php

namespace App\Services\Complaints;

use App\Data\Complaints\ResolvedComplaintTarget;
use App\Enums\ComplaintContextType;
use App\Enums\MentorApplicationStatus;
use App\Models\Comment;
use App\Models\CompanyTask;
use App\Models\CompanyTaskAssignment;
use App\Models\Conversation;
use App\Models\MentorProfile;
use App\Models\OpportunityInterview;
use App\Models\Post;
use App\Models\ProjectAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class ComplaintContextResolver
{
    public function resolve(
        User $complainant,
        ComplaintContextType $contextType,
        int $contextId,
        ?int $targetUserId = null,
    ): ResolvedComplaintTarget {
        return match ($contextType) {
            ComplaintContextType::ProjectAssignment => $this->resolveProjectAssignment(
                $complainant,
                $contextId,
                $targetUserId,
            ),
            ComplaintContextType::CompanyTaskAssignment => $this->resolveCompanyTaskAssignment(
                $complainant,
                $contextId,
            ),
            ComplaintContextType::OpportunityInterview => $this->resolveOpportunityInterview(
                $complainant,
                $contextId,
            ),
            ComplaintContextType::CommunityPost => $this->resolveCommunityPost(
                $contextId,
            ),
            ComplaintContextType::CommunityComment => $this->resolveCommunityComment(
                $contextId,
            ),
            ComplaintContextType::MentorProfile => $this->resolveMentorProfile(
                $complainant,
                $contextId,
            ),
        };
    }

    private function resolveProjectAssignment(
        User $complainant,
        int $assignmentId,
        ?int $targetUserId,
    ): ResolvedComplaintTarget {
        $assignment = ProjectAssignment::query()
            ->select(['id', 'supervisor_id'])
            ->findOrFail($assignmentId);

        if ((int) $assignment->supervisor_id === (int) $complainant->id) {
            if ($targetUserId === null) {
                throw ValidationException::withMessages([
                    'target_user_id' => [
                        'يجب تحديد الطالب عند تقديم المشرف شكوى ضمن مشروع جماعي. | Supervisor must specify the student when reporting within a project assignment.',
                    ],
                ]);
            }

            $isAssignmentStudent = $assignment->members()
                ->where('student_id', $targetUserId)
                ->exists();

            if (! $isAssignmentStudent) {
                throw ValidationException::withMessages([
                    'target_user_id' => [
                        'الطالب المحدد ليس عضواً في هذا المشروع. | The selected student is not a member of this project assignment.',
                    ],
                ]);
            }

            return ResolvedComplaintTarget::user($targetUserId);
        }

        $isAssignmentStudent = $assignment->members()
            ->where('student_id', $complainant->id)
            ->exists();

        if ($isAssignmentStudent) {
            if ($targetUserId !== null) {
                throw ValidationException::withMessages([
                    'target_user_id' => [
                        'الطالب لا يحدد المستخدم المستهدف في شكوى المشروع؛ يتم تحديد المشرف تلقائياً. | A student does not choose the target user for a project complaint; the assigned supervisor is resolved automatically.',
                    ],
                ]);
            }

            return ResolvedComplaintTarget::user((int) $assignment->supervisor_id);
        }

        throw new AuthorizationException(
            'You are not a participant in this project assignment.'
        );
    }

    private function resolveCompanyTaskAssignment(
        User $complainant,
        int $assignmentId,
    ): ResolvedComplaintTarget {
        $assignment = CompanyTaskAssignment::withTrashed()
            ->select([
                'id',
                'company_task_id',
                'student_user_id',
            ])
            ->findOrFail($assignmentId);

        $task = CompanyTask::withTrashed()
            ->select(['id', 'company_id'])
            ->findOrFail($assignment->company_task_id);

        if ((int) $assignment->student_user_id === (int) $complainant->id) {
            $conversation = $this->findConversationFor($assignment);

            return ResolvedComplaintTarget::user(
                $this->resolveCompanyRepresentative(
                    $conversation,
                    (int) $task->company_id,
                )
            );
        }

        if ($this->userBelongsToCompany($complainant, (int) $task->company_id)) {
            return ResolvedComplaintTarget::user(
                (int) $assignment->student_user_id
            );
        }

        throw new AuthorizationException(
            'You are not a participant in this company task assignment.'
        );
    }

    private function resolveOpportunityInterview(
        User $complainant,
        int $interviewId,
    ): ResolvedComplaintTarget {
        $interview = OpportunityInterview::query()
            ->select([
                'id',
                'company_id',
                'student_user_id',
            ])
            ->findOrFail($interviewId);

        if ((int) $interview->student_user_id === (int) $complainant->id) {
            $conversation = $this->findConversationFor($interview);

            return ResolvedComplaintTarget::user(
                $this->resolveCompanyRepresentative(
                    $conversation,
                    (int) $interview->company_id,
                )
            );
        }

        if ($this->userBelongsToCompany(
            $complainant,
            (int) $interview->company_id,
        )) {
            return ResolvedComplaintTarget::user(
                (int) $interview->student_user_id
            );
        }

        throw new AuthorizationException(
            'You are not a participant in this opportunity interview.'
        );
    }

    private function resolveCommunityPost(int $postId): ResolvedComplaintTarget
    {
        $post = Post::query()
            ->select(['id', 'User_id'])
            ->findOrFail($postId);

        return ResolvedComplaintTarget::user((int) $post->User_id);
    }

    private function resolveCommunityComment(
        int $commentId,
    ): ResolvedComplaintTarget {
        $comment = Comment::query()
            ->select(['id', 'user_id'])
            ->findOrFail($commentId);

        return ResolvedComplaintTarget::user((int) $comment->user_id);
    }

    private function resolveMentorProfile(
        User $complainant,
        int $mentorProfileId,
    ): ResolvedComplaintTarget {
        if (! $complainant->hasRole('student')) {
            throw new AuthorizationException(
                'Only students can submit complaints about mentors.'
            );
        }

        $mentorProfile = MentorProfile::query()
            ->select(['id', 'user_id', 'status'])
            ->where('status', MentorApplicationStatus::Approved->value)
            ->findOrFail($mentorProfileId);

        if (
            $mentorProfile->user_id !== null
            && (int) $mentorProfile->user_id === (int) $complainant->id
        ) {
            throw ValidationException::withMessages([
                'context_id' => [
                    'لا يمكنك تقديم شكوى على ملفك الإرشادي الخاص. | You cannot submit a complaint against your own mentor profile.',
                ],
            ]);
        }

        return ResolvedComplaintTarget::mentorProfile(
            (int) $mentorProfile->id
        );
    }

    private function findConversationFor(Model $context): Conversation
    {
        $conversation = Conversation::query()
            ->select(['id'])
            ->where('conversationable_type', $context->getMorphClass())
            ->where('conversationable_id', $context->getKey())
            ->first();

        if ($conversation === null) {
            throw ValidationException::withMessages([
                'context_id' => [
                    'لا توجد محادثة موثقة لهذا التعامل، لذلك لا يمكن تحديد ممثل الشركة بأمان. | No recorded conversation exists for this interaction, so the company representative cannot be resolved safely.',
                ],
            ]);
        }

        return $conversation;
    }

    private function resolveCompanyRepresentative(
        Conversation $conversation,
        int $companyId,
    ): int {
        $companyUserId = $conversation->participants()
            ->wherePivot('role', 'company')
            ->whereHas(
                'companies',
                fn ($query) => $query->where('companies.id', $companyId)
            )
            ->value('users.id');

        if ($companyUserId === null) {
            throw ValidationException::withMessages([
                'context_id' => [
                    'تعذر تحديد ممثل الشركة المرتبط بهذا التعامل. | The company representative associated with this interaction could not be resolved.',
                ],
            ]);
        }

        return (int) $companyUserId;
    }

    private function userBelongsToCompany(User $user, int $companyId): bool
    {
        return $user->companies()
            ->whereKey($companyId)
            ->exists();
    }
}
