<?php

namespace App\Services\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseMessagingService
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {
    }

    /**
     * Send one Firebase notification to all registered Android devices
     * of the specified user.
     *
     * @return array{
     *     targeted: int,
     *     successful: int,
     *     failed: int,
     *     invalid_deleted: int,
     *     unknown: int
     * }
     */
    public function sendToUser(
        User $recipient,
        string $title,
        string $body,
        array $data = [],
    ): array {
        $tokens = $recipient->deviceTokens()
            ->where('platform', 'android')
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return [
                'targeted' => 0,
                'successful' => 0,
                'failed' => 0,
                'invalid_deleted' => 0,
                'unknown' => 0,
            ];
        }

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($this->normalizeData($data))
            ->withAndroidConfig(AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                ],
            ]));

        $successful = 0;
        $failed = 0;
        $invalidDeleted = 0;
        $unknown = 0;

        foreach (array_chunk($tokens, 500) as $tokenChunk) {
            try {
                $report = $this->messaging->sendMulticast($message, $tokenChunk);

                $successful += $report->successes()->count();
                $failed += $report->failures()->count();

                $invalidTokens = $report->invalidTokens();

                if ($invalidTokens !== []) {
                    $invalidDeleted += $recipient->deviceTokens()
                        ->whereIn('token', $invalidTokens)
                        ->delete();
                }

                $unknownTokens = $report->unknownTokens();

                if ($unknownTokens !== []) {
                    $unknown += count($unknownTokens);

                    Log::warning('FCM tokens are not recognized by the configured Firebase project.', [
                        'user_id' => $recipient->id,
                        'token_count' => count($unknownTokens),
                    ]);
                }
            } catch (MessagingException $exception) {
                Log::error('Firebase notification delivery failed.', [
                    'user_id' => $recipient->id,
                    'target_count' => count($tokenChunk),
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }

        return [
            'targeted' => count($tokens),
            'successful' => $successful,
            'failed' => $failed,
            'invalid_deleted' => $invalidDeleted,
            'unknown' => $unknown,
        ];
    }

    /**
     * FCM requires all data-payload values to be strings.
     */
    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[(string) $key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_null($value) => '',
                is_scalar($value) => (string) $value,
                default => json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),
            };
        }

        return $normalized;
    }
}
