<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_menus') || !Schema::hasTable('admin_subs')) return;
        $menuId = DB::table('admin_menus')->where('name', 'gudang')->orWhere('label', 'GUDANG')->value('id');
        if (!$menuId) return;
        DB::table('admin_subs')->updateOrInsert(
            ['link' => '/gudang/retur-pembelian'],
            [
                'menu_id' => $menuId,
                'name' => 'retur_pembelian',
                'label' => 'Retur Pembelian',
                'icon' => 'assignment_return',
                'urut' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_subs')) {
            DB::table('admin_subs')->where('link', '/gudang/retur-pembelian')->delete();
        }
    }
};
