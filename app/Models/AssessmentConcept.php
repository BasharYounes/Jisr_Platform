<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentConcept extends Model
{
    protected $table = 'assessment_concepts';

    protected $primaryKey = 'ConceptID';

    protected $fillable = [
        'ConceptCode',
        'NameAr',
        'NameEn',
        'Description',
        'IsActive',
    ];

    protected $casts = [
        'IsActive' => 'boolean',
    ];

    public function aliases()
    {
        return $this->hasMany(
            AssessmentConceptAlias::class,
            'ConceptID',
            'ConceptID'
        );
    }

    public function examples()
    {
        return $this->hasMany(
            AssessmentConceptExample::class,
            'ConceptID',
            'ConceptID'
        );
    }

    public function triggeredContradictionRules()
    {
        return $this->hasMany(
            AssessmentContradictionRule::class,
            'TriggerConceptID',
            'ConceptID'
        );
    }

    public function evidence()
    {
        return $this->hasMany(
            AssessmentEvaluationEvidence::class,
            'ConceptID',
            'ConceptID'
        );
    }

    public function detectionPolicies()
    {
        return $this->hasMany(
            AssessmentConceptDetectionPolicy::class,
            'ConceptID',
            'ConceptID'
        );
    }
}
