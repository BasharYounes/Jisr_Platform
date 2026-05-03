<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointRule extends Model
{
    protected $guarded = [];
    public function actionTypes()
    {
        return $this->hasMany(PointActionType::class);
    }
}
