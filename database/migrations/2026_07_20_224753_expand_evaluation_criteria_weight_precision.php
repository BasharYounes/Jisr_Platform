<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'evaluation_criteria',
            function (Blueprint $table): void {
                $table->decimal(
                    'weight',
                    5,
                    2
                )
                    ->default(1)
                    ->change();
            }
        );
    }

    public function down(): void
    {
        if (
            DB::table('evaluation_criteria')
                ->where('weight', '>', 99.99)
                ->exists()
        ) {
            throw new RuntimeException(
                'Cannot restore weight to DECIMAL(4,2) while values greater than 99.99 exist.'
            );
        }

        Schema::table(
            'evaluation_criteria',
            function (Blueprint $table): void {
                $table->decimal(
                    'weight',
                    4,
                    2
                )
                    ->default(1)
                    ->change();
            }
        );
    }
};
