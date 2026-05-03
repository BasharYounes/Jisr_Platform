<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationRequest extends Model
{
    protected $guarded = [];

   public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    public function cv()
    {
        return $this->belongsTo(Cv::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'tag_verification_request')
                    ->withTimestamps();
    }
}
