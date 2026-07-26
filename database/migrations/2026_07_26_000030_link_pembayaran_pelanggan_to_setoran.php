<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tpembayaran_pelanggan', function (Blueprint $table) {
            $table->foreignId('setoran_bendahara_id')
                ->nullable()
                ->after('created_by')
                ->constrained('tsetoran_bendahara')
                ->nullOnDelete();
            $table->index(
                ['setoran_bendahara_id', 'created_by', 'tanggal'],
                'pembayaran_pelanggan_setoran_kasir_tanggal_idx',
            );
        });

        Schema::table('tsetoran_bendahara', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_pembayaran_pelanggan')->default(0)->after('jumlah_retur');
            $table->decimal('pembayaran_pelanggan', 20, 2)->default(0)->after('retur_tunai');
        });
    }

    public function down(): void
    {
        Schema::table('tsetoran_bendahara', function (Blueprint $table) {
            $table->dropColumn(['jumlah_pembayaran_pelanggan', 'pembayaran_pelanggan']);
        });

        Schema::table('tpembayaran_pelanggan', function (Blueprint $table) {
            $table->dropIndex('pembayaran_pelanggan_setoran_kasir_tanggal_idx');
            $table->dropConstrainedForeignId('setoran_bendahara_id');
        });
    }
};
