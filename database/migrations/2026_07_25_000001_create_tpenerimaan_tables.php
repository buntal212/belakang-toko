<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tpenerimaan', function (Blueprint $table) {
            $table->id();
            $table->string('nomortransaksi', 30)->unique();
            $table->string('nomorfaktur', 100)->nullable();
            $table->date('tanggal');
            $table->date('tglfaktur')->nullable();
            $table->foreignId('supplier_id')->constrained('msupplier');
            $table->text('catatan')->nullable();
            $table->string('status', 20)->default('Draft');
            $table->unsignedInteger('jumlahitem')->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('diskon', 18, 2)->default(0);
            $table->decimal('pajak', 18, 2)->default(0);
            $table->decimal('grandtotal', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tpenerimaan_rinci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_id')->constrained('tpenerimaan')->cascadeOnDelete();
            $table->foreignId('barang_id')->constrained('mbarang');
            $table->decimal('qtybesar', 18, 2)->default(0);
            $table->unsignedInteger('isi')->default(1);
            $table->decimal('qtykecil', 18, 2)->default(0);
            $table->decimal('hargabeli', 18, 2)->default(0);
            $table->decimal('hargakecil', 18, 2)->default(0);
            $table->decimal('diskonpersen', 5, 2)->default(0);
            $table->decimal('diskonnominal', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamps();
            $table->unique(['penerimaan_id', 'barang_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpenerimaan_rinci');
        Schema::dropIfExists('tpenerimaan');
    }
};
