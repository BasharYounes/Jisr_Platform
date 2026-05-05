<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CareerPathSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('career_paths')->updateOrInsert(
            ['Name' => 'Backend Developer'],
            [
                'Description' => 'Backend development track focused on server-side programming, APIs, databases, and version control.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
