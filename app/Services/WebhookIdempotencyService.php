<?php

namespace App\Services;

use App\Exceptions\WebhookConflictException;
use App\Models\WebhookReceipt;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class WebhookIdempotencyService
{
    /**
     * @param  callable():array{code:int,body:array<string,mixed>}  $callback
     * @return array{code:int,body:array<string,mixed>,replayed:bool}
     */
    public function process(string $provider, string $eventKey, array $payload, callable $callback): array
    {
        $hashPayload = Arr::except($payload, ['signature', 'secureHash', 'vnp_SecureHash']);
        $hashPayload = $this->canonicalize($hashPayload);
        $payloadHash = hash('sha256', json_encode(
            $hashPayload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        $receipt = WebhookReceipt::query()->firstOrCreate(
            ['provider' => $provider, 'event_key' => $eventKey],
            ['payload_hash' => $payloadHash, 'status' => 'processing'],
        );

        if (! hash_equals($receipt->payload_hash, $payloadHash)) {
            throw new WebhookConflictException('Webhook event key was reused with a different payload.');
        }

        if (! $receipt->wasRecentlyCreated && $receipt->status === 'processed') {
            return [
                'code' => $receipt->response_code ?? 200,
                'body' => $receipt->response_body ?? ['message' => 'Already processed'],
                'replayed' => true,
            ];
        }

        if (! $receipt->wasRecentlyCreated) {
            $query = WebhookReceipt::query()->whereKey($receipt->id);

            if ($receipt->status === 'processing') {
                $timeout = max(30, (int) config('webhooks.processing_timeout_seconds', 300));
                $query->where('status', 'processing')->where('updated_at', '<=', now()->subSeconds($timeout));
            } else {
                $query->where('status', $receipt->status);
            }

            $claimed = $query->update([
                'status' => 'processing',
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

            if ($claimed !== 1) {
                return ['code' => 200, 'body' => ['message' => 'Already processing'], 'replayed' => true];
            }
        }

        try {
            $result = $callback();
            DB::transaction(function () use ($receipt, $result): void {
                WebhookReceipt::query()->lockForUpdate()->findOrFail($receipt->id)->update([
                    'status' => 'processed',
                    'response_code' => $result['code'],
                    'response_body' => $result['body'],
                    'processed_at' => now(),
                ]);
            });

            return $result + ['replayed' => false];
        } catch (Throwable $exception) {
            $receipt->update(['status' => 'failed']);
            throw $exception;
        }
    }

    /**
     * Sort object-like arrays at every depth so the same JSON object produces
     * one hash regardless of provider key order. List order remains meaningful.
     */
    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
