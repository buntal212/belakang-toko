<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tpembayaran_pelanggan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pembayaran', 40)->unique();
            $table->dateTime('tanggal');
            $table->foreignId('pelanggan_id')->constrained('mpelanggan');
            $table->decimal('nominal', 20, 2);
            $table->string('metode_pembayaran', 20);
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['pelanggan_id', 'tanggal']);
        });

        Schema::create('tpembayaran_pelanggan_rinci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_pelanggan_id')
                ->constrained('tpembayaran_pelanggan')->cascadeOnDelete();
            $table->foreignId('penjualan_id')->constrained('tpenjualan');
            $table->decimal('nominal', 20, 2);
            $table->timestamps();
            $table->unique(['pembayaran_pelanggan_id', 'penjualan_id'], 'bayar_pelanggan_nota_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpembayaran_pelanggan_rinci');
        Schema::dropIfExists('tpembayaran_pelanggan');
    }
};
