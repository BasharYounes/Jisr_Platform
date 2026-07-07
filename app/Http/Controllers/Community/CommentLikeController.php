<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Services\Community\CommunityLikeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentLikeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CommunityLikeService $communityLikeService
    ) {}

    public function toggle(Request $request, Comment $comment): JsonResponse
    {
        $result = $this->communityLikeService->toggleCommentLike(
            comment: $comment,
            user: $request->user()
        );

        return $this->success(
            $result['liked']
                ? 'Comment liked successfully.'
                : 'Comment unliked successfully.',
            $result
        );
    }
}
