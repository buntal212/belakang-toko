<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tretur_pembelian')) {
            Schema::create('tretur_pembelian', function (Blueprint $table) {
            $table->id();
            $table->string('nomorretur', 40)->unique();
            $table->foreignId('penerimaan_id')->constrained('tpenerimaan');
            $table->foreignId('supplier_id')->nullable()->constrained('msupplier')->nullOnDelete();
            $table->dateTime('tanggal');
            $table->text('alasan');
            $table->decimal('jumlahitem', 18, 2)->default(0);
            $table->decimal('total', 20, 2)->default(0);
            $table->string('status', 20)->default('SELESAI');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tanggal', 'status']);
            });
        }

        if (!Schema::hasTable('tretur_pembelian_rinci')) {
            Schema::create('tretur_pembelian_rinci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retur_pembelian_id')->constrained('tretur_pembelian')->cascadeOnDelete();
            $table->foreignId('penerimaan_rinci_id')->constrained('tpenerimaan_rinci');
            $table->foreignId('stok_id')->constrained('stok');
            $table->foreignId('barang_id')->constrained('mbarang');
            $table->decimal('qty', 18, 2);
            $table->decimal('qty_kecil', 18, 2);
            $table->unsignedInteger('konversi')->default(1);
            $table->string('satuan', 50)->nullable();
            $table->decimal('harga_perolehan', 20, 4);
            $table->decimal('subtotal', 20, 2);
            $table->timestamps();
            $table->index(['penerimaan_rinci_id', 'barang_id'], 'retur_beli_rinci_barang_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tretur_pembelian_rinci');
        Schema::dropIfExists('tretur_pembelian');
    }
};
