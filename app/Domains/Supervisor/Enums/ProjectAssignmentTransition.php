<?php

namespace App\Domains\Supervisor\Enums;

class ProjectAssignmentTransition
{
    public static function allowed(): array
    {
        return [

            ProjectAssignmentStatus::PENDING->value => [
                ProjectAssignmentStatus::ASSIGNED->value,
            ],

            ProjectAssignmentStatus::ASSIGNED->value => [
                ProjectAssignmentStatus::IN_PROGRESS->value,
            ],

            ProjectAssignmentStatus::IN_PROGRESS->value => [
                ProjectAssignmentStatus::SUBMITTED->value,
            ],

            ProjectAssignmentStatus::SUBMITTED->value => [
                ProjectAssignmentStatus::UNDER_REVIEW->value,
                ProjectAssignmentStatus::REJECTED->value,
            ],

            ProjectAssignmentStatus::UNDER_REVIEW->value => [
                ProjectAssignmentStatus::COMPLETED->value,
                ProjectAssignmentStatus::REJECTED->value,
            ],

            ProjectAssignmentStatus::REJECTED->value => [
                ProjectAssignmentStatus::IN_PROGRESS->value,
            ],
        ];
    }

    public static function canTransition(
        string $from,
        string $to
    ): bool {
        return in_array(
            $to,
            self::allowed()[$from] ?? []
        );
    }
}
