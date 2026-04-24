<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    public function post()
    {
    return $this->belongsTo(Post::class);
    }

    public function user()
    {
    return $this->belongsTo(User::class);
    }

    public function likedByUsers()
    {
    return $this->belongsToMany(User::class, 'comment_likes');
    }

    public function pointTransactions()
{
    return $this->morphMany(PointTransaction::class, 'reference');
}
}
