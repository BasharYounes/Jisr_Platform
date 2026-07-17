<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Community\CommunityLikeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostLikeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CommunityLikeService $communityLikeService
    ) {}

    public function toggle(Request $request, Post $post): JsonResponse
    {
        $result = $this->communityLikeService->togglePostLike(
            post: $post,
            user: $request->user()
        );

        return $this->success(
            $result['liked']
                ? 'Post liked successfully.'
                : 'Post unliked successfully.',
            $result
        );
    }
}
