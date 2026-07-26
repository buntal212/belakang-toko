<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_menus') || !Schema::hasTable('admin_subs')) return;
        $menuId = DB::table('admin_menus')->where('name', 'keuangan')->value('id');
        if (!$menuId) {
            $menuId = DB::table('admin_menus')->insertGetId([
                'name' => 'keuangan', 'label' => 'KEUANGAN', 'icon' => 'account_balance_wallet',
                'link' => null, 'urut' => 5, 'color' => '#0F766E',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('admin_subs')->updateOrInsert(
            ['link' => '/keuangan/pembayaran-hutang-pbf'],
            [
                'menu_id' => $menuId, 'name' => 'pembayaran_hutang_pbf',
                'label' => 'Pembayaran Hutang ke PBF', 'icon' => 'outgoing_mail',
                'urut' => 1, 'created_at' => now(), 'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('admin_menus')) return;
        $menuId = DB::table('admin_menus')->where('name', 'keuangan')->value('id');
        if ($menuId && Schema::hasTable('admin_subs')) DB::table('admin_subs')->where('menu_id', $menuId)->delete();
        if ($menuId) DB::table('admin_menus')->where('id', $menuId)->delete();
    }
};
