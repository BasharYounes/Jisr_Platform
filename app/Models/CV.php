<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CV extends Model
{
    protected $table = 'c_v_s';

    protected $primaryKey = 'CvID';

    protected $fillable = [
        'UserId',
        'FileUrl',
        'IsPrimary',
        'UploadedAt',
    ];

    protected $casts = [
        'IsPrimary' => 'boolean',
        'UploadedAt' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserId', 'id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'cv_id', 'CvID');
    }
}
