<?php

namespace Tests\Unit;

use App\Services\Assessment\LevelEstimationService;
use PHPUnit\Framework\TestCase;

class LevelEstimationServiceTest extends TestCase
{
    private LevelEstimationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LevelEstimationService();
    }

    public function test_excellent_score_increases_level(): void
    {
        $nextLevel = $this->service->resolveNextLevel(
            currentLevel: 3.00,
            normalizedScore: 0.95
        );

        $this->assertEquals(3.45, $nextLevel);
    }

    public function test_good_score_slightly_increases_level(): void
    {
        $nextLevel = $this->service->resolveNextLevel(
            currentLevel: 3.00,
            normalizedScore: 0.80
        );

        $this->assertEquals(3.25, $nextLevel);
    }

    public function test_medium_score_keeps_level_almost_stable(): void
    {
        $nextLevel = $this->service->resolveNextLevel(
            currentLevel: 3.00,
            normalizedScore: 0.60
        );

        $this->assertEquals(3.05, $nextLevel);
    }

    public function test_weak_score_decreases_level(): void
    {
        $nextLevel = $this->service->resolveNextLevel(
            currentLevel: 3.00,
            normalizedScore: 0.45
        );

        $this->assertEquals(2.80, $nextLevel);
    }

    public function test_very_weak_score_decreases_level_significantly(): void
    {
        $nextLevel = $this->service->resolveNextLevel(
            currentLevel: 3.00,
            normalizedScore: 0.20
        );

        $this->assertEquals(2.55, $nextLevel);
    }

    public function test_level_does_not_exceed_maximum_level(): void
    {
        $nextLevel = $this->service->resolveNextLevel(
            currentLevel: 4.90,
            normalizedScore: 0.95
        );

        $this->assertEquals(5.00, $nextLevel);
    }

    public function test_level_does_not_go_below_minimum_level(): void
    {
        $nextLevel = $this->service->resolveNextLevel(
            currentLevel: 1.10,
            normalizedScore: 0.10
        );

        $this->assertEquals(1.00, $nextLevel);
    }

    public function test_final_level_returns_starting_level_when_no_attempts_exist(): void
    {
        $finalLevel = $this->service->resolveFinalLevelFromAttempts(
            attempts: [],
            startingLevel: 3.20
        );

        $this->assertEquals(3.20, $finalLevel);
    }

    public function test_confidence_is_zero_when_no_attempts_exist(): void
    {
        $confidence = $this->service->calculateConfidenceFromAttempts([]);

        $this->assertEquals(0.00, $confidence);
    }

    public function test_confidence_increases_with_stable_attempts(): void
    {
        $attempts = [
            [
                'score' => 0.82,
                'question_level' => 3,
                'difficulty_weight' => 1.0,
            ],
            [
                'score' => 0.84,
                'question_level' => 3,
                'difficulty_weight' => 1.0,
            ],
            [
                'score' => 0.81,
                'question_level' => 4,
                'difficulty_weight' => 1.1,
            ],
            [
                'score' => 0.83,
                'question_level' => 4,
                'difficulty_weight' => 1.1,
            ],
            [
                'score' => 0.85,
                'question_level' => 5,
                'difficulty_weight' => 1.2,
            ],
        ];

        $confidence = $this->service->calculateConfidenceFromAttempts($attempts);

        $this->assertGreaterThanOrEqual(0.70, $confidence);
    }

    public function test_invalid_attempts_are_ignored(): void
    {
        $attempts = [
            [
                'score' => 'invalid',
                'question_level' => 3,
                'difficulty_weight' => 1.0,
            ],
            [
                'score' => 0.80,
                'question_level' => 3,
                'difficulty_weight' => 1.0,
            ],
        ];

        $confidence = $this->service->calculateConfidenceFromAttempts($attempts);

        $this->assertGreaterThan(0.00, $confidence);
    }
}
