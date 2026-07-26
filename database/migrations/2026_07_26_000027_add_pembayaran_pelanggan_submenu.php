<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_menus') || !Schema::hasTable('admin_subs')) return;
        $menuId = DB::table('admin_menus')
            ->where('name', 'penjualan')->orWhere('label', 'PENJUALAN')->value('id');
        if (!$menuId) return;

        DB::table('admin_subs')->updateOrInsert(
            ['link' => '/penjualan/pembayaran-pelanggan'],
            [
                'menu_id' => $menuId,
                'name' => 'pembayaran_pelanggan',
                'label' => 'Pembayaran Pelanggan',
                'icon' => 'payments',
                'urut' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_subs')) {
            DB::table('admin_subs')->where('link', '/penjualan/pembayaran-pelanggan')->delete();
        }
    }
};
