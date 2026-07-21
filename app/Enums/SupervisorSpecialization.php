<?php

namespace App\Enums;

enum SupervisorSpecialization: string
{
    case Backend = 'backend';
    case Frontend = 'frontend';
    case Flutter = 'flutter';
    case ArtificialIntelligence = 'ai';
    case DevOps = 'devops';
}
