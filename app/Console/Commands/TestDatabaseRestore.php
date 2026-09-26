<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class TestDatabaseRestore extends Command
{
    protected $signature = 'db:restore-test
        {--file= : File SQL cần thử khôi phục; mặc định dùng bản backup mới nhất}
        {--keep-test-database : Giữ database tạm để kiểm tra thủ công}';

    protected $description = 'Thử khôi phục backup vào database tạm, tuyệt đối không ghi đè database đang chạy';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config('database.connections.'.$connection);
        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error('Lệnh này chỉ hỗ trợ MySQL/MariaDB.');

            return self::FAILURE;
        }

        $backup = $this->resolveBackup();
        if ($backup === null) {
            return self::FAILURE;
        }

        $productionDatabase = (string) ($config['database'] ?? '');
        $testDatabase = 'vua_beach_restore_'.strtolower(Str::random(12));
        if ($productionDatabase === '' || $testDatabase === $productionDatabase || ! preg_match('/^[a-z0-9_]+$/', $testDatabase)) {
            $this->error('Không thể tạo tên database kiểm thử an toàn.');

            return self::FAILURE;
        }

        $created = false;
        try {
            $this->runMysql($config, "CREATE DATABASE `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $created = true;

            $input = fopen($backup, 'rb');
            if ($input === false) {
                throw new \RuntimeException('Không thể đọc file backup.');
            }

            try {
                $this->runMysql($config, null, $testDatabase, $input, 600);
            } finally {
                fclose($input);
            }

            $result = $this->runMysql(
                $config,
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$testDatabase}'",
                null,
                null,
                60,
                true,
            );
            $tableCount = (int) trim($result);
            if ($tableCount < 1) {
                throw new \RuntimeException('Backup khôi phục nhưng không có bảng dữ liệu.');
            }

            $this->info("Khôi phục thử thành công: {$tableCount} bảng từ ".basename($backup).'.');
            if ($this->option('keep-test-database')) {
                $created = false;
                $this->warn("Database tạm được giữ lại: {$testDatabase}");
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Khôi phục thử thất bại: '.mb_strimwidth($exception->getMessage(), 0, 300, '…'));

            return self::FAILURE;
        } finally {
            if ($created) {
                try {
                    $this->runMysql($config, "DROP DATABASE IF EXISTS `{$testDatabase}`");
                } catch (\Throwable) {
                    $this->warn("Không thể tự xóa database tạm: {$testDatabase}");
                }
            }
        }
    }

    private function resolveBackup(): ?string
    {
        $requested = $this->option('file');
        if ($requested) {
            $path = realpath((string) $requested);
        } else {
            $path = collect(File::files(storage_path('app/backups')))
                ->filter(fn ($file) => preg_match('/^database-\d{8}-\d{6}\.sql$/', $file->getFilename()))
                ->sortByDesc(fn ($file) => $file->getMTime())
                ->first()?->getRealPath();
        }

        if (! $path || ! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'sql' || filesize($path) === 0) {
            $this->error('Không tìm thấy file backup SQL hợp lệ.');

            return null;
        }

        return $path;
    }

    /** @param array<string, mixed> $config */
    private function runMysql(
        array $config,
        ?string $statement = null,
        ?string $database = null,
        mixed $input = null,
        int $timeout = 60,
        bool $returnOutput = false,
    ): string {
        $arguments = [
            (string) config('database.backup.mysql_binary', 'mysql'),
            '--host='.(string) $config['host'],
            '--port='.(string) $config['port'],
            '--user='.(string) $config['username'],
            '--default-character-set='.(string) $config['charset'],
            '--batch',
            '--skip-column-names',
        ];
        if ($database !== null) {
            $arguments[] = $database;
        }
        if ($statement !== null) {
            $arguments[] = '--execute='.$statement;
        }

        $process = new Process($arguments);
        $process->setEnv(['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
        $process->setTimeout($timeout);
        if ($input !== null) {
            $process->setInput($input);
        }
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(mb_strimwidth(trim($process->getErrorOutput()), 0, 300, '…'));
        }

        return $returnOutput ? $process->getOutput() : '';
    }
}
