<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CVExtractedSkill extends Model
{
    protected $table = 'cv_extracted_skills';
    protected $primaryKey = 'CVExtractedSkillID';

    protected $fillable = [
        'CVAnalysisID',
        'SkillID',
        'RawSkillName',
        'EvidenceText',
        'InitialLevel',
        'ConfidenceScore',
        'ExtractionSource',
    ];

    protected $casts = [
        'InitialLevel' => 'decimal:1',
        'ConfidenceScore' => 'decimal:2',
    ];

    public function skill()
    {
        return $this->belongsTo(Skill::class, 'SkillID', 'id');
    }
    public function analysis()
    {
        return $this->belongsTo(CVAnalysis::class, 'CVAnalysisID', 'CVAnalysisID');
    }

}
