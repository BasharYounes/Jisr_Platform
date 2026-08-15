<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentor_profiles', function (Blueprint $table) {
            // Company-nominated mentors may not have a Jisr user account.
            $table->unsignedBigInteger('user_id')->nullable()->change();

            $table->foreignId('submitted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->string('source', 32)->nullable();
            $table->string('status', 20)->default('pending');

            $table->string('full_name')->nullable();
            $table->string('email', 254)->nullable();
            $table->string('whatsapp_number', 50)->nullable();

            $table->string('specialization', 32)->nullable();
            $table->string('professional_title')->nullable();
            $table->text('bio')->nullable();

            $table->text('linkedin_url')->nullable();
            $table->text('github_or_portfolio_url')->nullable();
            $table->string('cv_path', 1024)->nullable();

            $table->json('mentoring_topics')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->unique('user_id');
            $table->unique(['company_id', 'email']);

            $table->index(['status', 'specialization']);
            $table->index('source');
        });

        $this->backfillLegacyProfiles();
    }

    public function down(): void
    {
        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->dropUnique('mentor_profiles_user_id_unique');
            $table->dropUnique('mentor_profiles_company_id_email_unique');

            $table->dropIndex('mentor_profiles_status_specialization_index');
            $table->dropIndex('mentor_profiles_source_index');

            $table->dropForeign(['submitted_by_user_id']);
            $table->dropForeign(['company_id']);
            $table->dropForeign(['reviewed_by']);

            $table->dropColumn([
                'submitted_by_user_id',
                'company_id',
                'source',
                'status',
                'full_name',
                'email',
                'whatsapp_number',
                'specialization',
                'professional_title',
                'bio',
                'linkedin_url',
                'github_or_portfolio_url',
                'cv_path',
                'mentoring_topics',
                'reviewed_by',
                'reviewed_at',
                'rejection_reason',
            ]);
        });

        Schema::table('mentor_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }

    private function backfillLegacyProfiles(): void
    {
        DB::table('mentor_profiles')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->eachById(function (object $profile): void {
                $user = DB::table('users')
                    ->where('id', $profile->user_id)
                    ->first(['name', 'email']);

                DB::table('mentor_profiles')
                    ->where('id', $profile->id)
                    ->update([
                        'submitted_by_user_id' => $profile->user_id,
                        'source' => 'self_application',
                        'status' => 'approved',
                        'full_name' => $user?->name,
                        'email' => $user?->email,
                    ]);
            }, 100, 'id', 'id');
    }
};
