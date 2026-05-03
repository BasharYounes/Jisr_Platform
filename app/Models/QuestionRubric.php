<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionRubric extends Model
{
    protected $table = 'question_rubrics';
    protected $primaryKey = 'QuestionRubricID';

    protected $fillable = [
        'QuestionID',
        'CriterionName',
        'CriterionDescription',
        'MaxScore',
        'Weight',
        'KeywordsJson',
        'SampleGoodAnswer',
        'SampleBadAnswer',
        'OrderIndex',
    ];

    protected $casts = [
        'MaxScore' => 'decimal:2',
        'Weight' => 'decimal:2',
        'KeywordsJson' => 'array',
        'OrderIndex' => 'integer',
    ];

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class, 'QuestionID', 'QuestionID');
    }
}
