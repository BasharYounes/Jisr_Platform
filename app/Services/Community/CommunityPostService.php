<?php

namespace App\Services\Community;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CommunityPostService
{
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
        return Post::create([
            'User_id' => $user->id,
            'Content' => $data['content'],
            'Type' => $data['type'],
            'LikeCount' => 0,
            'CommentCount' => 0,
        ])->load('user');
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
