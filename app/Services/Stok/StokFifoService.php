<?php

namespace App\Services\Stok;

use App\Models\Gudang\Penerimaan;
use App\Models\Master\Barang;
use App\Models\Stok\Stok;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StokFifoService
{
    public function kirimPenerimaan(Penerimaan $penerimaan, int $userId): Penerimaan
    {
        return DB::transaction(function () use ($penerimaan, $userId) {
            $penerimaan = Penerimaan::with(['supplier', 'rincian.barang'])
                ->lockForUpdate()
                ->findOrFail($penerimaan->id);

            if ((int) $penerimaan->flaging === 1 || $penerimaan->stok_terkirim_at) {
                throw ValidationException::withMessages([
                    'penerimaan' => 'Penerimaan ini sudah pernah dikirim ke stok',
                ]);
            }
            if ($penerimaan->rincian->isEmpty()) {
                throw ValidationException::withMessages([
                    'penerimaan' => 'Penerimaan tidak memiliki rincian barang',
                ]);
            }

            foreach ($penerimaan->rincian as $rincian) {
                $barang = $rincian->barang;
                $qtyBesar = (float) $rincian->qtybesar;
                $qtyKecil = (float) $rincian->qtykecil;
                $nilaiPerolehan = (float) $rincian->total;
                $perolehanBesar = $qtyBesar > 0 ? $nilaiPerolehan / $qtyBesar : 0;
                $perolehanKecil = $qtyKecil > 0 ? $nilaiPerolehan / $qtyKecil : 0;
                $hargaJualBesar = (float) $barang?->hargajual_satuanbesar;
                $hargaJualKecil = (float) $barang?->hargajual_satuankecil;
                $kesalahan = [];

                if ($hargaJualBesar < $perolehanBesar) {
                    $kesalahan[] = sprintf(
                        'satuan besar: harga jual %s lebih kecil dari harga perolehan %s',
                        number_format($hargaJualBesar, 2, ',', '.'),
                        number_format($perolehanBesar, 2, ',', '.'),
                    );
                }
                if ($hargaJualKecil < $perolehanKecil) {
                    $kesalahan[] = sprintf(
                        'satuan kecil: harga jual %s lebih kecil dari harga perolehan %s',
                        number_format($hargaJualKecil, 2, ',', '.'),
                        number_format($perolehanKecil, 2, ',', '.'),
                    );
                }

                if ($kesalahan) {
                    throw ValidationException::withMessages([
                        'harga_jual' => sprintf(
                            'Kirim stok ditolak untuk %s — %s. Perbaiki harga jual di Master Barang.',
                            $barang?->namabarang ?? 'barang',
                            implode('; ', $kesalahan),
                        ),
                    ]);
                }
            }

            foreach ($penerimaan->rincian as $rincian) {
                $qty = (float) $rincian->qtykecil;
                $nilai = (float) $rincian->total;
                $hargaEfektif = $qty > 0 ? $nilai / $qty : 0;
                $stok = Stok::create([
                    'kode_lot' => sprintf('LOT-%s-%06d', $penerimaan->nomortransaksi, $rincian->id),
                    'barang_id' => $rincian->barang_id,
                    'supplier_id' => $penerimaan->supplier_id,
                    'penerimaan_id' => $penerimaan->id,
                    'penerimaan_rinci_id' => $rincian->id,
                    'tanggal_masuk' => $penerimaan->tanggal,
                    'qty_masuk' => $qty,
                    'qty_keluar' => 0,
                    'qty_tersedia' => $qty,
                    'satuan' => $rincian->barang?->satuankecil,
                    'harga_per_unit' => $hargaEfektif,
                    'nilai_awal' => $nilai,
                    'nilai_tersedia' => $nilai,
                    'status' => 'TERSEDIA',
                    'metadata' => [
                        'qty_besar' => (float) $rincian->qtybesar,
                        'isi' => (int) $rincian->isi,
                        'harga_beli_satuan_besar' => (float) $rincian->hargabeli,
                        'harga_beli_satuan_kecil_sebelum_diskon' => (float) $rincian->hargakecil,
                        'diskon_persen' => (float) $rincian->diskonpersen,
                        'diskon_nominal' => (float) $rincian->diskonnominal,
                        'nomor_faktur' => $penerimaan->nomorfaktur,
                    ],
                    'created_by' => $userId,
                ]);

                $stok->mutasi()->create([
                    'kode_mutasi' => 'MUT-IN-'.$rincian->id,
                    'barang_id' => $rincian->barang_id,
                    'tipe' => 'MASUK',
                    'sumber_tipe' => 'PENERIMAAN',
                    'sumber_id' => $penerimaan->id,
                    'nomor_referensi' => $penerimaan->nomortransaksi,
                    'tanggal_mutasi' => now(),
                    'qty_masuk' => $qty,
                    'saldo_sebelum' => 0,
                    'saldo_sesudah' => $qty,
                    'harga_per_unit' => $hargaEfektif,
                    'nilai_mutasi' => $nilai,
                    'keterangan' => 'Stok masuk dari penerimaan '.$penerimaan->nomortransaksi,
                    'created_by' => $userId,
                ]);
            }

            $penerimaan->update([
                'status' => 'Terkunci',
                'flaging' => 1,
                'stok_terkirim_at' => now(),
                'stok_terkirim_oleh' => $userId,
            ]);

            return $penerimaan->fresh(['supplier:id,nama']);
        });
    }

    public function keluarkanFifo(
        Barang $barang,
        float $qty,
        string $sumberTipe,
        ?int $sumberId,
        ?string $nomorReferensi,
        int $userId,
        ?string $keterangan = null,
        ?float $hargaJualPerUnit = null,
    ): array {
        if ($qty <= 0) {
            throw ValidationException::withMessages(['qty' => 'Qty keluar harus lebih dari nol']);
        }

        return DB::transaction(function () use (
            $barang, $qty, $sumberTipe, $sumberId, $nomorReferensi, $userId, $keterangan,
            $hargaJualPerUnit
        ) {
            $sisa = $qty;
            $nilaiKeluar = 0;
            $alokasi = [];
            $lots = Stok::where('barang_id', $barang->id)
                ->where('qty_tersedia', '>', 0)
                ->orderBy('tanggal_masuk')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ((float) $lots->sum('qty_tersedia') < $qty) {
                throw ValidationException::withMessages([
                    'qty' => "Stok {$barang->namabarang} tidak mencukupi",
                ]);
            }

            foreach ($lots as $lot) {
                if ($sisa <= 0) break;
                $sebelum = (float) $lot->qty_tersedia;
                $diambil = min($sebelum, $sisa);
                $sesudah = $sebelum - $diambil;
                $nilai = $diambil * (float) $lot->harga_per_unit;

                if (
                    strtoupper($sumberTipe) === 'PENJUALAN'
                    && (float) $lot->harga_per_unit > ($hargaJualPerUnit ?? (float) $barang->hargajual_satuankecil)
                ) {
                    throw ValidationException::withMessages([
                        'harga_jual' => sprintf(
                            'Transaksi diblokir: harga perolehan %s pada lot %s lebih besar dari harga jual %s untuk %s',
                            number_format((float) $lot->harga_per_unit, 2, ',', '.'),
                            $lot->kode_lot,
                            number_format($hargaJualPerUnit ?? (float) $barang->hargajual_satuankecil, 2, ',', '.'),
                            $barang->namabarang,
                        ),
                    ]);
                }

                $lot->update([
                    'qty_keluar' => (float) $lot->qty_keluar + $diambil,
                    'qty_tersedia' => $sesudah,
                    'nilai_tersedia' => $sesudah * (float) $lot->harga_per_unit,
                    'status' => $sesudah <= 0 ? 'HABIS' : 'TERSEDIA',
                ]);
                $lot->mutasi()->create([
                    'kode_mutasi' => 'MUT-OUT-'.Str::uuid(),
                    'barang_id' => $barang->id,
                    'tipe' => 'KELUAR',
                    'sumber_tipe' => strtoupper($sumberTipe),
                    'sumber_id' => $sumberId,
                    'nomor_referensi' => $nomorReferensi,
                    'tanggal_mutasi' => now(),
                    'qty_keluar' => $diambil,
                    'saldo_sebelum' => $sebelum,
                    'saldo_sesudah' => $sesudah,
                    'harga_per_unit' => $lot->harga_per_unit,
                    'nilai_mutasi' => $nilai,
                    'keterangan' => $keterangan,
                    'created_by' => $userId,
                ]);
                $alokasi[] = ['stok_id' => $lot->id, 'kode_lot' => $lot->kode_lot, 'qty' => $diambil];
                $nilaiKeluar += $nilai;
                $sisa -= $diambil;
            }

            return ['qty' => $qty, 'nilai' => $nilaiKeluar, 'alokasi' => $alokasi];
        });
    }
}
