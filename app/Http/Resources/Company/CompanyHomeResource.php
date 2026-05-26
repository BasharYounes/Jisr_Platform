<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyHomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'company' => $this->resource['company'],

            'stats' => [
                'active_opportunities_count' => $this->resource['stats']['active_opportunities_count'],
                'new_applicants_count' => $this->resource['stats']['new_applicants_count'],
                'pending_reviews_count' => $this->resource['stats']['pending_reviews_count'],
                'active_assignments_count' => $this->resource['stats']['active_assignments_count'],
            ],

            'required_actions' => $this->resource['required_actions'],

            'recent_activities' => $this->resource['recent_activities'],

            'quick_create' => $this->resource['quick_create'],
        ];
    }
}