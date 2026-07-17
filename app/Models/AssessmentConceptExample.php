<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentConceptExample extends Model
{
    protected $table = 'assessment_concept_examples';

    protected $primaryKey = 'ConceptExampleID';

    protected $fillable = [
        'ConceptID',
        'Language',
        'ExampleText',
        'MinimumSimilarity',
        'IsPositive',
        'IsActive',
    ];

    protected $casts = [
        'MinimumSimilarity' => 'decimal:4',
        'IsPositive' => 'boolean',
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
