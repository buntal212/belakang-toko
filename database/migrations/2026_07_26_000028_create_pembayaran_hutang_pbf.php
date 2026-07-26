<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tpenerimaan', function (Blueprint $table) {
            $table->decimal('dibayar', 20, 2)->default(0)->after('grandtotal');
            $table->decimal('sisa_hutang', 20, 2)->default(0)->after('dibayar');
            $table->index(['supplier_id', 'cara_bayar', 'sisa_hutang'], 'penerimaan_hutang_pbf_idx');
        });
        DB::table('tpenerimaan')->where('cara_bayar', 'HUTANG')->update([
            'dibayar' => 0,
            'sisa_hutang' => DB::raw('grandtotal'),
        ]);
        DB::table('tpenerimaan')->where('cara_bayar', '!=', 'HUTANG')->update([
            'dibayar' => DB::raw('grandtotal'),
            'sisa_hutang' => 0,
        ]);

        Schema::create('tpembayaran_pbf', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_pembayaran', 40)->unique();
            $table->dateTime('tanggal');
            $table->foreignId('supplier_id')->constrained('msupplier');
            $table->decimal('nominal', 20, 2);
            $table->string('metode_pembayaran', 20);
            $table->text('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['supplier_id', 'tanggal']);
        });
        Schema::create('tpembayaran_pbf_rinci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembayaran_pbf_id')->constrained('tpembayaran_pbf')->cascadeOnDelete();
            $table->foreignId('penerimaan_id')->constrained('tpenerimaan');
            $table->decimal('nominal', 20, 2);
            $table->timestamps();
            $table->unique(['pembayaran_pbf_id', 'penerimaan_id'], 'bayar_pbf_faktur_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpembayaran_pbf_rinci');
        Schema::dropIfExists('tpembayaran_pbf');
        Schema::table('tpenerimaan', function (Blueprint $table) {
            $table->dropIndex('penerimaan_hutang_pbf_idx');
            $table->dropColumn(['dibayar', 'sisa_hutang']);
        });
    }
};
