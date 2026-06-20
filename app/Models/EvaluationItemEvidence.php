<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationItemEvidence extends Model
{
    protected $table = 'evaluation_item_evidences';
    protected $fillable = [
        'evaluation_item_id',
        'disk',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function evaluationItem(): BelongsTo
    {
        return $this->belongsTo(
            EvaluationItem::class,
            'evaluation_item_id'
        );
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(
            EvaluationItemEvidence::class,
            'evaluation_item_id'
        );
    }
}
