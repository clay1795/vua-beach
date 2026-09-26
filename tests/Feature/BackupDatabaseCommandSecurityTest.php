<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupDatabaseCommandSecurityTest extends TestCase
{
    public function test_backup_refuses_a_directory_accessible_by_other_users(): void
    {
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.driver', 'mysql');

        $directory = storage_path('framework/testing/unsafe-backups-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($directory);
        chmod($directory, 0777);

        try {
            $this->artisan('db:backup', ['--path' => $directory])
                ->expectsOutput('Thư mục backup không được cấp quyền cho người dùng khác: '.$directory)
                ->assertExitCode(1);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
