<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityPostRequest;
use App\Http\Requests\Community\UpdateCommunityPostRequest;
use App\Http\Resources\Community\CommunityPostResource;
use App\Models\Post;
use App\Services\Community\CommunityPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(
        private readonly CommunityPostService $communityPostService
    ) {
    }

    public function index(Request $request)
    {
        $posts = $this->communityPostService->index(
            $request->only(['type', 'search', 'per_page'])
        );

        return CommunityPostResource::collection($posts);
    }

    public function store(StoreCommunityPostRequest $request): JsonResponse
    {
        $post = $this->communityPostService->create(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Community post created successfully.',
            'data' => new CommunityPostResource($post),
        ], 201);
    }

    public function show(Post $post): CommunityPostResource
    {
        $post->load('user');

        return new CommunityPostResource($post);
    }

    public function update(
        UpdateCommunityPostRequest $request,
        Post $post
    ): JsonResponse {
        if ($post->User_id !== $request->user()->id) {
            abort(403, 'You are not allowed to update this post.');
        }

        $post = $this->communityPostService->update(
            $post,
            $request->validated()
        );

        return response()->json([
            'message' => 'Community post updated successfully.',
            'data' => new CommunityPostResource($post),
        ]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        if ($post->User_id !== $request->user()->id) {
            abort(403, 'You are not allowed to delete this post.');
        }

        $this->communityPostService->delete($post);

        return response()->json([
            'message' => 'Community post deleted successfully.',
        ]);
    }
}
