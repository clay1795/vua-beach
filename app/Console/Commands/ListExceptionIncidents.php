<?php

namespace App\Console\Commands;

use App\Models\ExceptionIncident;
use Illuminate\Console\Command;

class ListExceptionIncidents extends Command
{
    protected $signature = 'vua-beach:incidents {--all : Bao gồm sự cố đã đóng}';

    protected $description = 'Liệt kê các exception đã được bộ theo dõi nội bộ gộp và làm sạch';

    public function handle(): int
    {
        $incidents = ExceptionIncident::query()
            ->when(! $this->option('all'), fn ($query) => $query->whereNull('resolved_at'))
            ->latest('last_seen_at')
            ->limit(50)
            ->get();

        if ($incidents->isEmpty()) {
            $this->info('Không có sự cố chưa xử lý.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Exception', 'Vị trí', 'Route', 'Số lần', 'Gần nhất', 'Đã đóng'],
            $incidents->map(fn (ExceptionIncident $incident): array => [
                $incident->id,
                $incident->exception_class,
                $incident->location,
                $incident->route,
                $incident->occurrences,
                $incident->last_seen_at?->toDateTimeString(),
                $incident->resolved_at?->toDateTimeString() ?: 'Chưa',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
