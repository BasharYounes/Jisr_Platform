<?php

namespace App\Models;

use App\Enums\SupervisorSpecialization;
use Illuminate\Database\Eloquent\Model;

class SupervisorProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'specialization' => SupervisorSpecialization::class,
            'is_volunteer' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
