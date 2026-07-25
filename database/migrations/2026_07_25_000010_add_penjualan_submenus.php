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
            ->where('name', 'penjualan')
            ->orWhere('label', 'PENJUALAN')
            ->value('id');
        if (!$menuId) return;

        DB::table('admin_menus')->where('id', $menuId)->update(['link' => null, 'updated_at' => now()]);
        foreach ([
            ['link' => '/penjualan', 'name' => 'kasir', 'label' => 'Kasir Penjualan', 'icon' => 'point_of_sale', 'urut' => 1],
            ['link' => '/penjualan/list', 'name' => 'list_penjualan', 'label' => 'List Penjualan', 'icon' => 'receipt_long', 'urut' => 2],
        ] as $submenu) {
            DB::table('admin_subs')->updateOrInsert(
                ['link' => $submenu['link']],
                [...$submenu, 'menu_id' => $menuId, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_subs')) {
            DB::table('admin_subs')->whereIn('link', ['/penjualan', '/penjualan/list'])->delete();
        }
    }
};
