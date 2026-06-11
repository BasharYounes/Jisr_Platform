<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $guarded = [];

    public function conversationable()
    {
        return $this->morphTo();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['role', 'last_read_at'])
            ->withTimestamps();
    }

    public function participantRecords()
    {
        return $this->hasMany(ConversationParticipant::class);
    }
}
