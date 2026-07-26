<?php

namespace App\Services\Stok;

use App\Models\Penjualan\PenjualanRinci;
use App\Models\Penjualan\Penjualan;
use App\Models\Penjualan\ReturPenjualan;
use App\Models\Penjualan\ReturPenjualanRinci;
use App\Models\Stok\Stok;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturPenjualanService
{
    public function simpan(array $data, int $userId): ReturPenjualan
    {
        return DB::transaction(function () use ($data, $userId) {
            $penjualan = Penjualan::lockForUpdate()->findOrFail($data['penjualan_id']);
            if ($penjualan->setoran_bendahara_id !== null) {
                throw ValidationException::withMessages([
                    'penjualan' => 'Transaksi sudah disetor ke bendahara dan tidak dapat diretur',
                ]);
            }
            if ($penjualan->tanggal->lt(now()->subDays(3))) {
                throw ValidationException::withMessages([
                    'penjualan' => 'Transaksi sudah melewati batas retur 3 hari',
                ]);
            }

            $retur = ReturPenjualan::create([
                'nomorretur' => $this->nextNumber(),
                'penjualan_id' => $data['penjualan_id'],
                'tanggal' => now(),
                'alasan' => $data['alasan'],
                'metode_pengembalian' => $data['metode_pengembalian'],
                'created_by' => $userId,
            ]);
            $jumlahItem = $total = 0;

            foreach ($data['items'] as $item) {
                $rinciJual = PenjualanRinci::with('barang')
                    ->lockForUpdate()
                    ->where('penjualan_id', $data['penjualan_id'])
                    ->findOrFail($item['penjualan_rinci_id']);
                $qty = (float) $item['qty'];
                $qtyTerjualKecil = (float) $rinciJual->qty_kecil;
                if ($qtyTerjualKecil <= 0) {
                    $qtyTerjualKecil = (float) collect($rinciJual->alokasi_fifo ?? [])->sum('qty');
                }
                $konversi = max((int) $rinciJual->konversi, 1);
                if ((float) $rinciJual->qty > 0 && $qtyTerjualKecil > 0) {
                    $konversi = max((int) round($qtyTerjualKecil / (float) $rinciJual->qty), 1);
                }
                $qtyKecil = $qty * $konversi;
                $sudahDiretur = (float) ReturPenjualanRinci::query()
                    ->where('penjualan_rinci_id', $rinciJual->id)
                    ->lockForUpdate()
                    ->sum('qty_kecil');
                $sisa = $qtyTerjualKecil - $sudahDiretur;

                if ($qtyKecil <= 0 || $qtyKecil > $sisa) {
                    throw ValidationException::withMessages([
                        'qty' => "Jumlah retur {$rinciJual->barang->namabarang} melebihi sisa yang dapat diretur",
                    ]);
                }

                $alokasi = $this->kembalikanKeLot($rinciJual, $qtyKecil, $retur, $userId);
                $subtotal = $qty * (float) $rinciJual->harga;
                $retur->rincian()->create([
                    'penjualan_rinci_id' => $rinciJual->id,
                    'barang_id' => $rinciJual->barang_id,
                    'qty' => $qty,
                    'qty_kecil' => $qtyKecil,
                    'konversi' => $konversi,
                    'satuan' => $rinciJual->satuan,
                    'harga' => $rinciJual->harga,
                    'subtotal' => $subtotal,
                    'alokasi_retur' => $alokasi,
                ]);
                $jumlahItem += $qtyKecil;
                $total += $subtotal;
            }

            $retur->update(['jumlahitem' => $jumlahItem, 'total' => $total]);
            return $retur->load([
                'penjualan:id,nomortransaksi,tanggal',
                'pengguna:id,name',
                'rincian.barang:id,kodebarang,namabarang',
            ]);
        });
    }

    public function hapus(ReturPenjualan $retur): void
    {
        DB::transaction(function () use ($retur) {
            $retur = ReturPenjualan::with('rincian')->lockForUpdate()->findOrFail($retur->id);

            foreach ($retur->rincian as $rinci) {
                foreach ($rinci->alokasi_retur ?? [] as $alokasi) {
                    $lot = Stok::lockForUpdate()->findOrFail($alokasi['stok_id']);
                    $qty = (float) $alokasi['qty'];
                    if ((float) $lot->qty_tersedia < $qty) {
                        throw ValidationException::withMessages([
                            'retur' => "Retur tidak dapat dihapus karena stok lot {$lot->kode_lot} sudah digunakan",
                        ]);
                    }
                    $sesudah = (float) $lot->qty_tersedia - $qty;
                    $lot->update([
                        'qty_keluar' => (float) $lot->qty_keluar + $qty,
                        'qty_tersedia' => $sesudah,
                        'nilai_tersedia' => $sesudah * (float) $lot->harga_per_unit,
                        'status' => $sesudah <= 0 ? 'HABIS' : 'TERSEDIA',
                    ]);
                }
            }

            \App\Models\Stok\StokMutasi::where('sumber_tipe', 'RETUR_PENJUALAN')
                ->where('sumber_id', $retur->id)->delete();
            $retur->delete();
        });
    }

    private function kembalikanKeLot(
        PenjualanRinci $rinciJual,
        float $qtyKecil,
        ReturPenjualan $retur,
        int $userId,
    ): array {
        $alokasiJual = collect($rinciJual->alokasi_fifo ?? []);
        $alokasiReturLama = ReturPenjualanRinci::where('penjualan_rinci_id', $rinciJual->id)
            ->lockForUpdate()->get()
            ->flatMap(fn ($rinci) => $rinci->alokasi_retur ?? [])
            ->groupBy('stok_id')
            ->map(fn ($rows) => (float) $rows->sum('qty'));
        $sisa = $qtyKecil;
        $hasil = [];

        foreach ($alokasiJual as $asal) {
            if ($sisa <= 0) break;
            $stokId = (int) $asal['stok_id'];
            $kapasitas = (float) $asal['qty'] - (float) ($alokasiReturLama[$stokId] ?? 0);
            if ($kapasitas <= 0) continue;
            $dikembalikan = min($kapasitas, $sisa);
            $lot = Stok::lockForUpdate()->findOrFail($stokId);
            $sebelum = (float) $lot->qty_tersedia;
            $sesudah = $sebelum + $dikembalikan;
            $lot->update([
                'qty_keluar' => max((float) $lot->qty_keluar - $dikembalikan, 0),
                'qty_tersedia' => $sesudah,
                'nilai_tersedia' => $sesudah * (float) $lot->harga_per_unit,
                'status' => 'TERSEDIA',
            ]);
            $lot->mutasi()->create([
                'kode_mutasi' => 'MUT-RET-'.Str::uuid(),
                'barang_id' => $rinciJual->barang_id,
                'tipe' => 'MASUK',
                'sumber_tipe' => 'RETUR_PENJUALAN',
                'sumber_id' => $retur->id,
                'nomor_referensi' => $retur->nomorretur,
                'tanggal_mutasi' => now(),
                'qty_masuk' => $dikembalikan,
                'saldo_sebelum' => $sebelum,
                'saldo_sesudah' => $sesudah,
                'harga_per_unit' => $lot->harga_per_unit,
                'nilai_mutasi' => $dikembalikan * (float) $lot->harga_per_unit,
                'keterangan' => 'Retur dari '.$rinciJual->penjualan?->nomortransaksi,
                'created_by' => $userId,
            ]);
            $hasil[] = ['stok_id' => $stokId, 'kode_lot' => $lot->kode_lot, 'qty' => $dikembalikan];
            $sisa -= $dikembalikan;
        }

        if ($sisa > 0) {
            throw ValidationException::withMessages(['stok' => 'Alokasi lot asal untuk retur tidak mencukupi']);
        }
        return $hasil;
    }

    private function nextNumber(): string
    {
        $prefix = 'RET-'.now()->format('Ymd').'-';
        $last = ReturPenjualan::where('nomorretur', 'like', $prefix.'%')->lockForUpdate()->max('nomorretur');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
