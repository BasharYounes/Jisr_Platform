<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityPostRequest;
use App\Http\Requests\Community\UpdateCommunityPostRequest;
use App\Http\Resources\Community\CommunityPostCollection;
use App\Http\Resources\Community\CommunityPostResource;
use App\Models\Post;
use App\Services\Community\CommunityPostService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CommunityPostService $communityPostService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $posts = $this->communityPostService->index(
            $request->only(['type', 'search', 'per_page'])
        );

        return (new CommunityPostCollection($posts))
            ->response()
            ->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }

    public function store(StoreCommunityPostRequest $request): JsonResponse
    {
        $post = $this->communityPostService->create(
            $request->user(),
            $request->validated()
        );

        return $this->success(
            'Community post created successfully.',
            new CommunityPostResource($post),
            201
        );
    }

    public function show(Post $post): JsonResponse
    {
        $post->load('user');

        return $this->success(
            'Community post retrieved successfully.',
            new CommunityPostResource($post)
        );
    }

    public function update(
        UpdateCommunityPostRequest $request,
        Post $post
    ): JsonResponse {
        if ($post->User_id !== $request->user()->id) {
            return $this->error('You are not allowed to update this post.', null, 403);
        }

        $post = $this->communityPostService->update(
            $post,
            $request->validated()
        );

        return $this->success(
            'Community post updated successfully.',
            new CommunityPostResource($post)
        );
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        if ($post->User_id !== $request->user()->id) {
            return $this->error('You are not allowed to delete this post.', null, 403);
        }

        $this->communityPostService->delete($post);

        return $this->success('Community post deleted successfully.');
    }
}
