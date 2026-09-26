<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminCredentialReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotation_replaces_a_common_password_revokes_sessions_and_writes_a_private_one_time_file(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('password')]);
        DB::table('sessions')->insert([
            'id' => 'admin-old-session',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('test'),
            'last_activity' => now()->timestamp,
        ]);

        $output = storage_path('framework/testing/admin-password-rotation-'.uniqid().'.txt');
        File::delete($output);

        try {
            $this->artisan('admin:rotate-default-password', ['--output' => $output])->assertSuccessful();

            $this->assertFileExists($output);
            $this->assertSame(0600, fileperms($output) & 0777);
            $this->assertStringContainsString($admin->email.': ', File::get($output));
            $this->assertFalse(app(AdminCredentialReadiness::class)->usesKnownDefaultPassword($admin->fresh()));
            $this->assertDatabaseMissing('sessions', ['id' => 'admin-old-session']);
        } finally {
            File::delete($output);
        }
    }

    public function test_rotation_refuses_to_overwrite_an_existing_output_file_without_changing_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('admin123')]);
        $output = storage_path('framework/testing/existing-admin-password.txt');
        File::put($output, 'keep-me');

        try {
            $this->artisan('admin:rotate-default-password', ['--output' => $output])
                ->expectsOutput('Từ chối ghi đè file mật khẩu đã tồn tại. Hãy chọn đường dẫn mới.')
                ->assertFailed();

            $this->assertSame('keep-me', File::get($output));
            $this->assertTrue(Hash::check('admin123', $admin->fresh()->password));
        } finally {
            File::delete($output);
        }
    }
}
