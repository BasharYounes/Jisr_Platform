<?php

namespace App\Data\Complaints;

use InvalidArgumentException;

final readonly class ResolvedComplaintTarget
{
    private function __construct(
        public ?int $reportedUserId,
        public ?int $reportedMentorProfileId,
    ) {
        $hasUser = $this->reportedUserId !== null;
        $hasMentor = $this->reportedMentorProfileId !== null;

        if ($hasUser === $hasMentor) {
            throw new InvalidArgumentException(
                'A complaint target must be exactly one user or one mentor profile.'
            );
        }
    }

    public static function user(int $userId): self
    {
        return new self(
            reportedUserId: $userId,
            reportedMentorProfileId: null,
        );
    }

    public static function mentorProfile(int $mentorProfileId): self
    {
        return new self(
            reportedUserId: null,
            reportedMentorProfileId: $mentorProfileId,
        );
    }

    public function identity(): string
    {
        if ($this->reportedUserId !== null) {
            return 'user:'.$this->reportedUserId;
        }

        return 'mentor_profile:'.$this->reportedMentorProfileId;
    }
}
