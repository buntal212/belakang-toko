<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stok')) {
            Schema::create('stok', function (Blueprint $table) {
            $table->id();
            $table->string('kode_lot', 60)->unique();
            $table->foreignId('barang_id')->constrained('mbarang');
            $table->foreignId('supplier_id')->nullable()->constrained('msupplier')->nullOnDelete();
            $table->foreignId('penerimaan_id')->constrained('tpenerimaan');
            $table->foreignId('penerimaan_rinci_id')->unique()->constrained('tpenerimaan_rinci');
            $table->date('tanggal_masuk');
            $table->date('tanggal_kadaluarsa')->nullable();
            $table->decimal('qty_masuk', 18, 2);
            $table->decimal('qty_keluar', 18, 2)->default(0);
            $table->decimal('qty_tersedia', 18, 2);
            $table->string('satuan', 50)->nullable();
            $table->decimal('harga_per_unit', 18, 4);
            $table->decimal('nilai_awal', 20, 2);
            $table->decimal('nilai_tersedia', 20, 2);
            $table->string('status', 20)->default('TERSEDIA');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barang_id', 'status', 'tanggal_masuk'], 'stok_fifo_idx');
            $table->index(['tanggal_kadaluarsa', 'status']);
            $table->index(['supplier_id', 'tanggal_masuk']);
            });
        }

        if (!Schema::hasTable('stok_mutasi')) {
            Schema::create('stok_mutasi', function (Blueprint $table) {
            $table->id();
            $table->string('kode_mutasi', 80)->unique();
            $table->foreignId('stok_id')->constrained('stok');
            $table->foreignId('barang_id')->constrained('mbarang');
            $table->string('tipe', 20);
            $table->string('sumber_tipe', 50);
            $table->unsignedBigInteger('sumber_id')->nullable();
            $table->string('nomor_referensi', 100)->nullable();
            $table->dateTime('tanggal_mutasi');
            $table->decimal('qty_masuk', 18, 2)->default(0);
            $table->decimal('qty_keluar', 18, 2)->default(0);
            $table->decimal('saldo_sebelum', 18, 2);
            $table->decimal('saldo_sesudah', 18, 2);
            $table->decimal('harga_per_unit', 18, 4);
            $table->decimal('nilai_mutasi', 20, 2);
            $table->text('keterangan')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barang_id', 'tanggal_mutasi']);
            $table->index(['tipe', 'tanggal_mutasi']);
            $table->index(['sumber_tipe', 'sumber_id']);
            });
        }

        if (!Schema::hasColumn('tpenerimaan', 'stok_terkirim_at')) {
            Schema::table('tpenerimaan', function (Blueprint $table) {
                $table->timestamp('stok_terkirim_at')->nullable()->after('created_by');
            });
        }
        if (!Schema::hasColumn('tpenerimaan', 'stok_terkirim_oleh')) {
            Schema::table('tpenerimaan', function (Blueprint $table) {
                $table->foreignId('stok_terkirim_oleh')
                    ->nullable()
                    ->after('stok_terkirim_at')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tpenerimaan', 'stok_terkirim_oleh')) {
            Schema::table('tpenerimaan', fn (Blueprint $table) =>
                $table->dropConstrainedForeignId('stok_terkirim_oleh')
            );
        }
        if (Schema::hasColumn('tpenerimaan', 'stok_terkirim_at')) {
            Schema::table('tpenerimaan', fn (Blueprint $table) =>
                $table->dropColumn('stok_terkirim_at')
            );
        }
        Schema::dropIfExists('stok_mutasi');
        Schema::dropIfExists('stok');
    }
};
