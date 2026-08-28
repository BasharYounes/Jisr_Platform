<?php

namespace Database\Seeders;

use App\Models\AssessmentSession;
use App\Models\CV;
use App\Models\CVAnalysis;
use App\Models\CVExtractedSkill;
use App\Models\UserSkill;
use App\Services\CV\CVTextExtractionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GraduationDemoCVSeeder extends Seeder
{
    private const STUDENT_EMAIL = 'leleen830@gmail.com';

    private const CAREER_PATH_NAME = 'Backend Developer';

    private const FIXTURE_PATH =
        'database/data/Jisr_Demo_Student_CV_Leleen_Ahmad.docx';

    private const STORAGE_PATH =
        'cvs/graduation-demo-leleen-ahmad.docx';

    private const DEMO_MODEL_VERSION =
        'prepared-demo-cv-analysis-v1';

    private const EXTRACTED_SKILLS = [
        'Python' => [
            'initial_level' => 4.0,
            'confidence' => 0.97,
            'evidence' => (
                'مذكورة صراحة كمستوى متقدم، مع خبرة عملية في تطوير '
                .'أداة أتمتة باستخدام Python لمعالجة البيانات وتقليل '
                .'وقت معالجة التقارير بنسبة 35%.'
            ),
        ],

        'Flask' => [
            'initial_level' => 2.0,
            'confidence' => 0.96,
            'evidence' => (
                'مذكورة ضمن مهارات تطوير الويب، واستُخدمت في مشروع '
                .'Task Manager API لبناء واجهة برمجة تطبيقات مع '
                .'Authentication.'
            ),
        ],

        'SQL' => [
            'initial_level' => 3.0,
            'confidence' => 0.95,
            'evidence' => (
                'مذكورة صراحة مع PostgreSQL وMySQL، مع خبرة عملية '
                .'في تحسين استعلامات SQL لقاعدة بيانات العملاء '
                .'وزيادة سرعة استجابة البحث بنسبة 20%.'
            ),
        ],

        'Git' => [
            'initial_level' => 2.0,
            'confidence' => 0.94,
            'evidence' => (
                'مذكورة ضمن الأدوات، واستُخدمت لإدارة إصدارات '
                .'الكود وحفظ التغييرات بشكل دوري على GitHub.'
            ),
        ],

        'Docker' => [
            'initial_level' => 1.0,
            'confidence' => 0.92,
            'evidence' => (
                'مذكورة صراحة ضمن الأدوات التقنية في السيرة '
                .'الذاتية، دون دليل إضافي على استخدام متقدم.'
            ),
        ],
    ];

    public function run(): void
    {
        $this->ensureSafeEnvironment();

        $studentId = $this->resolveStudentId();
        $careerPathId = $this->resolveCareerPathId();
        $skillIds = $this->resolveSkillIds();

        $this->storeCvFixture();

        /*
         * Validate that the same production text-extraction service can read
         * the stored DOCX before creating database records.
         */
        $this->verifyStoredCvText();

        [$cv, $analysis] = DB::transaction(function () use (
            $studentId,
            $careerPathId,
            $skillIds
        ): array {
            /*
             * Keep only this CV as primary for the demo student.
             * Other CV records are not deleted.
             */
            CV::query()
                ->where('UserId', $studentId)
                ->where('FileUrl', '!=', self::STORAGE_PATH)
                ->update(['IsPrimary' => false]);

            $cv = CV::query()->updateOrCreate(
                [
                    'UserId' => $studentId,
                    'FileUrl' => self::STORAGE_PATH,
                ],
                [
                    'IsPrimary' => true,
                    'UploadedAt' => now()->subDays(6),
                ]
            );

            /*
             * Idempotency: delete only this seeder's previous prepared
             * analysis. Any real/live CV analyses for the same CV are kept.
             */
            CVAnalysis::query()
                ->where('CvId', $cv->CvID)
                ->where(
                    'AiModelVersion',
                    self::DEMO_MODEL_VERSION
                )
                ->delete();

            $extractedSkillsJson = collect(
                self::EXTRACTED_SKILLS
            )->map(
                fn (array $item, string $skillName) => [
                    'skill_name' => $skillName,
                    'evidence' => $item['evidence'],
                    'initial_level' => $item['initial_level'],
                    'confidence' => $item['confidence'],
                ]
            )->values()->all();

            $analysis = CVAnalysis::query()->create([
                'CvId' => $cv->CvID,
                'ExtractedSkillsJson' => $extractedSkillsJson,
                'MissingCriteriaJson' => [],
                'OverallScore' => 0,
                /*
                 * This is intentionally not "extraction-v1":
                 * the defense fixture is precomputed and does not call Gemini.
                 */
                'AiModelVersion' => self::DEMO_MODEL_VERSION,
                'AnalyzedAt' => now()->subDays(5),
            ]);

            foreach (
                self::EXTRACTED_SKILLS as $skillName => $item
            ) {
                CVExtractedSkill::query()->create([
                    'CVAnalysisID' => $analysis->CVAnalysisID,
                    'SkillID' => $skillIds[$skillName],
                    'RawSkillName' => $skillName,
                    'EvidenceText' => $item['evidence'],
                    'InitialLevel' => $item['initial_level'],
                    'ConfidenceScore' => $item['confidence'],
                    /*
                     * Preserve truthful provenance for the prepared fixture.
                     */
                    'ExtractionSource' => 'prepared_demo',
                ]);

                $this->syncCvBaselineUserSkill(
                    studentId: $studentId,
                    skillId: $skillIds[$skillName],
                    initialLevel: (float) $item['initial_level'],
                    confidence: (float) $item['confidence']
                );
            }

            /*
             * Link the already prepared completed assessment to this CV.
             * This closes the demo chain:
             * CV -> extracted skills -> assessment.
             */
            $assessment = AssessmentSession::query()
                ->where('UserID', $studentId)
                ->where('CareerPathID', $careerPathId)
                ->where(
                    'Status',
                    AssessmentSession::STATUS_COMPLETED
                )
                ->latest('AssessmentSessionID')
                ->first();

            if (! $assessment) {
                throw new RuntimeException(
                    'No completed Backend assessment was found for '
                    .self::STUDENT_EMAIL
                    .'. Run GraduationDemoAssessmentSeeder first.'
                );
            }

            $assessment->forceFill([
                'CvID' => $cv->CvID,
            ])->save();

            return [
                $cv->fresh(),
                $analysis->fresh([
                    'extractedSkills.skill',
                ]),
            ];
        });

        $this->verifyPersistedState(
            studentId: $studentId,
            careerPathId: $careerPathId,
            cv: $cv,
            analysis: $analysis
        );

        $this->printSummary(
            cv: $cv,
            analysis: $analysis
        );
    }

    private function ensureSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'GraduationDemoCVSeeder is allowed only in '
                .'local or testing environments.'
            );
        }
    }

    private function resolveStudentId(): int
    {
        $studentId = DB::table('users')
            ->where('email', self::STUDENT_EMAIL)
            ->value('id');

        if (! $studentId) {
            throw new RuntimeException(
                'Graduation demo student was not found: '
                .self::STUDENT_EMAIL
            );
        }

        return (int) $studentId;
    }

    private function resolveCareerPathId(): int
    {
        $careerPathId = DB::table('career_paths')
            ->where('Name', self::CAREER_PATH_NAME)
            ->value('CareerPathID');

        if (! $careerPathId) {
            throw new RuntimeException(
                'Backend Developer career path was not found.'
            );
        }

        return (int) $careerPathId;
    }

    /**
     * @return array<string, int>
     */
    private function resolveSkillIds(): array
    {
        $resolved = [];

        foreach (
            array_keys(self::EXTRACTED_SKILLS) as $skillName
        ) {
            $skillId = DB::table('skills')
                ->where('name', $skillName)
                ->value('id');

            if (! $skillId) {
                throw new RuntimeException(
                    "Required CV skill {$skillName} was not found."
                );
            }

            $resolved[$skillName] = (int) $skillId;
        }

        return $resolved;
    }

    private function storeCvFixture(): void
    {
        $fixturePath = base_path(self::FIXTURE_PATH);

        if (! is_file($fixturePath)) {
            throw new RuntimeException(
                'CV fixture was not found: '
                .self::FIXTURE_PATH
            );
        }

        $contents = file_get_contents($fixturePath);

        if ($contents === false || $contents === '') {
            throw new RuntimeException(
                'CV fixture could not be read or is empty.'
            );
        }

        if (
            ! Storage::disk('public')->put(
                self::STORAGE_PATH,
                $contents
            )
        ) {
            throw new RuntimeException(
                'Failed to store graduation demo CV '
                .'on the public disk.'
            );
        }

        if (
            ! Storage::disk('public')
                ->exists(self::STORAGE_PATH)
        ) {
            throw new RuntimeException(
                'Stored graduation demo CV was not found '
                .'on the public disk.'
            );
        }
    }

    private function verifyStoredCvText(): void
    {
        $absolutePath = Storage::disk('public')
            ->path(self::STORAGE_PATH);

        /** @var CVTextExtractionService $service */
        $service = app(CVTextExtractionService::class);

        $text = $service->extractFromPath($absolutePath);

        if (blank($text)) {
            throw new RuntimeException(
                'The production CVTextExtractionService could not '
                .'extract text from the stored demo DOCX.'
            );
        }

        foreach ([
            'leleen830@gmail.com',
            'Python',
            'Flask',
            'SQL',
            'Git',
            'Docker',
        ] as $expectedToken) {
            if (
                mb_stripos($text, $expectedToken)
                === false
            ) {
                throw new RuntimeException(
                    'Stored CV text verification failed. '
                    ."Missing token: {$expectedToken}"
                );
            }
        }
    }

    private function syncCvBaselineUserSkill(
        int $studentId,
        int $skillId,
        float $initialLevel,
        float $confidence
    ): void {
        $userSkill = UserSkill::query()->firstOrNew([
            'UserId' => $studentId,
            'SkillId' => $skillId,
        ]);

        /*
         * This seeder can be run either:
         * 1) before the assessment in a clean rebuild, or
         * 2) after the assessment while assembling the current demo DB.
         *
         * Never downgrade a post-assessment or human-verified skill back to
         * the CV baseline.
         */
        $protectedStatuses = [
            UserSkill::STATUS_CODE_TESTED,
            UserSkill::STATUS_SUPERVISOR_VERIFIED,
            UserSkill::STATUS_COMPANY_VERIFIED,
        ];

        $hasPostCvEvidence = $userSkill->exists
            && (
                $userSkill->Source === 'ai_assessment'
                || in_array(
                    $userSkill->VerificationStatus,
                    $protectedStatuses,
                    true
                )
            );

        if ($hasPostCvEvidence) {
            return;
        }

        $userSkill->ProficiencyLevel = max(
            1,
            min(5, (int) round($initialLevel))
        );
        $userSkill->ConfidenceScore = $confidence;
        $userSkill->Source = 'cv_analysis';
        $userSkill->Verified = false;
        $userSkill->VerificationStatus =
            UserSkill::STATUS_AI_ESTIMATED;
        $userSkill->VerifiedAt = null;
        $userSkill->VerifiedBy = null;
        $userSkill->save();
    }

    private function verifyPersistedState(
        int $studentId,
        int $careerPathId,
        CV $cv,
        CVAnalysis $analysis
    ): void {
        $primaryCv = CV::query()
            ->where('UserId', $studentId)
            ->where('IsPrimary', true)
            ->first();

        if (
            ! $primaryCv
            || (int) $primaryCv->CvID
                !== (int) $cv->CvID
        ) {
            throw new RuntimeException(
                'Graduation demo CV is not the primary CV.'
            );
        }

        $freshAnalysis = CVAnalysis::query()
            ->with('extractedSkills.skill')
            ->find($analysis->CVAnalysisID);

        if (! $freshAnalysis) {
            throw new RuntimeException(
                'Prepared CV analysis was not persisted.'
            );
        }

        if (
            $freshAnalysis->extractedSkills->count()
            !== count(self::EXTRACTED_SKILLS)
        ) {
            throw new RuntimeException(
                'Prepared CV extracted-skill count mismatch.'
            );
        }

        $actualSkills = $freshAnalysis
            ->extractedSkills
            ->pluck('skill.name')
            ->filter()
            ->values()
            ->all();

        $expectedSkills = array_keys(
            self::EXTRACTED_SKILLS
        );

        if (
            collect($actualSkills)->sort()->values()->all()
            !== collect($expectedSkills)->sort()->values()->all()
        ) {
            throw new RuntimeException(
                'Prepared CV extracted skills do not match '
                .'the expected demo set.'
            );
        }

        $assessment = AssessmentSession::query()
            ->where('UserID', $studentId)
            ->where('CareerPathID', $careerPathId)
            ->where(
                'Status',
                AssessmentSession::STATUS_COMPLETED
            )
            ->latest('AssessmentSessionID')
            ->first();

        if (
            ! $assessment
            || (int) $assessment->CvID
                !== (int) $cv->CvID
        ) {
            throw new RuntimeException(
                'Completed demo assessment is not linked '
                .'to the prepared CV.'
            );
        }

        /*
         * Critical regression guard:
         * Python/Flask/SQL/Git should still reflect post-assessment state if
         * the assessment seeder has already run.
         */
        $assessmentSkillNames = [
            'Python',
            'Flask',
            'SQL',
            'Git',
        ];

        foreach ($assessmentSkillNames as $skillName) {
            $skillId = DB::table('skills')
                ->where('name', $skillName)
                ->value('id');

            $skillSession = $assessment
                ->skillSessions()
                ->where('SkillID', $skillId)
                ->first();

            if (! $skillSession) {
                continue;
            }

            $userSkill = UserSkill::query()
                ->where('UserId', $studentId)
                ->where('SkillId', $skillId)
                ->first();

            if (
                ! $userSkill
                || $userSkill->Source !== 'ai_assessment'
            ) {
                throw new RuntimeException(
                    "{$skillName} UserSkill was unexpectedly "
                    .'downgraded from the assessment result.'
                );
            }

            $expectedProficiency = max(
                1,
                min(
                    5,
                    (int) round(
                        (float) $skillSession->FinalLevel
                    )
                )
            );

            if (
                (int) $userSkill->ProficiencyLevel
                !== $expectedProficiency
            ) {
                throw new RuntimeException(
                    "{$skillName} UserSkill proficiency changed "
                    .'after CV seeding.'
                );
            }
        }
    }

    private function printSummary(
        CV $cv,
        CVAnalysis $analysis
    ): void {
        $this->command?->newLine();
        $this->command?->info(
            'Graduation demo CV and prepared analysis '
            .'seeded successfully.'
        );

        $this->command?->line(
            'Student: '.self::STUDENT_EMAIL
            .' | CV #'.$cv->CvID
            .' | Analysis #'.$analysis->CVAnalysisID
        );

        $this->command?->line(
            'Stored file: '.self::STORAGE_PATH
            .' | Primary: YES'
        );

        $rows = $analysis->extractedSkills
            ->sortBy(
                fn ($item) => array_search(
                    $item->skill?->name,
                    array_keys(self::EXTRACTED_SKILLS),
                    true
                )
            )
            ->map(fn ($item) => [
                $item->skill?->name ?? $item->RawSkillName,
                number_format(
                    (float) $item->InitialLevel,
                    1
                ),
                number_format(
                    (float) $item->ConfidenceScore,
                    2
                ),
                $item->ExtractionSource,
            ])
            ->values()
            ->all();

        $this->command?->table(
            [
                'Extracted skill',
                'Initial level',
                'Confidence',
                'Source',
            ],
            $rows
        );

        $assessmentId = AssessmentSession::query()
            ->where('CvID', $cv->CvID)
            ->latest('AssessmentSessionID')
            ->value('AssessmentSessionID');

        $this->command?->info(
            'Assessment linked successfully: Session #'
            .$assessmentId.' -> CV #'.$cv->CvID
        );

        $this->command?->warn(
            'No Gemini call was executed. '
            .'AiModelVersion is '.self::DEMO_MODEL_VERSION
            .' and ExtractionSource is prepared_demo.'
        );
    }
}
