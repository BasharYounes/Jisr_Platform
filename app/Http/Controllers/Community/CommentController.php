<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\GetPostCommentsRequest;
use App\Http\Requests\Community\StoreCommunityCommentRequest;
use App\Http\Requests\Community\UpdateCommunityCommentRequest;
use App\Http\Resources\Community\CommunityCommentResource;
use App\Models\Comment;
use App\Models\Post;
use App\Services\Community\CommunityCommentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CommunityCommentService $communityCommentService
    ) {}

    public function indexByPost(GetPostCommentsRequest $request, Post $post): JsonResponse
    {
        $filter = $request->validated('filter') ?? 'oldest';
        $comments = $this->communityCommentService->getPostComments($post, $filter);

        return $this->success(
            'Post comments retrieved successfully.',
            CommunityCommentResource::collection($comments)
        );
    }

    public function store(
        StoreCommunityCommentRequest $request,
        Post $post
    ): JsonResponse {
        $comment = $this->communityCommentService->create(
            post: $post,
            user: $request->user(),
            data: $request->validated()
        );

        return $this->success(
            'Comment added successfully.',
            new CommunityCommentResource($comment),
            201
        );
    }

    public function update(
        UpdateCommunityCommentRequest $request,
        Comment $comment
    ): JsonResponse {
        $comment = $this->communityCommentService->update(
            comment: $comment,
            user: $request->user(),
            data: $request->validated()
        );

        return $this->success(
            'Comment updated successfully.',
            new CommunityCommentResource($comment)
        );
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->communityCommentService->delete(
            comment: $comment,
            user: $request->user()
        );

        return $this->success('Comment deleted successfully.');
    }

    public function replies(Comment $comment): JsonResponse
    {
        $replies = $this->communityCommentService->replies($comment);

        return $this->success(
            'Replies retrieved successfully.',
            CommunityCommentResource::collection($replies)
        );
    }
}
