<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatbotConversation extends Model
{
    use HasFactory, SoftDeletes;

    public const MODE_PLATFORM_HELP = 'platform_help';

    public const MODE_SKILLS_MARKET_ANALYSIS = 'skills_market_analysis';

    public const MODE_OPPORTUNITY_MATCHING = 'opportunity_matching';

    protected $fillable = [
        'student_id',
        'mode',
        'title',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public static function allowedModes(): array
    {
        return [
            self::MODE_PLATFORM_HELP,
            self::MODE_SKILLS_MARKET_ANALYSIS,
            self::MODE_OPPORTUNITY_MATCHING,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatbotMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatbotMessage::class, 'conversation_id')->latestOfMany();
    }
}
