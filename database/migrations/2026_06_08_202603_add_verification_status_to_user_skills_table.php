<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_skills', function (Blueprint $table) {
            $table->string('VerificationStatus', 50)
                ->default('self_declared')
                ->after('Verified');

            $table->timestamp('VerifiedAt')
                ->nullable()
                ->after('VerificationStatus');

            $table->unsignedBigInteger('VerifiedBy')
                ->nullable()
                ->after('VerifiedAt');

            $table->index('VerificationStatus');
        });

        /*
         * ترحيل البيانات القديمة بصورة محافظة:
         *
         * Verified = true لا يعني أننا نعرف من وثّقها.
         * لذلك نستخدم مصدر المهارة لتحديد الحالة قدر الإمكان.
         */
        DB::table('user_skills')
            ->where('Source', 'ai_assessment')
            ->update([
                'VerificationStatus' => 'ai_estimated',
            ]);
    }

    public function down(): void
    {
        Schema::table('user_skills', function (Blueprint $table) {
            $table->dropIndex(['VerificationStatus']);

            $table->dropColumn([
                'VerificationStatus',
                'VerifiedAt',
                'VerifiedBy',
            ]);
        });
    }
};
