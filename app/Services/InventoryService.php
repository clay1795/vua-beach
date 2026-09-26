<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Apply one stock movement. Call this inside a database transaction.
     * Reusing the same idempotency key is a successful no-op.
     */
    public function applyOnce(
        int $variantId,
        int $delta,
        string $idempotencyKey,
        string $reason,
        ?int $orderId = null,
        ?string $note = null,
    ): bool {
        if ($delta === 0) {
            return false;
        }

        // Always acquire the variant row before the idempotency row. Locking a
        // missing unique key first creates InnoDB gap locks; concurrent orders
        // with different keys can then deadlock while reaching for this same
        // variant in the opposite order.
        $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId);

        $existing = InventoryMovement::query()
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->exists();

        if ($existing) {
            return false;
        }

        $nextStock = $variant->stock + $delta;

        if ($nextStock < 0) {
            throw ValidationException::withMessages([
                'stock' => 'Sản phẩm không còn đủ tồn kho để thực hiện thao tác này.',
            ]);
        }

        $variant->update(['stock' => $nextStock]);
        InventoryMovement::create([
            'product_variant_id' => $variant->id,
            'order_id' => $orderId,
            'type' => $delta > 0 ? 'in' : 'out',
            'quantity' => abs($delta),
            'balance_after' => $nextStock,
            'reason' => $reason,
            'idempotency_key' => $idempotencyKey,
            'note' => $note,
        ]);
        Log::channel('inventory')->info('Inventory movement applied', [
            'variant_id' => $variant->id,
            'order_id' => $orderId,
            'delta' => $delta,
            'balance_after' => $nextStock,
            'reason' => $reason,
            'idempotency_key' => $idempotencyKey,
        ]);

        return true;
    }
}
