<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tpenjualan')) {
            Schema::create('tpenjualan', function (Blueprint $table) {
            $table->id();
            $table->string('nomortransaksi', 40)->unique();
            $table->dateTime('tanggal');
            $table->string('cara_bayar', 10);
            $table->decimal('jumlahitem', 18, 2)->default(0);
            $table->decimal('subtotal', 20, 2)->default(0);
            $table->decimal('diskon', 20, 2)->default(0);
            $table->decimal('grandtotal', 20, 2)->default(0);
            $table->decimal('dibayar', 20, 2)->default(0);
            $table->decimal('kembalian', 20, 2)->default(0);
            $table->decimal('hpp', 20, 2)->default(0);
            $table->string('status', 20)->default('SELESAI');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tanggal', 'status']);
            });
        }

        if (!Schema::hasTable('tpenjualan_rinci')) {
            Schema::create('tpenjualan_rinci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_id')->constrained('tpenjualan')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('mbarang');
            $table->decimal('qty', 18, 2);
            $table->string('satuan', 50)->nullable();
            $table->decimal('harga', 20, 2);
            $table->decimal('diskon', 20, 2)->default(0);
            $table->decimal('subtotal', 20, 2);
            $table->decimal('hpp', 20, 2)->default(0);
            $table->longText('alokasi_fifo')->nullable();
            $table->timestamps();
            $table->index(['penjualan_id', 'barang_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tpenjualan_rinci');
        Schema::dropIfExists('tpenjualan');
    }
};
