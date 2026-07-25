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

        $gudangId = DB::table('admin_menus')
            ->where('name', 'gudang')
            ->orWhere('label', 'GUDANG')
            ->value('id');

        if (!$gudangId) {
            return;
        }

        DB::table('admin_subs')->updateOrInsert(
            ['link' => '/gudang/stok'],
            [
                'menu_id' => $gudangId,
                'name' => 'stok',
                'label' => 'Stok Barang',
                'icon' => 'inventory_2',
                'urut' => 2,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_subs')) {
            DB::table('admin_subs')->where('link', '/gudang/stok')->delete();
        }
    }
};
