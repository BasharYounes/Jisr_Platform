<?php

namespace App\Services\Community;

use App\Models\Post;
use App\Models\User;
use App\Services\Points\PointService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CommunityPostService
{
    public function __construct(
        private readonly PointService $pointService
    ) {}

    public function index(array $filters = []): LengthAwarePaginator
    {
        return Post::query()
            ->with('user')
            ->when(
                isset($filters['type']),
                fn ($query) => $query->where('Type', $filters['type'])
            )
            ->when(
                isset($filters['search']),
                fn ($query) => $query->where('Content', 'like', '%' . $filters['search'] . '%')
            )
            ->latest()
            ->paginate($filters['per_page'] ?? 10);
    }

    public function create(User $user, array $data): Post
    {
        return DB::transaction(function () use ($user, $data) {
            $post = Post::create([
                'User_id' => $user->id,
                'Content' => $data['content'],
                'Type' => $data['type'],
                'LikeCount' => 0,
                'CommentCount' => 0,
            ])->load('user');

            $this->pointService->award(
                user: $user,
                actionType: 'community_post_created',
                reference: $post,
                description: 'Created a community post.'
            );

            return $post;
        });
    }

    public function update(Post $post, array $data): Post
    {
        $post->update([
            'Content' => $data['content'] ?? $post->Content,
            'Type' => $data['type'] ?? $post->Type,
        ]);

        return $post->refresh()->load('user');
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }
}