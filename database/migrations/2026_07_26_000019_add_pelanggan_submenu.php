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
            ->where('name', 'master')
            ->orWhere('label', 'MASTER')
            ->value('id');

        if (!$menuId) {
            return;
        }

        $nextOrder = (int) DB::table('admin_subs')
            ->where('menu_id', $menuId)
            ->max('urut') + 1;

        DB::table('admin_subs')->updateOrInsert(
            ['link' => '/master/pelanggan'],
            [
                'menu_id' => $menuId,
                'name' => 'pelanggan',
                'label' => 'Pelanggan',
                'icon' => 'groups',
                'urut' => $nextOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_subs')) {
            DB::table('admin_subs')->where('link', '/master/pelanggan')->delete();
        }
    }
};
