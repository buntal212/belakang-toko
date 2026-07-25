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

        $laporanId = DB::table('admin_menus')
            ->where('name', 'laporan')
            ->orWhere('label', 'LAPORAN')
            ->value('id');

        if (!$laporanId) {
            $laporanId = DB::table('admin_menus')->insertGetId([
                'name' => 'laporan',
                'label' => 'LAPORAN',
                'icon' => 'assessment',
                'link' => null,
                'urut' => 4,
                'color' => '#F59E0B',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('admin_menus')->where('id', $laporanId)->update([
                'label' => 'LAPORAN',
                'icon' => 'assessment',
                'link' => null,
                'updated_at' => now(),
            ]);
        }

        DB::table('admin_subs')
            ->where('link', '/gudang/stok')
            ->update([
                'menu_id' => $laporanId,
                'name' => 'stok_barang',
                'label' => 'Stok Barang',
                'icon' => 'inventory_2',
                'link' => '/laporan/stok-barang',
                'urut' => 1,
                'updated_at' => now(),
            ]);

        DB::table('admin_subs')->updateOrInsert(
            ['link' => '/laporan/stok-barang'],
            [
                'menu_id' => $laporanId,
                'name' => 'stok_barang',
                'label' => 'Stok Barang',
                'icon' => 'inventory_2',
                'urut' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('admin_menus') || !Schema::hasTable('admin_subs')) {
            return;
        }

        $gudangId = DB::table('admin_menus')
            ->where('name', 'gudang')
            ->orWhere('label', 'GUDANG')
            ->value('id');

        if ($gudangId) {
            DB::table('admin_subs')
                ->where('link', '/laporan/stok-barang')
                ->update([
                    'menu_id' => $gudangId,
                    'name' => 'stok',
                    'label' => 'Stok Barang',
                    'icon' => 'inventory_2',
                    'link' => '/gudang/stok',
                    'urut' => 2,
                    'updated_at' => now(),
                ]);
        }

        $laporanId = DB::table('admin_menus')->where('name', 'laporan')->value('id');

        if ($laporanId && !DB::table('admin_subs')->where('menu_id', $laporanId)->exists()) {
            DB::table('admin_menus')->where('id', $laporanId)->delete();
        }
    }
};
