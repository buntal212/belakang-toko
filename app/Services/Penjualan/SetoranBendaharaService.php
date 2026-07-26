<?php

namespace App\Services\Penjualan;

use App\Models\Penjualan\Penjualan;
use App\Models\Penjualan\PembayaranPelanggan;
use App\Models\Penjualan\ReturPenjualan;
use App\Models\Penjualan\SetoranBendahara;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetoranBendaharaService
{
    public function preview(?User $kasir, Carbon $mulai, Carbon $sampai): array
    {
        $penjualan = $this->queryPenjualanTersedia($kasir, $mulai, $sampai)
            ->with('pengguna:id,name,username')
            ->withSum([
                'retur as retur_tunai' => fn ($query) =>
                    $query->where('metode_pengembalian', 'CASH'),
            ], 'total')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get()
            ->map(function (Penjualan $item) {
                $retur = (float) ($item->retur_tunai ?? 0);
                $item->setAttribute('retur_tunai', $retur);
                $item->setAttribute('netto_tunai', (float) $item->grandtotal - $retur);

                return $item;
            });

        $pembayaranPelanggan = $this->queryPembayaranPelangganTersedia($kasir, $mulai, $sampai)
            ->with(['pembuat:id,name,username', 'pelanggan:id,nama'])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        return [
            'kasir' => $kasir?->only(['id', 'name', 'username']),
            'tanggal_awal' => $mulai->toDateString(),
            'tanggal_akhir' => $sampai->toDateString(),
            'periode_mulai' => $mulai,
            'periode_sampai' => $sampai,
            'penjualan' => $penjualan,
            'pembayaran_pelanggan' => $pembayaranPelanggan,
            'jumlah_penjualan' => $penjualan->count(),
            'jumlah_retur' => $penjualan->where('retur_tunai', '>', 0)->count(),
            'penjualan_tunai' => $penjualan->sum(fn ($item) => (float) $item->grandtotal),
            'retur_tunai' => $penjualan->sum(fn ($item) => (float) $item->retur_tunai),
            'total_pembayaran_pelanggan' => $pembayaranPelanggan->sum(fn ($item) => (float) $item->nominal),
            'jumlah_pembayaran_pelanggan' => $pembayaranPelanggan->count(),
            'seharusnya_disetor' => $penjualan->sum(fn ($item) => (float) $item->netto_tunai)
                + $pembayaranPelanggan->sum(fn ($item) => (float) $item->nominal),
        ];
    }

    private function queryPembayaranPelangganTersedia(?User $kasir, Carbon $mulai, Carbon $sampai)
    {
        return PembayaranPelanggan::query()
            ->select(['id', 'nomor_pembayaran', 'tanggal', 'pelanggan_id', 'nominal', 'created_by'])
            ->when($kasir, fn ($query) => $query->where('created_by', $kasir->id))
            ->where('metode_pembayaran', 'CASH')
            ->whereNull('setoran_bendahara_id')
            ->whereBetween('tanggal', [$mulai, $sampai]);
    }

    private function queryPenjualanTersedia(?User $kasir, Carbon $mulai, Carbon $sampai)
    {
        return Penjualan::query()
            ->select(['id', 'nomortransaksi', 'tanggal', 'grandtotal', 'created_by'])
            ->when($kasir, fn ($query) => $query->where('created_by', $kasir->id))
            ->where('cara_bayar', 'CASH')
            ->whereNull('setoran_bendahara_id')
            ->where('tanggal', '>=', $mulai)
            ->where('tanggal', '<=', $sampai)
            ->where('status', 'SELESAI');
    }

    public function simpan(User $kasir, array $data): SetoranBendahara
    {
        return DB::transaction(function () use ($kasir, $data) {
            $pembuat = $kasir;
            $semuaKasir = ($data['kasir_id'] ?? null) === 'all';
            $kasir = $semuaKasir
                ? null
                : (isset($data['kasir_id']) ? User::findOrFail($data['kasir_id']) : $pembuat);
            $mulai = Carbon::createFromFormat('Y-m-d', $data['tanggal_awal'])->startOfDay();
            $sampai = Carbon::createFromFormat('Y-m-d', $data['tanggal_akhir'])->endOfDay();
            $penjualan = $this->queryPenjualanTersedia($kasir, $mulai, $sampai)
                ->whereIn('id', $data['penjualan_ids'])
                ->lockForUpdate()
                ->get();
            $pembayaranPelanggan = $this->queryPembayaranPelangganTersedia($kasir, $mulai, $sampai)
                ->whereIn('id', $data['pembayaran_pelanggan_ids'])
                ->lockForUpdate()
                ->get();

            if ($penjualan->count() !== count(array_unique($data['penjualan_ids']))) {
                throw ValidationException::withMessages([
                    'penjualan_ids' => 'Sebagian transaksi sudah disetor atau tidak valid untuk kasir ini',
                ]);
            }
            if ($pembayaranPelanggan->count() !== count(array_unique($data['pembayaran_pelanggan_ids']))) {
                throw ValidationException::withMessages([
                    'pembayaran_pelanggan_ids' => 'Sebagian pembayaran pelanggan sudah disetor atau tidak valid untuk kasir ini',
                ]);
            }

            if ($semuaKasir) {
                $kasirIds = $penjualan->pluck('created_by')
                    ->merge($pembayaranPelanggan->pluck('created_by'))
                    ->unique();
                if ($kasirIds->count() !== 1) {
                    throw ValidationException::withMessages([
                        'penjualan_ids' => 'Pilih transaksi dari satu kasir yang sama untuk setiap setoran',
                    ]);
                }
                $kasir = User::findOrFail($kasirIds->first());
            }

            $retur = ReturPenjualan::query()
                ->whereIn('penjualan_id', $penjualan->pluck('id'))
                ->where('metode_pengembalian', 'CASH')
                ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(total), 0) as total')
                ->first();
            $totalPenjualan = (float) $penjualan->sum('grandtotal');
            $totalRetur = (float) $retur->total;
            $totalPembayaranPelanggan = (float) $pembayaranPelanggan->sum('nominal');
            $seharusnya = $totalPenjualan - $totalRetur + $totalPembayaranPelanggan;
            $semuaTanggal = $penjualan->pluck('tanggal')->merge($pembayaranPelanggan->pluck('tanggal'));
            $periodeMulai = Carbon::parse($semuaTanggal->min());
            $periodeSampai = Carbon::parse($semuaTanggal->max());

            if ($seharusnya < 0) {
                throw ValidationException::withMessages([
                    'setoran' => 'Nilai retur tunai melebihi penjualan tunai pada periode ini',
                ]);
            }

            $nominal = (float) $data['nominal_disetor'];

            $setoran = SetoranBendahara::create([
                'nomor_setoran' => $this->nextNumber(),
                'tanggal' => now(),
                'kasir_id' => $kasir->id,
                'periode_mulai' => $periodeMulai,
                'periode_sampai' => $periodeSampai,
                'jumlah_penjualan' => $penjualan->count(),
                'jumlah_retur' => (int) $retur->jumlah,
                'jumlah_pembayaran_pelanggan' => $pembayaranPelanggan->count(),
                'penjualan_tunai' => $totalPenjualan,
                'retur_tunai' => $totalRetur,
                'pembayaran_pelanggan' => $totalPembayaranPelanggan,
                'seharusnya_disetor' => $seharusnya,
                'nominal_disetor' => $nominal,
                'selisih' => $nominal - $seharusnya,
                'catatan' => $data['catatan'] ?? null,
                'created_by' => $pembuat->id,
            ]);

            Penjualan::query()
                ->whereIn('id', $penjualan->pluck('id'))
                ->update(['setoran_bendahara_id' => $setoran->id]);
            PembayaranPelanggan::query()
                ->whereIn('id', $pembayaranPelanggan->pluck('id'))
                ->update(['setoran_bendahara_id' => $setoran->id]);

            return $setoran;
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'SETOR-'.now()->format('Ymd').'-';
        $last = SetoranBendahara::query()
            ->where('nomor_setoran', 'like', $prefix.'%')
            ->lockForUpdate()
            ->max('nomor_setoran');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
