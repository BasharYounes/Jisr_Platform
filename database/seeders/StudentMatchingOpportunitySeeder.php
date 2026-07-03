<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentMatchingOpportunitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder creates an opportunity (job or training) that matches the
     * skill set of a demo student. It demonstrates how to attach the same
     * skills the student possesses to the newly created opportunity.
     */
    public function run()
    {
        // Retrieve a demo student (user). Adjust the query as needed.
        $student = User::where('email', 'student@example.com')->first();
        if (! $student) {
            // If the demo student does not exist, create one.
            $student = User::factory()->create([
                'email' => 'student@example.com',
                'name' => 'Demo Student',
                'password' => bcrypt('password'),
            ]);
        }

        // Ensure the student has some skills. If not, attach a few sample skills.
        if ($student->skills()->count() === 0) {
            $sampleSkills = Skill::inRandomOrder()->take(3)->pluck('id')->toArray();
            $student->skills()->attach($sampleSkills);
        }

        // Get the skill IDs the student possesses.
        $skillIds = $student->skills()->pluck('id')->toArray();

        // Create a company for the opportunity (replace with an existing one if needed).
        $company = Company::first();
        if (! $company) {
            $company = Company::factory()->create([
                'name' => 'Demo Company',
                'email' => 'info@democompany.com',
            ]);
        }

        // Create the opportunity.
        $opportunity = Opportunity::create([
            'company_id' => $company->id,
            'title' => 'Training: Matching Your Skills',
            'description' => 'A training program tailored to the skills you already have.',
        ]);

        // Attach the same skills to the opportunity with pivot data.
        $pivotData = [];
        foreach ($skillIds as $skillId) {
            $pivotData[$skillId] = [
                'required_level' => 1,
                'mandatory' => true,
                'weight' => 1,
            ];
        }
        $opportunity->skills()->attach($pivotData);
    }
}
