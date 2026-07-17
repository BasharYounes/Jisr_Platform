<?php

namespace App\Services\Community;

use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\PointTransaction;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\User;
use App\Services\Points\PointService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommunityLikeService
{
    public function __construct(
        private readonly PointService $pointService
    ) {}

    public function togglePostLike(Post $post, User $user): array
    {
        if ($post->User_id === $user->id) {
            throw ValidationException::withMessages([
                'post' => 'لا يمكنك الإعجاب بمنشورك الخاص.',
            ]);
        }

        return DB::transaction(function () use ($post, $user) {
            $like = PostLike::query()
                ->where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->first();

            if ($like) {
                // Remove points if they were awarded for this like
                PointTransaction::query()
                    ->where('reference_type', get_class($like))
                    ->where('reference_id', $like->id)
                    ->delete();

                $like->delete();

                $post->update([
                    'LikeCount' => max(0, ((int) $post->LikeCount) - 1),
                ]);

                return [
                    'liked' => false,
                    'like_count' => (int) $post->refresh()->LikeCount,
                ];
            }

            $newLike = PostLike::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);

            $post->increment('LikeCount');

            // Add points for post owner
            if ($post->user) {
                $this->pointService->award(
                    user: $post->user, // The owner of the post gets the points
                    actionType: 'community_post_liked_received',
                    reference: $newLike, // The reference must be the specific Like!
                    description: "Received a like from user {$user->name}."
                );
            }

            // TODO: Add in-app notification for post owner later.

            return [
                'liked' => true,
                'like_count' => (int) $post->refresh()->LikeCount,
            ];
        });
    }

    public function toggleCommentLike(Comment $comment, User $user): array
    {
        if ($comment->user_id === $user->id) {
            throw ValidationException::withMessages([
                'comment' => 'لا يمكنك الإعجاب بتعليقك الخاص.',
            ]);
        }

        return DB::transaction(function () use ($comment, $user) {
            $like = CommentLike::query()
                ->where('comment_id', $comment->id)
                ->where('user_id', $user->id)
                ->first();

            if ($like) {
                // Remove points if they were awarded for this like
                PointTransaction::query()
                    ->where('reference_type', get_class($like))
                    ->where('reference_id', $like->id)
                    ->delete();

                $like->delete();

                return [
                    'liked' => false,
                ];
            }

            $newLike = CommentLike::create([
                'comment_id' => $comment->id,
                'user_id' => $user->id,
            ]);

            // Add points for comment owner
            if ($comment->user) {
                $this->pointService->award(
                    user: $comment->user, // The owner of the comment gets the points
                    actionType: 'community_comment_liked_received',
                    reference: $newLike,
                    description: "Received a like on comment from user {$user->name}."
                );
            }

            // TODO: Add in-app notification for comment owner later.

            return [
                'liked' => true,
            ];
        });
    }
}
