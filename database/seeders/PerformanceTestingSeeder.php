<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PerformanceTestingSeeder extends Seeder
{
    public function run(): void
    {
        $studentRole = Role::findByName('student', 'web');

        // لدينا مسبقاً 100 طالب + شركة واحدة.
        // نريد 10,000 مستخدم إجمالاً:
        // 9,999 students + 1 company.
        for ($i = 101; $i <= 9999; $i++) {

            $email = "performance.student.{$i}@jisr.test";

            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::factory()->create([
                    'name' => "Performance Student {$i}",
                    'email' => $email,
                ]);
            }

            if (! $user->hasRole('student')) {
                $user->assignRole($studentRole);
            }

            $user->studentProfile()->firstOrCreate(
                [],
                [
                    'university' => 'Performance University',
                    'major' => 'Computer Science',
                    'graduation_year' => 2026,
                ]
            );

            if ($i % 1000 === 0) {
                $this->command->info("Prepared {$i} students...");
            }
        }

        $this->command->info('Performance dataset completed.');
    }
}
