<?php

namespace App\Services\Stok;

use App\Models\Gudang\Penerimaan;
use App\Models\Gudang\PenerimaanRinci;
use App\Models\Gudang\ReturPembelian;
use App\Models\Gudang\ReturPembelianRinci;
use App\Models\Stok\Stok;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturPembelianService
{
    public function simpan(array $data, int $userId): ReturPembelian
    {
        return DB::transaction(function () use ($data, $userId) {
            $penerimaan = Penerimaan::lockForUpdate()->findOrFail($data['penerimaan_id']);
            if ((int) $penerimaan->flaging !== 1 || !$penerimaan->stok_terkirim_at) {
                throw ValidationException::withMessages([
                    'penerimaan' => 'Hanya penerimaan yang sudah masuk stok yang dapat diretur',
                ]);
            }

            $retur = ReturPembelian::create([
                'nomorretur' => $this->nextNumber(),
                'penerimaan_id' => $penerimaan->id,
                'supplier_id' => $penerimaan->supplier_id,
                'tanggal' => now(),
                'alasan' => $data['alasan'],
                'created_by' => $userId,
            ]);
            $jumlahItem = $total = 0;

            foreach ($data['items'] as $item) {
                $rinci = PenerimaanRinci::with('barang')
                    ->where('penerimaan_id', $penerimaan->id)
                    ->lockForUpdate()->findOrFail($item['penerimaan_rinci_id']);
                $lot = Stok::where('penerimaan_rinci_id', $rinci->id)->lockForUpdate()->firstOrFail();
                $besar = $item['jenis_satuan'] === 'BESAR';
                $konversi = $besar ? max((int) $rinci->isi, 1) : 1;
                $qty = (float) $item['qty'];
                $qtyKecil = $qty * $konversi;
                $sudahDiretur = (float) ReturPembelianRinci::where(
                    'penerimaan_rinci_id',
                    $rinci->id,
                )->lockForUpdate()->sum('qty_kecil');
                $sisaPenerimaan = max((float) $rinci->qtykecil - $sudahDiretur, 0);
                $maksimalRetur = min((float) $lot->qty_tersedia, $sisaPenerimaan);

                if ($qtyKecil <= 0 || $qtyKecil > $maksimalRetur) {
                    throw ValidationException::withMessages([
                        'qty' => sprintf(
                            'Jumlah retur %s melebihi batas. Sisa penerimaan: %s %s; stok lot tersedia: %s %s',
                            $rinci->barang->namabarang,
                            number_format($sisaPenerimaan, 2, ',', '.'),
                            $rinci->barang->satuankecil,
                            number_format((float) $lot->qty_tersedia, 2, ',', '.'),
                            $rinci->barang->satuankecil,
                        ),
                    ]);
                }

                $hargaKecil = (float) $lot->harga_per_unit;
                $harga = $besar ? $hargaKecil * $konversi : $hargaKecil;
                $subtotal = $qty * $harga;
                $sebelum = (float) $lot->qty_tersedia;
                $sesudah = $sebelum - $qtyKecil;
                $lot->update([
                    'qty_keluar' => (float) $lot->qty_keluar + $qtyKecil,
                    'qty_tersedia' => $sesudah,
                    'nilai_tersedia' => $sesudah * $hargaKecil,
                    'status' => $sesudah <= 0 ? 'HABIS' : 'TERSEDIA',
                ]);
                $lot->mutasi()->create([
                    'kode_mutasi' => 'MUT-RET-BELI-'.Str::uuid(),
                    'barang_id' => $rinci->barang_id,
                    'tipe' => 'KELUAR',
                    'sumber_tipe' => 'RETUR_PEMBELIAN',
                    'sumber_id' => $retur->id,
                    'nomor_referensi' => $retur->nomorretur,
                    'tanggal_mutasi' => now(),
                    'qty_keluar' => $qtyKecil,
                    'saldo_sebelum' => $sebelum,
                    'saldo_sesudah' => $sesudah,
                    'harga_per_unit' => $hargaKecil,
                    'nilai_mutasi' => $qtyKecil * $hargaKecil,
                    'keterangan' => 'Retur pembelian '.$penerimaan->nomortransaksi,
                    'created_by' => $userId,
                ]);
                $retur->rincian()->create([
                    'penerimaan_rinci_id' => $rinci->id,
                    'stok_id' => $lot->id,
                    'barang_id' => $rinci->barang_id,
                    'qty' => $qty,
                    'qty_kecil' => $qtyKecil,
                    'konversi' => $konversi,
                    'satuan' => $besar ? $rinci->barang->satuanbesar : $rinci->barang->satuankecil,
                    'harga_perolehan' => $harga,
                    'subtotal' => $subtotal,
                ]);
                $jumlahItem += $qtyKecil;
                $total += $subtotal;
            }
            $retur->update(['jumlahitem' => $jumlahItem, 'total' => $total]);
            return $retur->load([
                'penerimaan:id,nomortransaksi,nomorfaktur',
                'supplier:id,nama',
                'pengguna:id,name',
                'rincian.barang:id,kodebarang,namabarang',
            ]);
        });
    }

    public function hapus(ReturPembelian $retur): void
    {
        DB::transaction(function () use ($retur) {
            $retur = ReturPembelian::with('rincian')->lockForUpdate()->findOrFail($retur->id);
            foreach ($retur->rincian as $rinci) {
                $lot = Stok::lockForUpdate()->findOrFail($rinci->stok_id);
                $qty = (float) $rinci->qty_kecil;
                $sesudah = (float) $lot->qty_tersedia + $qty;
                $lot->update([
                    'qty_keluar' => max((float) $lot->qty_keluar - $qty, 0),
                    'qty_tersedia' => $sesudah,
                    'nilai_tersedia' => $sesudah * (float) $lot->harga_per_unit,
                    'status' => 'TERSEDIA',
                ]);
            }
            \App\Models\Stok\StokMutasi::where('sumber_tipe', 'RETUR_PEMBELIAN')
                ->where('sumber_id', $retur->id)->delete();
            $retur->delete();
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'RB-'.now()->format('Ymd').'-';
        $last = ReturPembelian::where('nomorretur', 'like', $prefix.'%')->lockForUpdate()->max('nomorretur');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
