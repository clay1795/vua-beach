<?php

namespace Tests\Feature;

use App\Exceptions\WebhookConflictException;
use App\Models\WebhookReceipt;
use App\Services\WebhookIdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_processing_receipt_is_not_claimed_twice(): void
    {
        $payload = ['event' => 'same'];
        $this->processingReceipt($payload, now());
        $called = false;

        $result = app(WebhookIdempotencyService::class)->process('test', 'event-1', $payload, function () use (&$called): array {
            $called = true;

            return ['code' => 200, 'body' => ['message' => 'processed']];
        });

        $this->assertFalse($called);
        $this->assertTrue($result['replayed']);
        $this->assertSame('Already processing', $result['body']['message']);
        $this->assertSame(1, WebhookReceipt::firstOrFail()->attempts);
    }

    public function test_stale_processing_receipt_can_be_reclaimed_after_worker_crash(): void
    {
        config(['webhooks.processing_timeout_seconds' => 60]);
        $payload = ['event' => 'same'];
        $this->processingReceipt($payload, now()->subMinutes(2));

        $result = app(WebhookIdempotencyService::class)->process('test', 'event-1', $payload, fn (): array => [
            'code' => 202,
            'body' => ['message' => 'recovered'],
        ]);

        $receipt = WebhookReceipt::firstOrFail();
        $this->assertFalse($result['replayed']);
        $this->assertSame('recovered', $result['body']['message']);
        $this->assertSame('processed', $receipt->status);
        $this->assertSame(2, $receipt->attempts);
        $this->assertNotNull($receipt->processed_at);
    }

    public function test_nested_object_key_order_does_not_create_a_false_conflict(): void
    {
        $service = app(WebhookIdempotencyService::class);
        $first = [
            'order' => ['code' => 'VB-1', 'customer' => ['name' => 'An', 'phone' => '0900000000']],
            'items' => [['sku' => 'A', 'quantity' => 1]],
        ];
        $reordered = [
            'items' => [['quantity' => 1, 'sku' => 'A']],
            'order' => ['customer' => ['phone' => '0900000000', 'name' => 'An'], 'code' => 'VB-1'],
        ];

        $service->process('test', 'nested-order', $first, fn (): array => [
            'code' => 200,
            'body' => ['message' => 'processed'],
        ]);
        $result = $service->process('test', 'nested-order', $reordered, fn (): array => [
            'code' => 500,
            'body' => ['message' => 'must not run'],
        ]);

        $this->assertTrue($result['replayed']);
        $this->assertSame('processed', $result['body']['message']);
        $this->assertDatabaseCount('webhook_receipts', 1);
    }

    public function test_list_order_remains_part_of_the_webhook_identity(): void
    {
        $service = app(WebhookIdempotencyService::class);
        $service->process('test', 'ordered-items', ['items' => ['A', 'B']], fn (): array => [
            'code' => 200,
            'body' => ['message' => 'processed'],
        ]);

        $this->expectException(WebhookConflictException::class);
        $service->process('test', 'ordered-items', ['items' => ['B', 'A']], fn (): array => [
            'code' => 200,
            'body' => ['message' => 'must not run'],
        ]);
    }

    private function processingReceipt(array $payload, \DateTimeInterface $updatedAt): WebhookReceipt
    {
        ksort($payload);
        $receipt = WebhookReceipt::create([
            'provider' => 'test',
            'event_key' => 'event-1',
            'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'status' => 'processing',
        ]);
        WebhookReceipt::query()->whereKey($receipt->id)->update(['updated_at' => $updatedAt]);

        return $receipt;
    }
}
