<?php

namespace App\Events;

use App\Models\ProjectAssignment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectAssignmentStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProjectAssignment $assignment,
        public string $oldStatus,
        public string $newStatus,
        public int $changedBy
    ) {}
}
