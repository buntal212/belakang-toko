<?php

namespace App\Services\Keuangan;

use App\Models\Gudang\Penerimaan;
use App\Models\Keuangan\PembayaranPbf;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PembayaranPbfService
{
    public function simpan(array $data, int $userId): PembayaranPbf
    {
        return DB::transaction(function () use ($data, $userId) {
            $faktur = Penerimaan::query()
                ->where('supplier_id', $data['supplier_id'])->where('cara_bayar', 'HUTANG')
                ->where('sisa_hutang', '>', 0)->orderBy('tanggal')->orderBy('id')
                ->lockForUpdate()->get();
            $totalHutang = (float) $faktur->sum('sisa_hutang');
            $nominal = (float) $data['nominal'];
            if ($totalHutang <= 0) throw ValidationException::withMessages(['supplier_id' => 'PBF tidak memiliki hutang aktif']);
            if ($nominal > $totalHutang) {
                throw ValidationException::withMessages(['nominal' => 'Uang keluar melebihi total hutang PBF sebesar Rp '.number_format($totalHutang, 0, ',', '.')]);
            }

            $pembayaran = PembayaranPbf::create([
                'nomor_pembayaran' => $this->nextNumber(), 'tanggal' => now(),
                'supplier_id' => $data['supplier_id'], 'nominal' => $nominal,
                'metode_pembayaran' => $data['metode_pembayaran'],
                'catatan' => $data['catatan'] ?? null, 'created_by' => $userId,
            ]);
            $sisa = $nominal;
            foreach ($faktur as $item) {
                if ($sisa <= 0) break;
                $dibayar = min($sisa, (float) $item->sisa_hutang);
                $sisaFaktur = (float) $item->sisa_hutang - $dibayar;
                $pembayaran->rincian()->create(['penerimaan_id' => $item->id, 'nominal' => $dibayar]);
                $item->update([
                    'dibayar' => (float) $item->dibayar + $dibayar,
                    'sisa_hutang' => $sisaFaktur,
                ]);
                $sisa -= $dibayar;
            }
            return $pembayaran->load([
                'supplier:id,nama,telepon', 'pembuat:id,name,username',
                'rincian.penerimaan:id,nomortransaksi,nomorfaktur,tanggal,grandtotal,dibayar,sisa_hutang',
            ]);
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'BAYAR-PBF-'.now()->format('Ymd').'-';
        $last = PembayaranPbf::query()->where('nomor_pembayaran', 'like', $prefix.'%')
            ->lockForUpdate()->max('nomor_pembayaran');
        return $prefix.str_pad((string) ($last ? ((int) substr($last, -4)) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }
}
