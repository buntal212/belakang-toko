<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('stok') && Schema::hasColumn('stok', 'metadata')) {
            DB::statement('ALTER TABLE stok MODIFY metadata LONGTEXT NULL');
        }

        if (Schema::hasTable('stok_mutasi') && Schema::hasColumn('stok_mutasi', 'metadata')) {
            DB::statement('ALTER TABLE stok_mutasi MODIFY metadata LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('stok') && Schema::hasColumn('stok', 'metadata')) {
            DB::statement('ALTER TABLE stok MODIFY metadata JSON NULL');
        }

        if (Schema::hasTable('stok_mutasi') && Schema::hasColumn('stok_mutasi', 'metadata')) {
            DB::statement('ALTER TABLE stok_mutasi MODIFY metadata JSON NULL');
        }
    }
};
