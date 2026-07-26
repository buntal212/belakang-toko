<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tpenjualan', function (Blueprint $table) {
            $table->foreignId('pelanggan_id')
                ->nullable()
                ->after('tanggal')
                ->constrained('mpelanggan')
                ->nullOnDelete();
            $table->decimal('sisa_hutang', 20, 2)->default(0)->after('kembalian');
            $table->index(['pelanggan_id', 'cara_bayar']);
        });
    }

    public function down(): void
    {
        Schema::table('tpenjualan', function (Blueprint $table) {
            $table->dropIndex(['pelanggan_id', 'cara_bayar']);
            $table->dropConstrainedForeignId('pelanggan_id');
            $table->dropColumn('sisa_hutang');
        });
    }
};
