<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class GraduationDemoCompanyAccountSeeder extends Seeder
{
    private const EMAIL = 'karamalah.kweader@gmail.com';

    private const NAME = 'NexaTech Solutions';

    private const INDUSTRY = 'Software Development';

    private const LOCATION = 'Remote / Hybrid';

    public function run(): void
    {
        $this->ensureSafeEnvironment();

        $password = $this->resolveDemoPassword();

        $result = DB::transaction(function () use ($password): array {
            Role::findOrCreate('company', 'web');

            app(PermissionRegistrar::class)
                ->forgetCachedPermissions();

            $user = User::query()
                ->where('email', self::EMAIL)
                ->first();

            if ($user !== null && ! $user->hasRole('company')) {
                throw new RuntimeException(
                    'The configured graduation company email already belongs '
                    .'to a non-company account. Refusing to change its role: '
                    .self::EMAIL
                );
            }

            if ($user === null) {
                $user = new User();
                $user->email = self::EMAIL;
            }

            /*
             * Dedicated local demo account.
             *
             * The password is read from .env and never hard-coded or printed
             * by this seeder.
             */
            $user->forceFill([
                'name' => self::NAME,
                'email' => self::EMAIL,
                'password' => Hash::make($password),
                'is_active' => true,
                'email_verified' => true,
                'is_verified_by_admin' => 'accepted',
                'deleted_at' => null,
            ]);

            $user->save();

            if (! $user->hasRole('company')) {
                $user->assignRole('company');
            }

            $company = $user->companies()
                ->orderBy('companies.id')
                ->first();

            if ($company === null) {
                $company = Company::query()->create([
                    'industry' => self::INDUSTRY,
                    'location' => self::LOCATION,
                    'website' => null,
                    'documentation_file' => null,
                ]);

                $user->companies()->attach(
                    $company->id,
                    [
                        'role' => 'owner',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            } else {
                /*
                 * Keep the existing company identity/id stable.
                 * Only normalize fields needed by the graduation demo.
                 */
                $company->forceFill([
                    'industry' => self::INDUSTRY,
                    'location' => self::LOCATION,
                ])->save();

                $user->companies()->updateExistingPivot(
                    $company->id,
                    [
                        'role' => 'owner',
                        'updated_at' => now(),
                    ]
                );
            }

            return [
                'user' => $user->fresh('roles'),
                'company' => $company->fresh(),
            ];
        });

        $this->verify(
            user: $result['user'],
            company: $result['company']
        );

        $this->printSummary(
            user: $result['user'],
            company: $result['company']
        );
    }

    private function ensureSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'GraduationDemoCompanyAccountSeeder is allowed only '
                .'in local or testing environments.'
            );
        }
    }

    private function resolveDemoPassword(): string
    {
        $password = (string) env(
            'GRADUATION_DEMO_PASSWORD',
            ''
        );

        if ($password === '') {
            throw new RuntimeException(
                'Missing GRADUATION_DEMO_PASSWORD in .env. '
                .'Set a local demo password before running this seeder. '
                .'The password is intentionally not hard-coded in source.'
            );
        }

        if (mb_strlen($password) < 8) {
            throw new RuntimeException(
                'GRADUATION_DEMO_PASSWORD must be at least 8 characters.'
            );
        }

        return $password;
    }

    private function verify(
        User $user,
        Company $company
    ): void {
        if ($user->email !== self::EMAIL) {
            throw new RuntimeException(
                'Graduation company email verification failed.'
            );
        }

        if (! $user->hasRole('company')) {
            throw new RuntimeException(
                'Graduation company role verification failed.'
            );
        }

        if (! (bool) $user->is_active) {
            throw new RuntimeException(
                'Graduation company account is not active.'
            );
        }

        if (
            $user->is_verified_by_admin !== 'accepted'
        ) {
            throw new RuntimeException(
                'Graduation company account is not admin-approved.'
            );
        }

        $pivot = DB::table('company_users')
            ->where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->first();

        if (! $pivot || $pivot->role !== 'owner') {
            throw new RuntimeException(
                'Graduation company owner pivot verification failed.'
            );
        }
    }

    private function printSummary(
        User $user,
        Company $company
    ): void {
        if ($this->command === null) {
            return;
        }

        $this->command->newLine();

        $this->command->info(
            'Graduation demo company account is ready.'
        );

        $this->command->table(
            ['Field', 'Value'],
            [
                ['User ID', $user->id],
                ['Company ID', $company->id],
                ['Display name', $user->name],
                ['Email', $user->email],
                ['Role', 'company'],
                ['Company pivot role', 'owner'],
                ['Industry', $company->industry],
                ['Location', $company->location],
                ['Active', 'YES'],
                ['Admin verified', 'accepted'],
            ]
        );

        $this->command->warn(
            'The demo password was read from '
            .'GRADUATION_DEMO_PASSWORD and was not printed.'
        );

        $this->command->info(
            'You can now rerun '
            .'GraduationDemoOpportunityMatchingSeeder.'
        );
    }
}
