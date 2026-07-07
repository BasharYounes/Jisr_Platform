<?php

namespace App\Http\Resources\Community;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CommunityPostCollection extends ResourceCollection
{
    public $collects = CommunityPostResource::class;

    public function toArray(Request $request): array
    {
        return [
            'status' => true,
            'message' => 'Community posts retrieved successfully.',
            'data' => $this->collection,
        ];
    }

    public function paginationInformation($request, $paginated, $default): array
    {
        return [
            'pagination' => [
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
                'has_more' => $this->resource->hasMorePages(),
            ],
        ];
    }
}
