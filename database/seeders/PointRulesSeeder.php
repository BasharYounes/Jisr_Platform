<?php

namespace Database\Seeders;

use App\Models\PointActionType;
use App\Models\PointCategory;
use App\Models\PointRule;
use Illuminate\Database\Seeder;

class PointRulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $communityCategory = PointCategory::updateOrCreate(
            ['name' => 'community'],
            ['description' => 'Points earned from community posts, comments, and likes.']
        );

        $rules = [
            [
                'action_type' => 'community_post_created',
                'points' => 5,
                'max_per_day' => 5,
                'description' => 'Student creates a community post.',
            ],
            [
                'action_type' => 'community_comment_created',
                'points' => 3,
                'max_per_day' => 10,
                'description' => 'Student creates a community comment.',
            ],
            [
                'action_type' => 'community_post_liked_received',
                'points' => 1,
                'max_per_day' => 30,
                'description' => 'Student receives a like on a community post.',
            ],
            [
                'action_type' => 'community_comment_liked_received',
                'points' => 1,
                'max_per_day' => 30,
                'description' => 'Student receives a like on a community comment.',
            ],
        ];

        foreach ($rules as $ruleData) {
            // Create or update the rule
            $rule = PointRule::updateOrCreate(
                ['action_type' => $ruleData['action_type']],
                [
                    'points' => $ruleData['points'],
                    'max_per_day' => $ruleData['max_per_day'],
                    'is_active' => true,
                ]
            );

            // Link the rule to the category via PointActionType
            PointActionType::updateOrCreate(
                [
                    'point_rule_id' => $rule->id,
                    'point_category_id' => $communityCategory->id,
                ],
                [
                    'description' => $ruleData['description'],
                ]
            );
        }
    }
}
