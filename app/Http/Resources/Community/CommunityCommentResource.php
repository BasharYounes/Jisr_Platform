<?php

namespace App\Http\Resources\Community;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'parent_comment_id' => $this->parent_comment_id,
            'content' => $this->content,
            'replies_count' => (int) ($this->replies_count ?? 0),
            'likes_count' => (int) ($this->likes_count ?? 0),
            'is_owner' => $user ? $user->id === $this->user_id : false,
            'is_liked' => (bool) ($this->is_liked ?? false),
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'profile_picture_url' => $this->user?->profile_picture_url ? asset($this->user->profile_picture_url) : null,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
