<?php

namespace Tests\Feature\MarketAnalysis;

use App\Models\MarketJobPosting;
use App\Services\MarketAnalysis\MarketJobCareerPathClassifierService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketJobCareerPathClassifierServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'Backend Developer',
            'Frontend Developer',
            'Mobile Developer',
            'DevOps Engineer',
        ] as $pathName) {
            DB::table('career_paths')->updateOrInsert(
                ['Name' => $pathName],
                [
                    'Description' =>
                        'Temporary classifier test path.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function test_it_classifies_german_frontend_title(): void
    {
        $posting = $this->createPosting(
            'Frontend-/UI-Entwickler React'
        );

        $result = app(
            MarketJobCareerPathClassifierService::class
        )->classify($posting);

        $frontendPathId = (int) DB::table('career_paths')
            ->where('Name', 'Frontend Developer')
            ->value('CareerPathID');

        $this->assertSame('classified', $result['status']);

        $this->assertSame(
            'Frontend Developer',
            $result['career_path_name']
        );

        $this->assertSame(
            $frontendPathId,
            (int) $posting->fresh()->career_path_id
        );
    }

    public function test_it_classifies_using_weighted_skill_evidence(): void
    {
        $posting = $this->createPosting(
            'General Software Engineer'
        );

        $this->addSkillEvidence(
            posting: $posting,
            pathName: 'Frontend Developer',
            skillName: 'Frontend Core Skill One',
            weight: 1.0,
            isCore: true,
        );

        $this->addSkillEvidence(
            posting: $posting,
            pathName: 'Frontend Developer',
            skillName: 'Frontend Core Skill Two',
            weight: 0.9,
            isCore: true,
        );

        $result = app(
            MarketJobCareerPathClassifierService::class
        )->classify($posting);

        $this->assertSame('classified', $result['status']);

        $this->assertSame(
            'Frontend Developer',
            $result['career_path_name']
        );

        $this->assertSame(3.8, $result['score']);
    }

    public function test_it_marks_close_paths_as_ambiguous(): void
    {
        $posting = $this->createPosting(
            'Backend Developer and Frontend Developer'
        );

        $result = app(
            MarketJobCareerPathClassifierService::class
        )->classify($posting);

        $this->assertSame('ambiguous', $result['status']);
        $this->assertNull($result['career_path_id']);
        $this->assertNull($posting->fresh()->career_path_id);
    }

    public function test_it_marks_non_technical_title_as_out_of_scope(): void
    {
        $posting = $this->createPosting(
            'Sales Manager'
        );

        $result = app(
            MarketJobCareerPathClassifierService::class
        )->classify($posting);

        $this->assertSame(
            'out_of_scope',
            $result['status']
        );

        $this->assertNull($result['career_path_id']);
    }

    public function test_it_marks_missing_evidence_as_insufficient(): void
    {
        $posting = $this->createPosting(
            'General Office Position'
        );

        $result = app(
            MarketJobCareerPathClassifierService::class
        )->classify($posting);

        $this->assertSame(
            'insufficient_evidence',
            $result['status']
        );

        $this->assertNull($result['career_path_id']);
    }

    private function createPosting(
        string $title
    ): MarketJobPosting {
        $externalId = 'classifier-test-' . uniqid();

        return MarketJobPosting::create([
            'source_type' => 'test',
            'source_name' => 'phpunit',
            'external_id' => $externalId,
            'title' => $title,
            'description' =>
                'Temporary job posting for classifier tests.',
            'company_name' => 'Test Company',
            'location' => 'Remote',
            'language' => 'en',
            'career_path_id' => null,
            'published_at' => now(),
            'imported_at' => now(),
            'status' => 'active',
            'content_hash' => hash(
                'sha256',
                $externalId
            ),
        ]);
    }

    private function addSkillEvidence(
        MarketJobPosting $posting,
        string $pathName,
        string $skillName,
        float $weight,
        bool $isCore,
    ): void {
        $normalizedName =
            strtolower(
                str_replace(' ', '_', $skillName)
            )
            . '_'
            . uniqid();

        $skillId = DB::table('skills')->insertGetId([
            'name' => $skillName,
            'category' => 'Test Skill',
            'normalized_name' => $normalizedName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $aliasId = DB::table(
            'skill_aliases'
        )->insertGetId([
            'SkillID' => $skillId,
            'Alias' => $skillName,
            'LanguageCode' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $careerPathId = (int) DB::table('career_paths')
            ->where('Name', $pathName)
            ->value('CareerPathID');

        DB::table('career_path_skills')->insert([
            'CareerPathID' => $careerPathId,
            'SkillID' => $skillId,
            'RequiredLevel' => 2.0,
            'Weight' => $weight,
            'IsCore' => $isCore,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(
            'market_job_posting_skill_occurrences'
        )->insert([
            'market_job_posting_id' => $posting->id,
            'skill_id' => $skillId,
            'skill_alias_id' => $aliasId,
            'matched_text' => $skillName,
            'language' => 'en',
            'confidence' => 1.00,
            'extraction_method' => 'alias_match',
            'context' => $skillName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_explicit_unsupported_title_overrides_generic_skill_votes(): void
    {
        $posting = $this->createPosting(
            'Senior Data Science/ML Engineer'
        );

        /*
        * These generic skills create enough Backend points,
        * but they must not override the explicit Data Science title.
        */
        $this->addSkillEvidence(
            posting: $posting,
            pathName: 'Backend Developer',
            skillName: 'Generic Backend Vote One',
            weight: 1.0,
            isCore: true,
        );

        $this->addSkillEvidence(
            posting: $posting,
            pathName: 'Backend Developer',
            skillName: 'Generic Backend Vote Two',
            weight: 0.9,
            isCore: true,
        );

        $result = app(
            MarketJobCareerPathClassifierService::class
        )->classify($posting);

        $this->assertSame(
            'out_of_scope',
            $result['status']
        );

        $this->assertNull($result['career_path_id']);
        $this->assertNull($posting->fresh()->career_path_id);

        /*
        * Confirms that technical votes existed and the
        * out-of-scope decision was intentional.
        */
        $this->assertSame(3.8, $result['score']);
    }

    public function test_it_classifies_cloud_architect_as_devops(): void
    {
        $posting = $this->createPosting(
            'Senior Microsoft Cloud Transformation Architect'
        );

        $result = app(
            MarketJobCareerPathClassifierService::class
        )->classify($posting);

        $devOpsPathId = (int) DB::table('career_paths')
            ->where('Name', 'DevOps Engineer')
            ->value('CareerPathID');

        $this->assertSame('classified', $result['status']);

        $this->assertSame(
            'DevOps Engineer',
            $result['career_path_name']
        );

        $this->assertSame(
            $devOpsPathId,
            (int) $posting->fresh()->career_path_id
        );
    }

    public function test_it_marks_known_unsupported_titles_as_out_of_scope(): void
    {
        $titles = [
            'Senior Consultant Azure Data Engineer',
            'IT Support Specialist 2nd Level',
            'IT Projektmanager Softwareentwicklung',
            'SAP Inhouse Consultant Business Partner',
            'Personalsachbearbeiter Zeitwirtschaft',
            'Haustechniker Gebäudemanagement',
            'Senior Microsoft AI & Copilot Engineer',
            'Senior SAP BTP Developer',
            'Softwareentwickler C++ / Qt',
            'System Ingenieur- Integration & Tests / Funkkommunikationssysteme',
        ];

        foreach ($titles as $title) {
            $posting = $this->createPosting($title);

            $result = app(
                MarketJobCareerPathClassifierService::class
            )->classify($posting);

            $this->assertSame(
                'out_of_scope',
                $result['status'],
                'Unexpected classification for title: ' . $title
            );

            $this->assertNull(
                $result['career_path_id'],
                'Unexpected path for title: ' . $title
            );
        }
    }

    public function test_full_stack_title_remains_ambiguous_even_with_stronger_votes(): void
    {
        $posting = $this->createPosting(
            'Senior Full Stack Java Entwickler'
        );

        $this->addSkillEvidence(
            posting: $posting,
            pathName: 'Frontend Developer',
            skillName: 'Full Stack Frontend Vote One',
            weight: 1.0,
            isCore: true,
        );

        $this->addSkillEvidence(
            posting: $posting,
            pathName: 'Frontend Developer',
            skillName: 'Full Stack Frontend Vote Two',
            weight: 0.9,
            isCore: true,
        );

        $result = app(
            MarketJobCareerPathClassifierService::class
        )->classify($posting);

        $this->assertSame('ambiguous', $result['status']);
        $this->assertNull($result['career_path_id']);
        $this->assertNull($posting->fresh()->career_path_id);
        $this->assertSame(3.8, $result['score']);
    }

    public function test_it_classifies_system_and_storage_roles_as_devops(): void
    {
        $titles = [
            'Systemadministrator (m/w/d)',
            'Senior Cloud Native Storage Engineer Ceph',
        ];

        foreach ($titles as $title) {
            $posting = $this->createPosting($title);

            $result = app(
                MarketJobCareerPathClassifierService::class
            )->classify($posting);

            $this->assertSame(
                'classified',
                $result['status'],
                'Unexpected status for: ' . $title
            );

            $this->assertSame(
                'DevOps Engineer',
                $result['career_path_name'],
                'Unexpected path for: ' . $title
            );
        }
    }
}
