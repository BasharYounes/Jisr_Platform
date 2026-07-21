<?php

namespace App\Domains\Admin\Enums;

enum ManagedUserRole: string
{
    case Supervisor = 'supervisor';
    case SupervisorLead = 'supervisor_lead';
}
