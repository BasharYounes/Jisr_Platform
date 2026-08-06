<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotKnowledgeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'category',
        'question_ar',
        'question_en',
        'answer_ar',
        'answer_en',
        'keywords',
        'action',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'action' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
