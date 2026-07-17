<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentEvaluationEvidence extends Model
{
    protected $table = 'assessment_evaluation_evidence';

    protected $primaryKey = 'EvidenceID';

    protected $fillable = [
        'EvaluationRunID',
        'ConceptID',
        'QuestionRubricID',
        'EvidenceText',
        'SentenceIndex',
        'Language',
        'DetectionMethod',
        'SimilarityScore',
        'IsNegated',
        'IsContradiction',
        'MetadataJson',
    ];

    protected $casts = [
        'SentenceIndex' => 'integer',
        'SimilarityScore' => 'decimal:4',
        'IsNegated' => 'boolean',
        'IsContradiction' => 'boolean',
        'MetadataJson' => 'array',
    ];

    public function evaluationRun()
    {
        return $this->belongsTo(
            AssessmentEvaluationRun::class,
            'EvaluationRunID',
            'EvaluationRunID'
        );
    }

    public function concept()
    {
        return $this->belongsTo(
            AssessmentConcept::class,
            'ConceptID',
            'ConceptID'
        );
    }

    public function rubric()
    {
        return $this->belongsTo(
            QuestionRubric::class,
            'QuestionRubricID',
            'QuestionRubricID'
        );
    }
}
