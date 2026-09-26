<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--path= : Thư mục chứa file backup, mặc định storage/app/backups}
        {--keep= : Số bản backup gần nhất cần giữ, mặc định 14}';

    protected $description = 'Sao lưu cơ sở dữ liệu MySQL/MariaDB thành file SQL';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config('database.connections.'.$connection);
        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error('Lệnh này hiện hỗ trợ MySQL/MariaDB. Kết nối hiện tại: '.($config['driver'] ?? 'không xác định').'.');

            return self::FAILURE;
        }

        $directory = $this->option('path') ?: storage_path('app/backups');
        $directoryAlreadyExists = File::isDirectory($directory);
        File::ensureDirectoryExists($directory, 0700, true);
        if (! $directoryAlreadyExists) {
            File::chmod($directory, 0700);
        }

        $permissions = fileperms($directory);
        if ($permissions === false || ($permissions & 0007) !== 0) {
            $this->error('Thư mục backup không được cấp quyền cho người dùng khác: '.$directory);

            return self::FAILURE;
        }

        if (! is_writable($directory)) {
            $this->error('Không thể ghi vào thư mục backup: '.$directory);

            return self::FAILURE;
        }

        $file = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'database-'.now()->format('Ymd-His').'.sql';
        $binary = (string) config('database.backup.mysqldump_binary', 'mysqldump');
        $process = new Process([
            $binary,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--skip-routines',
            '--skip-events',
            '--default-character-set='.$config['charset'],
            $config['database'],
        ]);
        $process->setEnv(['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            File::delete($file);
            $this->error('Sao lưu thất bại. Hãy kiểm tra MYSQLDUMP_BINARY và kết nối database.');
            $this->line(mb_strimwidth(trim($process->getErrorOutput()), 0, 500, '…'));

            return self::FAILURE;
        }

        File::put($file, $process->getOutput());
        File::chmod($file, 0600);
        if (File::size($file) === 0) {
            File::delete($file);
            $this->error('Sao lưu không tạo được dữ liệu.');

            return self::FAILURE;
        }

        $keep = max(1, (int) ($this->option('keep') ?: config('database.backup.keep', 14)));
        $removed = collect(File::files($directory))
            ->filter(fn ($backup) => preg_match('/^database-\d{8}-\d{6}\.sql$/', $backup->getFilename()))
            ->sortByDesc(fn ($backup) => $backup->getMTime())
            ->slice($keep)
            ->each(fn ($backup) => File::delete($backup->getPathname()))
            ->count();

        $this->info('Đã sao lưu database: '.$file);
        if ($removed > 0) {
            $this->line("Đã xóa {$removed} bản backup cũ, giữ lại {$keep} bản gần nhất.");
        }

        return self::SUCCESS;
    }
}
