<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class TestInventoryConcurrency extends Command
{
    protected $signature = 'inventory:concurrency-test
        {--workers=20 : Số tiến trình mua đồng thời (2-100)}
        {--stock=5 : Tồn kho thử nghiệm, phải nhỏ hơn số tiến trình}';

    protected $description = 'Chạy nhiều tiến trình đồng thời để chứng minh tồn kho không thể bị bán âm';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Từ chối chạy bài kiểm tra tranh chấp tồn kho trên Production. Hãy dùng database Staging riêng.');

            return self::FAILURE;
        }

        if (config('database.connections.'.config('database.default').'.driver') !== 'mysql') {
            $this->error('Bài kiểm tra cạnh tranh thật yêu cầu MySQL/MariaDB.');

            return self::FAILURE;
        }

        $workerCount = (int) $this->option('workers');
        $initialStock = (int) $this->option('stock');
        if ($workerCount < 2 || $workerCount > 100 || $initialStock < 1 || $initialStock >= $workerCount) {
            $this->error('--workers phải từ 2 đến 100 và --stock phải từ 1 đến workers - 1.');

            return self::FAILURE;
        }

        $marker = strtolower(Str::random(12));
        $category = Category::create(['name' => 'Concurrency '.$marker, 'slug' => 'concurrency-'.$marker]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Concurrency '.$marker, 'slug' => 'concurrency-product-'.$marker, 'price' => 1, 'description' => 'Bản ghi kiểm thử tạm thời', 'status' => 'inactive']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'CONC-'.strtoupper($marker), 'color' => 'Test', 'size' => 'T', 'stock' => $initialStock, 'is_active' => false]);

        try {
            // Every child waits for the same short barrier. This creates actual
            // lock contention instead of merely launching commands in sequence.
            $startAt = microtime(true) + 2;
            $processes = collect(range(1, $workerCount))->map(function (int $number) use ($variant, $marker, $startAt): Process {
                $process = new Process([
                    PHP_BINARY,
                    base_path('artisan'),
                    'inventory:concurrency-probe',
                    (string) $variant->id,
                    "concurrency:{$marker}:{$number}",
                    '--start-at='.(string) $startAt,
                    '--no-ansi',
                ], base_path());
                $process->setTimeout(30);
                $process->start();

                return $process;
            });
            $processes->each(fn (Process $process) => $process->wait());

            $successCount = $processes->filter(fn (Process $process) => $process->isSuccessful())->count();
            $outOfStockCount = $processes->filter(fn (Process $process) => $process->getExitCode() === self::FAILURE)->count();
            $unexpectedFailures = $processes->filter(fn (Process $process) => ! in_array($process->getExitCode(), [self::SUCCESS, self::FAILURE], true));
            $movementCount = InventoryMovement::query()->where('product_variant_id', $variant->id)->where('reason', 'concurrency_probe')->count();
            $remaining = (int) $variant->fresh()->stock;
            if ($successCount !== $initialStock
                || $outOfStockCount !== $workerCount - $initialStock
                || $unexpectedFailures->isNotEmpty()
                || $movementCount !== $initialStock
                || $remaining !== 0) {
                $this->error("Không đạt: success={$successCount}, out_of_stock={$outOfStockCount}, unexpected={$unexpectedFailures->count()}, movements={$movementCount}, stock={$remaining}.");
                $unexpectedFailures->each(function (Process $process): void {
                    $detail = trim($process->getErrorOutput().' '.$process->getOutput());
                    if ($detail !== '') {
                        $this->line(mb_strimwidth($detail, 0, 300, '…'));
                    }
                });

                return self::FAILURE;
            }

            $failedCount = $workerCount - $successCount;
            $this->info("Đạt: {$workerCount} tiến trình tranh {$initialStock} sản phẩm; {$successCount} thành công, {$failedCount} bị từ chối và tồn kho dừng ở 0.");

            return self::SUCCESS;
        } finally {
            DB::transaction(function () use ($variant, $product, $category): void {
                InventoryMovement::query()->where('product_variant_id', $variant->id)->delete();
                $variant->delete();
                $product->delete();
                $category->delete();
            });
        }
    }
}
