<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tretur_penjualan')) {
            Schema::create('tretur_penjualan', function (Blueprint $table) {
            $table->id();
            $table->string('nomorretur', 40)->unique();
            $table->foreignId('penjualan_id')->constrained('tpenjualan');
            $table->dateTime('tanggal');
            $table->text('alasan');
            $table->string('metode_pengembalian', 20)->default('CASH');
            $table->decimal('jumlahitem', 18, 2)->default(0);
            $table->decimal('total', 20, 2)->default(0);
            $table->string('status', 20)->default('SELESAI');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tanggal', 'status']);
            });
        }

        if (!Schema::hasTable('tretur_penjualan_rinci')) {
            Schema::create('tretur_penjualan_rinci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retur_penjualan_id')->constrained('tretur_penjualan')->cascadeOnDelete();
            $table->foreignId('penjualan_rinci_id')->constrained('tpenjualan_rinci');
            $table->foreignId('barang_id')->constrained('mbarang');
            $table->decimal('qty', 18, 2);
            $table->decimal('qty_kecil', 18, 2);
            $table->unsignedInteger('konversi')->default(1);
            $table->string('satuan', 50)->nullable();
            $table->decimal('harga', 20, 2);
            $table->decimal('subtotal', 20, 2);
            $table->longText('alokasi_retur')->nullable();
            $table->timestamps();
            $table->index(['penjualan_rinci_id', 'barang_id'], 'retur_jual_rinci_barang_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tretur_penjualan_rinci');
        Schema::dropIfExists('tretur_penjualan');
    }
};
