<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tsetoran_bendahara', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_setoran', 40)->unique();
            $table->dateTime('tanggal');
            $table->foreignId('kasir_id')->constrained('users');
            $table->dateTime('periode_mulai');
            $table->dateTime('periode_sampai');
            $table->unsignedInteger('jumlah_penjualan')->default(0);
            $table->unsignedInteger('jumlah_retur')->default(0);
            $table->decimal('penjualan_tunai', 20, 2)->default(0);
            $table->decimal('retur_tunai', 20, 2)->default(0);
            $table->decimal('seharusnya_disetor', 20, 2)->default(0);
            $table->decimal('nominal_disetor', 20, 2)->default(0);
            $table->decimal('selisih', 20, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kasir_id', 'tanggal']);
            $table->index(['tanggal', 'periode_sampai']);
        });

        Schema::table('tpenjualan', function (Blueprint $table) {
            $table->index(
                ['created_by', 'cara_bayar', 'tanggal'],
                'penjualan_kasir_bayar_tanggal_idx',
            );
        });

        Schema::table('tretur_penjualan', function (Blueprint $table) {
            $table->index(
                ['created_by', 'metode_pengembalian', 'tanggal'],
                'retur_kasir_metode_tanggal_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('tretur_penjualan', fn (Blueprint $table) =>
            $table->dropIndex('retur_kasir_metode_tanggal_idx')
        );
        Schema::table('tpenjualan', fn (Blueprint $table) =>
            $table->dropIndex('penjualan_kasir_bayar_tanggal_idx')
        );
        Schema::dropIfExists('tsetoran_bendahara');
    }
};
