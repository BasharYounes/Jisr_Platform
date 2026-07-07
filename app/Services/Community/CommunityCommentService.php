<?php

namespace App\Services\Community;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\Points\PointService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunityCommentService
{
    public function __construct(
        private readonly PointService $pointService
    ) {}

    public function create(Post $post, User $user, array $data): Comment
    {
        return DB::transaction(function () use ($post, $user, $data) {
            $parentCommentId = $data['parent_comment_id'] ?? null;

            if ($parentCommentId) {
                $parentComment = Comment::query()
                    ->where('id', $parentCommentId)
                    ->where('post_id', $post->id)
                    ->whereNull('parent_comment_id')
                    ->first();

                if (! $parentComment) {
                    throw ValidationException::withMessages([
                        'parent_comment_id' => 'لا يمكن الرد إلا على تعليق أساسي داخل نفس المنشور.',
                    ]);
                }
            }

            $comment = Comment::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'parent_comment_id' => $parentCommentId,
                'content' => $data['content'],
            ]);

            $post->increment('CommentCount');

            // TODO: Create in-app notification for post owner later.
            // شرطها لاحقاً:
            // if ($post->User_id !== $user->id) { notify post owner }

            $this->pointService->award(
                user: $user,
                actionType: 'community_comment_created',
                reference: $comment,
                description: 'Created a community comment.'
            );

            return $comment->load('user')->loadCount('replies');
        });
    }

    public function update(Comment $comment, User $user, array $data): Comment
    {
        if ($comment->user_id !== $user->id) {
            abort(403, 'You are not allowed to update this comment.');
        }

        $comment->update([
            'content' => $data['content'],
        ]);

        return $comment->refresh()->load('user')->loadCount('replies');
    }

    public function delete(Comment $comment, User $user): void
    {
        if ($comment->user_id !== $user->id) {
            abort(403, 'You are not allowed to delete this comment.');
        }

        DB::transaction(function () use ($comment) {
            $post = $comment->post;

            $deletedCount = 1;

            if ($comment->parent_comment_id === null) {
                $replyIds = $comment->replies()->pluck('id');

                if ($replyIds->isNotEmpty()) {
                    Comment::query()
                        ->whereIn('id', $replyIds)
                        ->delete();

                    $deletedCount += $replyIds->count();
                }
            }

            $comment->delete();

            if ($post && $post->CommentCount > 0) {
                $post->update([
                    'CommentCount' => max(0, $post->CommentCount - $deletedCount),
                ]);
            }
        });
    }

    public function getPostComments(Post $post, string $filter = 'oldest')
    {
        $query = Comment::query()
            ->with('user')
            ->withCount('replies')
            ->where('post_id', $post->id)
            ->whereNull('parent_comment_id');

        if ($filter === 'latest') {
            $query->latest();
        } elseif ($filter === 'top') {
            $query->orderByDesc('replies_count')->latest();
        } else {
            $query->oldest(); // Default
        }

        return $query->get();
    }

    public function replies(Comment $comment)
    {
        if ($comment->parent_comment_id !== null) {
            throw ValidationException::withMessages([
                'comment' => 'لا يمكن جلب ردود لتعليق هو أصلاً Reply.',
            ]);
        }

        return Comment::query()
            ->with('user')
            ->withCount('replies')
            ->where('parent_comment_id', $comment->id)
            ->oldest()
            ->get();
    }
}
