<?php

namespace App\Console\Commands;

use App\Services\ExceptionIncidentRecorder;
use Illuminate\Console\Command;
use RuntimeException;

class TestExceptionTracker extends Command
{
    protected $signature = 'vua-beach:incident-test';

    protected $description = 'Kiểm tra bộ theo dõi exception nội bộ và kênh cảnh báo';

    public function handle(ExceptionIncidentRecorder $recorder): int
    {
        if (! app()->environment(['staging', 'production'])) {
            $this->error('Bài kiểm tra sự cố chỉ được chạy trên Staging hoặc Production.');

            return self::FAILURE;
        }

        $incident = $recorder->record(new RuntimeException('Synthetic incident without production data.'), null, true);
        if ($incident === null || $incident->occurrences < 1) {
            $this->error('Bộ theo dõi exception không lưu được sự cố thử.');

            return self::FAILURE;
        }

        $incident->forceFill(['resolved_at' => now()])->save();
        $this->info('Đã lưu, cảnh báo và đóng sự cố thử thành công.');

        return self::SUCCESS;
    }
}
