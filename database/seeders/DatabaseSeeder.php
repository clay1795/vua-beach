<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::where('is_admin', true)->first();

        if ($admin) {
            $this->command?->info('Đã có quản trị viên; seeder không thay đổi tài khoản hoặc mật khẩu hiện tại.');
        } else {
            $initialPassword = (string) env('ADMIN_INITIAL_PASSWORD');
            if (strlen($initialPassword) < 16) {
                throw new \RuntimeException('Đặt ADMIN_INITIAL_PASSWORD tối thiểu 16 ký tự trước khi tạo quản trị viên đầu tiên.');
            }

            User::create([
                'name' => 'Quản trị viên',
                'username' => 'admin',
                'email' => 'admin@doboi.test',
                'password' => Hash::make($initialPassword),
                'phone' => '0900000000',
                'address' => 'Hà Nội',
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);
        }

        $this->call(SampleCatalogSeeder::class);
    }
}
