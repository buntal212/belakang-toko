<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tpenjualan', function (Blueprint $table) {
            $table->foreignId('setoran_bendahara_id')
                ->nullable()
                ->after('sisa_hutang')
                ->constrained('tsetoran_bendahara')
                ->nullOnDelete();
            $table->index(['setoran_bendahara_id', 'created_by', 'tanggal'], 'penjualan_setoran_kasir_tanggal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tpenjualan', function (Blueprint $table) {
            $table->dropIndex('penjualan_setoran_kasir_tanggal_idx');
            $table->dropConstrainedForeignId('setoran_bendahara_id');
        });
    }
};
