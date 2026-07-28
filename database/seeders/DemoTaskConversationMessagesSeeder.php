<?php

namespace Database\Seeders;

use App\Models\CompanyTaskAssignment;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoTaskConversationMessagesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $conversations = Conversation::query()
                ->where('conversationable_type', (new CompanyTaskAssignment)->getMorphClass())
                ->with(['participantRecords'])
                ->latest()
                ->take(3)
                ->get();

            if ($conversations->isEmpty()) {
                throw new \RuntimeException(
                    'No task conversations found. Run DemoCompanyTaskWorkflowSeeder first.'
                );
            }

            foreach ($conversations as $index => $conversation) {
                $companyParticipant = $conversation->participantRecords
                    ->firstWhere('role', 'company');

                $studentParticipant = $conversation->participantRecords
                    ->firstWhere('role', 'student');

                if (! $companyParticipant || ! $studentParticipant) {
                    continue;
                }

                Message::query()->firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'sender_id' => $companyParticipant->user_id,
                        'content' => 'مرحباً، تم قبولك رسمياً لهذه المهمة. يمكنك البدء بالتنفيذ من اليوم.',
                    ],
                    [
                        'type' => 'text',
                        'read_at' => now()->subHours(5),
                        'created_at' => now()->subHours(6)->addMinutes($index),
                        'updated_at' => now()->subHours(6)->addMinutes($index),
                    ]
                );

                Message::query()->firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'sender_id' => $studentParticipant->user_id,
                        'content' => 'شكراً لكم، سأبدأ بمراجعة المطلوب وتجهيز الخطة.',
                    ],
                    [
                        'type' => 'text',
                        'read_at' => now()->subHours(4),
                        'created_at' => now()->subHours(5)->addMinutes($index),
                        'updated_at' => now()->subHours(5)->addMinutes($index),
                    ]
                );

                Message::query()->firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'sender_id' => $companyParticipant->user_id,
                        'content' => 'ممتاز. ركّز على جودة الـ API response واستخدم validation واضح.',
                    ],
                    [
                        'type' => 'text',
                        'read_at' => now()->subHours(3),
                        'created_at' => now()->subHours(4)->addMinutes($index),
                        'updated_at' => now()->subHours(4)->addMinutes($index),
                    ]
                );

                Message::query()->firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'sender_id' => $studentParticipant->user_id,
                        'content' => 'تمام، سأرسل أول تحديث للتقدم بعد الانتهاء من إعداد الـ endpoints الأساسية.',
                    ],
                    [
                        'type' => 'text',
                        'read_at' => null,
                        'created_at' => now()->subHours(3)->addMinutes($index),
                        'updated_at' => now()->subHours(3)->addMinutes($index),
                    ]
                );
            }
        });
    }
}
