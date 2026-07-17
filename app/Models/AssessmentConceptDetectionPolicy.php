<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentConceptDetectionPolicy extends Model
{
    protected $table = 'assessment_concept_detection_policies';

    protected $primaryKey = 'DetectionPolicyID';

    protected $fillable = [
        'ConceptID',
        'DetectorType',
        'Language',
        'ConfigurationJson',
        'IsActive',
    ];

    protected $casts = [
        'ConfigurationJson' => 'array',
        'IsActive' => 'boolean',
    ];

    public function concept()
    {
        return $this->belongsTo(
            AssessmentConcept::class,
            'ConceptID',
            'ConceptID'
        );
    }
}
