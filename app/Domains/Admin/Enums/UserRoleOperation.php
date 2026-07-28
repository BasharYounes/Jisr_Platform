<?php

namespace App\Domains\Admin\Enums;

enum UserRoleOperation: string
{
    case Add = 'add';
    case Remove = 'remove';
}
