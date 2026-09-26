<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'ready_to_pick' => ['picking', 'money_collect_picking'],
            'shipping' => ['picked', 'storing', 'transporting', 'sorting', 'delivering', 'money_collect_delivering'],
            'returning' => ['return', 'waiting_to_return', 'return_transporting'],
            'cancelled' => ['cancel'],
        ] as $canonical => $providerStates) {
            DB::table('orders')->whereIn('shipping_status', $providerStates)->update([
                'shipping_status' => $canonical,
            ]);
        }
    }

    public function down(): void
    {
        // Việc chuẩn hóa gộp nhiều trạng thái GHN tương đương nên không thể khôi
        // phục chính xác raw status cũ. Giữ trạng thái canonical khi rollback.
    }
};
