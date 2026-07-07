<?php

namespace App\Http\Resources\Community;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunityPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'content' => $this->Content,
            'type' => $this->Type,
            'like_count' => (int) $this->LikeCount,
            'comment_count' => (int) $this->CommentCount,
            'is_owner' => $user ? $user->id === $this->User_id : false,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'profile_picture_url' => $this->user?->profile_picture_url ? asset($this->user->profile_picture_url) : null,
            ],
            'comments' => CommunityCommentResource::collection(
                $this->whenLoaded('comments')
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
