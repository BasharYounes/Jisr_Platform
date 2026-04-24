<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationCriteria extends Model
{
    protected $guarded = [];
    public function items()
{
    return $this->hasMany(EvaluationItem::class);
}
}
