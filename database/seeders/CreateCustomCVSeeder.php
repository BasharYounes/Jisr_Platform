<?php

namespace Database\Seeders;

use App\Models\CV;
use App\Models\CVAnalysis;
use App\Models\CVExtractedSkill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CreateCustomCVSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Find the student user
        $user = User::where('email', 'batoulsubuh@gmail.com')->first();

        if (! $user) {
            $this->command->error('Student user batoulsubuh@gmail.com not found. Please run CreateCustomStudentSeeder first.');

            return;
        }

        // 2. Create or update CV
        $cv = CV::updateOrCreate(
            [
                'UserId' => $user->id,
                'FileUrl' => 'cvs/batoul_cv.pdf',
            ],
            [
                'IsPrimary' => true,
                'UploadedAt' => now(),
            ]
        );

        // 3. Create CV Analysis
        $analysis = CVAnalysis::updateOrCreate(
            [
                'CvId' => $cv->CvID,
            ],
            [
                'ExtractedSkillsJson' => ['Python', 'Flask', 'SQL', 'Git'],
                'MissingCriteriaJson' => [],
                'OverallScore' => 85.50,
                'AiModelVersion' => 'gemini-1.5-pro',
                'AnalyzedAt' => now(),
            ]
        );

        // 4. Create Extracted Skills details
        // Get matching skill records from database
        $skills = DB::table('skills')
            ->whereIn('name', ['Python', 'Flask', 'SQL', 'Git'])
            ->get();

        // Clear old extracted skills first to avoid duplication
        CVExtractedSkill::where('CVAnalysisID', $analysis->CVAnalysisID)->delete();

        foreach ($skills as $skill) {
            CVExtractedSkill::create([
                'CVAnalysisID' => $analysis->CVAnalysisID,
                'SkillID' => $skill->id,
                'RawSkillName' => $skill->name.' Language/Framework',
                'EvidenceText' => 'Demonstrated proficiency in '.$skill->name.' through past project experience.',
                'InitialLevel' => 4.0,
                'ConfidenceScore' => 0.95,
                'ExtractionSource' => 'llm',
            ]);
        }

        $this->command->info('==================================================');
        $this->command->info('CV and CV Analysis successfully seeded for: '.$user->email);
        $this->command->info('CV ID: '.$cv->CvID);
        $this->command->info('Analysis ID: '.$analysis->CVAnalysisID);
        $this->command->info('==================================================');
    }
}
