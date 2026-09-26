<?php

namespace App\Console\Commands;

use App\Services\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class InventoryConcurrencyProbe extends Command
{
    protected $signature = 'inventory:concurrency-probe {variant} {key} {--start-at= : Unix timestamp có microseconds dùng làm barrier}';

    protected $description = 'Tiến trình nội bộ dùng để thử khóa tồn kho đồng thời';

    public function handle(InventoryService $inventory): int
    {
        $this->waitForBarrier();

        try {
            DB::transaction(fn () => $inventory->applyOnce(
                (int) $this->argument('variant'),
                -1,
                (string) $this->argument('key'),
                'concurrency_probe',
                null,
                'Kiểm tra cạnh tranh tồn kho',
            ), 3);

            return self::SUCCESS;
        } catch (ValidationException) {
            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Lỗi cạnh tranh ngoài dự kiến: '.class_basename($exception));

            return 2;
        }
    }

    private function waitForBarrier(): void
    {
        $startAt = (float) $this->option('start-at');
        if ($startAt <= 0) {
            return;
        }

        // Cap the wait so this internal command can never be held indefinitely
        // by malformed input.
        $deadline = min($startAt, microtime(true) + 5);
        while (($remaining = $deadline - microtime(true)) > 0) {
            usleep((int) min(100_000, max(1, $remaining * 1_000_000)));
        }
    }
}
