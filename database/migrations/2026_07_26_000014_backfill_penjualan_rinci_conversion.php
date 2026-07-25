<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tpenjualan_rinci')) return;

        DB::table('tpenjualan_rinci')
            ->where(function ($query) {
                $query->whereNull('qty_kecil')->orWhere('qty_kecil', '<=', 0);
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $alokasi = json_decode($row->alokasi_fifo ?: '[]', true) ?: [];
                    $qtyKecil = array_sum(array_map(
                        fn ($item) => (float) ($item['qty'] ?? 0),
                        $alokasi,
                    ));
                    $qty = (float) $row->qty;
                    $konversi = max((int) ($row->konversi ?? 1), 1);

                    if ($qtyKecil <= 0) {
                        $qtyKecil = $qty * $konversi;
                    }
                    if ($qty > 0 && $qtyKecil > 0) {
                        $konversi = max((int) round($qtyKecil / $qty), 1);
                    }

                    DB::table('tpenjualan_rinci')->where('id', $row->id)->update([
                        'qty_kecil' => $qtyKecil,
                        'konversi' => $konversi,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Backfill tidak dibatalkan agar data kuantitas transaksi lama tidak rusak.
    }
};
