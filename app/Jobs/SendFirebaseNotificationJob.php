<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Notifications\FirebaseMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendFirebaseNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Initial attempt + two retries if Firebase fails temporarily.
     */
    public int $tries = 3;

    /**
     * Maximum execution time for one attempt.
     */
    public int $timeout = 60;

    /**
     * If the recipient is deleted before processing, discard the job safely.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public User $recipient,
        public string $title,
        public string $body,
        public array $data = [],
    ) {
        /*
         * Do not place the job in the queue until the database transaction
         * that created the notification has committed successfully.
         */
        $this->afterCommit();
    }

    /**
     * Retry delays in seconds.
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(FirebaseMessagingService $firebase): void
    {
        $firebase->sendToUser(
            recipient: $this->recipient,
            title: $this->title,
            body: $this->body,
            data: $this->data,
        );
    }

    /**
     * Runs only after all configured attempts fail.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Firebase notification job failed permanently.', [
            'recipient_id' => $this->recipient->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
