<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminCredentialReadiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RotateDefaultAdminPassword extends Command
{
    protected $signature = 'admin:rotate-default-password {--output= : File 0600 lưu mật khẩu mới một lần}';

    protected $description = 'Đổi các mật khẩu quản trị mặc định hoặc quá phổ biến sang mật khẩu ngẫu nhiên mạnh';

    public function handle(AdminCredentialReadiness $readiness): int
    {
        $admins = User::query()->where('is_admin', true)->get()
            ->filter(fn (User $admin) => $readiness->usesKnownDefaultPassword($admin));
        if ($admins->isEmpty()) {
            $this->info('Không có quản trị viên nào còn dùng mật khẩu mặc định.');

            return self::SUCCESS;
        }

        $output = (string) ($this->option('output') ?: storage_path('app/private/admin-password-rotation-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.txt'));
        if (File::exists($output) || is_link($output)) {
            $this->error('Từ chối ghi đè file mật khẩu đã tồn tại. Hãy chọn đường dẫn mới.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($output), 0700, true);
        File::chmod(dirname($output), 0700);
        $lines = [];
        $rotations = [];
        foreach ($admins as $admin) {
            $password = Str::password(24, true, true, true, false);
            $lines[] = $admin->email.': '.$password;
            $rotations[] = [$admin, $password];
        }

        $handle = @fopen($output, 'x');
        if ($handle === false) {
            $this->error('Không thể tạo file mật khẩu mới bằng chế độ độc quyền. Không có mật khẩu nào bị đổi.');

            return self::FAILURE;
        }

        try {
            File::chmod($output, 0600);
            if (fwrite($handle, implode(PHP_EOL, $lines).PHP_EOL) === false) {
                throw new \RuntimeException('Không thể ghi file mật khẩu.');
            }
            fclose($handle);
            $handle = null;

            DB::transaction(function () use ($rotations): void {
                foreach ($rotations as [$admin, $password]) {
                    $admin->forceFill(['password' => Hash::make($password)])->save();
                }

                if (Schema::hasTable((string) config('session.table', 'sessions'))) {
                    DB::table((string) config('session.table', 'sessions'))
                        ->whereIn('user_id', collect($rotations)->map(fn (array $rotation) => $rotation[0]->id))
                        ->delete();
                }
            });
        } catch (\Throwable) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            File::delete($output);
            $this->error('Không thể đổi mật khẩu quản trị an toàn; mọi thay đổi đã được hủy.');

            return self::FAILURE;
        }

        $this->info('Đã đổi '.count($lines).' mật khẩu mặc định. Thông tin đăng nhập một lần: '.$output);
        $this->warn('Hãy lưu vào trình quản lý mật khẩu rồi xóa file này.');

        return self::SUCCESS;
    }
}
