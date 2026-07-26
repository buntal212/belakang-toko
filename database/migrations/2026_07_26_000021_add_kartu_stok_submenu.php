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
            ->where('name', 'laporan')
            ->orWhere('label', 'LAPORAN')
            ->value('id');

        if (!$menuId) {
            return;
        }

        DB::table('admin_subs')->updateOrInsert(
            ['link' => '/laporan/kartu-stok'],
            [
                'menu_id' => $menuId,
                'name' => 'kartu_stok',
                'label' => 'Kartu Stok',
                'icon' => 'receipt_long',
                'urut' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_subs')) {
            DB::table('admin_subs')
                ->where('link', '/laporan/kartu-stok')
                ->delete();
        }
    }
};
