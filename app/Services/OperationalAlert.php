<?php

namespace App\Services;

use App\Mail\OperationalAlertMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OperationalAlert
{
    private const ALLOWED_DETAILS = [
        'order_id',
        'return_id',
        'queue',
        'connection',
        'fingerprint',
        'occurrences',
        'route',
        'location',
    ];

    /** @param array<string, int|string|null> $details */
    public function report(
        string $event,
        string $source,
        Throwable|string $exception,
        array $details = [],
        string $logChannel = 'stack',
    ): void {
        $exceptionClass = $exception instanceof Throwable ? $exception::class : $exception;
        $safeDetails = array_intersect_key($details, array_flip(self::ALLOWED_DETAILS));
        $safeDetails = array_map(
            static fn (mixed $value): int|string|null => is_int($value) || $value === null
                ? $value
                : mb_strimwidth((string) $value, 0, 200, '…'),
            $safeDetails,
        );
        $context = [
            'event' => mb_strimwidth($event, 0, 100, '…'),
            'job' => mb_strimwidth($source, 0, 255, '…'),
            'exception' => mb_strimwidth($exceptionClass, 0, 255, '…'),
            ...$safeDetails,
        ];
        Log::channel($logChannel)->critical('Operational incident requires operator attention.', $context);

        $webhook = (string) config('services.alerts.webhook_url');
        if ($webhook !== '') {
            try {
                Http::asJson()->timeout(5)->post($webhook, [
                    'event' => $context['event'],
                    'app' => config('app.name'),
                    ...$context,
                ])->throw();
            } catch (Throwable $alertException) {
                $this->logDeliveryFailure($logChannel, 'webhook', $alertException);
            }
        }

        $recipient = (string) config('services.alerts.mail_to');
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($recipient)->send(new OperationalAlertMail(
                    $context['event'],
                    $context['job'],
                    $context['exception'],
                    $safeDetails,
                ));
            } catch (Throwable $alertException) {
                $this->logDeliveryFailure($logChannel, 'email', $alertException);
            }
        }
    }

    private function logDeliveryFailure(string $logChannel, string $channel, Throwable $exception): void
    {
        Log::channel($logChannel)->error('Could not deliver operational alert.', [
            'channel' => $channel,
            'exception' => $exception::class,
        ]);
    }
}
