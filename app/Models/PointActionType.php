<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointActionType extends Model
{
    protected $guarded = [];
     public function rule()
    {
        return $this->belongsTo(PointRule::class, 'point_rule_id');
    }

    public function category()
    {
        return $this->belongsTo(PointCategory::class, 'point_category_id');
    }

    public function transactions()
    {
        return $this->hasMany(PointTransaction::class);
    }


}
