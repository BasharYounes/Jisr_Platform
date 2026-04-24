<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $guarded = [];
    public function user()
   {
    return $this->belongsTo(User::class);
   }

   public function likedByUsers()
   {
    return $this->belongsToMany(User::class, 'post_likes');
   }

   public function pointTransactions()
{
    return $this->morphMany(PointTransaction::class, 'reference');
}
}
