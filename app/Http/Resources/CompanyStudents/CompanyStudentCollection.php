<?php

namespace App\Http\Resources\CompanyStudents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CompanyStudentCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'students' => CompanyStudentSummaryResource::collection(
                $this->collection
            ),

            'pagination' => [
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
            ],
        ];
    }
}
