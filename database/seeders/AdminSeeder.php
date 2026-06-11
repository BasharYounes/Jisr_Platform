<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminEmail = 'maysrbdran@gmail.com';
        $existingAdmin = User::where('email', $adminEmail)->first();

        if (! $existingAdmin) {

            $admin = User::create([
                'name' => 'BatoulSubuh',
                'email' => $adminEmail,
                'password' => Hash::make('toty1234'),
            ]);

            $role = Role::firstOrCreate([
                'name' => 'admin',
                'guard_name' => 'web',
            ]);
            $admin->assignRole($role);
        }
    }
}
