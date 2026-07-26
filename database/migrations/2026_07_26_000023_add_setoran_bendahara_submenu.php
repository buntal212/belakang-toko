<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_menus') || !Schema::hasTable('admin_subs')) {
            return;
        }

        $menuId = DB::table('admin_menus')
            ->where('name', 'penjualan')
            ->orWhere('label', 'PENJUALAN')
            ->value('id');

        if (!$menuId) {
            return;
        }

        DB::table('admin_subs')->updateOrInsert(
            ['link' => '/penjualan/setoran-bendahara'],
            [
                'menu_id' => $menuId,
                'name' => 'setoran_bendahara',
                'label' => 'Setor ke Bendahara',
                'icon' => 'account_balance',
                'urut' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_subs')) {
            DB::table('admin_subs')
                ->where('link', '/penjualan/setoran-bendahara')
                ->delete();
        }
    }
};
