<?php

namespace App\Services\Penjualan;

use App\Models\Penjualan\PembayaranPelanggan;
use App\Models\Penjualan\Penjualan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PembayaranPelangganService
{
    public function simpan(array $data, int $userId): PembayaranPelanggan
    {
        return DB::transaction(function () use ($data, $userId) {
            $nota = Penjualan::query()
                ->where('pelanggan_id', $data['pelanggan_id'])
                ->where('cara_bayar', 'HUTANG')
                ->where('sisa_hutang', '>', 0)
                ->orderBy('tanggal')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $totalHutang = (float) $nota->sum('sisa_hutang');
            $nominal = (float) $data['nominal'];

            if ($totalHutang <= 0) {
                throw ValidationException::withMessages(['pelanggan_id' => 'Pelanggan tidak memiliki hutang aktif']);
            }
            if ($nominal > $totalHutang) {
                throw ValidationException::withMessages([
                    'nominal' => 'Uang masuk melebihi total hutang pelanggan sebesar Rp '.number_format($totalHutang, 0, ',', '.'),
                ]);
            }

            $pembayaran = PembayaranPelanggan::create([
                'nomor_pembayaran' => $this->nextNumber(),
                'tanggal' => now(),
                'pelanggan_id' => $data['pelanggan_id'],
                'nominal' => $nominal,
                'metode_pembayaran' => $data['metode_pembayaran'],
                'catatan' => $data['catatan'] ?? null,
                'created_by' => $userId,
            ]);

            $sisaPembayaran = $nominal;
            foreach ($nota as $item) {
                if ($sisaPembayaran <= 0) break;
                $dibayar = min($sisaPembayaran, (float) $item->sisa_hutang);
                $sisaNota = (float) $item->sisa_hutang - $dibayar;
                $pembayaran->rincian()->create([
                    'penjualan_id' => $item->id,
                    'nominal' => $dibayar,
                ]);
                $item->update([
                    'sisa_hutang' => $sisaNota,
                    'status' => $sisaNota <= 0 ? 'SELESAI' : 'HUTANG',
                ]);
                $sisaPembayaran -= $dibayar;
            }

            return $pembayaran->load([
                'pelanggan:id,nama,telepon',
                'pembuat:id,name,username',
                'rincian.penjualan:id,nomortransaksi,tanggal,grandtotal,sisa_hutang,status',
            ]);
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'BAYAR-'.now()->format('Ymd').'-';
        $last = PembayaranPelanggan::query()
            ->where('nomor_pembayaran', 'like', $prefix.'%')
            ->lockForUpdate()->max('nomor_pembayaran');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
