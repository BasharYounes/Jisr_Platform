<?php

namespace App\Events;

use App\Models\ProjectAssignment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectAssignmentReadyForEvaluation
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ProjectAssignment $assignment
    ) {}
}
