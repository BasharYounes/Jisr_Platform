<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentConceptAlias extends Model
{
    protected $table = 'assessment_concept_aliases';

    protected $primaryKey = 'ConceptAliasID';

    protected $fillable = [
        'ConceptID',
        'Language',
        'AliasText',
        'NormalizedAlias',
        'MatchType',
        'MinimumSimilarity',
        'IsActive',
    ];

    protected $casts = [
        'MinimumSimilarity' => 'decimal:4',
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
