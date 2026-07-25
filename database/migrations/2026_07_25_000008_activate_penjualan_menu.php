<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_menus')) {
            return;
        }

        DB::table('admin_menus')
            ->where('name', 'penjualan')
            ->orWhere('label', 'PENJUALAN')
            ->update([
                'link' => '/penjualan',
                'icon' => 'point_of_sale',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_menus')) {
            DB::table('admin_menus')
                ->where('name', 'penjualan')
                ->orWhere('label', 'PENJUALAN')
                ->update(['link' => null, 'updated_at' => now()]);
        }
    }
};
