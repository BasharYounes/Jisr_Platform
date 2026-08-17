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
                'active_opportunities' => [
                    'count' => $this->resource['stats']['active_opportunities']['count'],
                    'ids' => $this->resource['stats']['active_opportunities']['ids'],
                ],

                'new_applicants' => [
                    'count' => $this->resource['stats']['new_applicants']['count'],
                    'items' => $this->resource['stats']['new_applicants']['items'],
                ],

                'active_assignments' => [
                    'count' => $this->resource['stats']['active_assignments']['count'],
                    'items' => $this->resource['stats']['active_assignments']['items'],
                ],

                'pending_reviews' => [
                    'count' => $this->resource['stats']['pending_reviews']['count'],
                    'items' => $this->resource['stats']['pending_reviews']['items'],
                ],
            ],

            'required_actions' => $this->resource['required_actions'],

            'recent_activities' => $this->resource['recent_activities'],
        ];
    }
}
