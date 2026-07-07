<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_comment_id',
        'content',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_comment_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_comment_id')
            ->oldest();
    }

    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comment_likes', 'comment_id', 'user_id')
            ->withTimestamps();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommentLike::class, 'comment_id');
    }

    public function pointTransactions(): MorphMany
    {
        return $this->morphMany(PointTransaction::class, 'reference');
    }
}
