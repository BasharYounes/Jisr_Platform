<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'assessment_concepts',
            function (Blueprint $table): void {
                $table->text('ClaimAr')->nullable();
                $table->text('ClaimEn')->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'assessment_concepts',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'ClaimAr',
                    'ClaimEn',
                ]);
            }
        );
    }
};
