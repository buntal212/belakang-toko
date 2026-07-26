<?php

namespace App\Http\Controllers\Api\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Master\Barang;
use App\Models\Stok\Stok;
use App\Models\Stok\StokMutasi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KartuStokController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barang_id' => ['required', 'integer', 'exists:mbarang,id'],
            'tanggal_awal' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_awal'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $barang = Barang::query()
            ->select([
                'id',
                'kodebarang',
                'kodebarcode',
                'namabarang',
                'jenisbarang',
                'merk',
                'satuankecil',
                'satuanbesar',
                'isisatuan',
            ])
            ->findOrFail($data['barang_id']);

        $tanggalAwal = isset($data['tanggal_awal'])
            ? Carbon::createFromFormat('Y-m-d', $data['tanggal_awal'])->startOfDay()
            : null;
        $tanggalAkhir = isset($data['tanggal_akhir'])
            ? Carbon::createFromFormat('Y-m-d', $data['tanggal_akhir'])->endOfDay()
            : null;

        $saldoAwal = $tanggalAwal
            ? StokMutasi::query()
                ->where('barang_id', $barang->id)
                ->where('tanggal_mutasi', '<', $tanggalAwal)
                ->selectRaw('COALESCE(SUM(qty_masuk - qty_keluar), 0) as saldo')
                ->value('saldo')
            : 0;
        $saldoAwal ??= 0;

        $query = StokMutasi::query()
            ->select('stok_mutasi.*')
            ->selectRaw(
                '? + SUM(qty_masuk - qty_keluar) OVER (
                    ORDER BY tanggal_mutasi ASC, id ASC
                    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                ) AS saldo_kartu',
                [(float) $saldoAwal],
            )
            ->with([
                'stok:id,kode_lot',
                'pengguna:id,name',
            ])
            ->where('barang_id', $barang->id)
            ->when($tanggalAwal, fn ($builder) => $builder->where('tanggal_mutasi', '>=', $tanggalAwal))
            ->when($tanggalAkhir, fn ($builder) => $builder->where('tanggal_mutasi', '<=', $tanggalAkhir))
            ->orderBy('tanggal_mutasi')
            ->orderBy('id');

        $ringkasanPeriode = StokMutasi::query()
            ->where('barang_id', $barang->id)
            ->when($tanggalAwal, fn ($builder) => $builder->where('tanggal_mutasi', '>=', $tanggalAwal))
            ->when($tanggalAkhir, fn ($builder) => $builder->where('tanggal_mutasi', '<=', $tanggalAkhir))
            ->selectRaw('COALESCE(SUM(qty_masuk), 0) as total_masuk')
            ->selectRaw('COALESCE(SUM(qty_keluar), 0) as total_keluar')
            ->first();

        $mutasi = $query->paginate($data['per_page'] ?? 25);
        $totalMasuk = (float) ($ringkasanPeriode->total_masuk ?? 0);
        $totalKeluar = (float) ($ringkasanPeriode->total_keluar ?? 0);
        $saldoSekarang = Stok::query()
            ->where('barang_id', $barang->id)
            ->sum('qty_tersedia');

        return new JsonResponse([
            ...$mutasi->toArray(),
            'barang' => $barang,
            'ringkasan' => [
                'saldo_awal' => (float) $saldoAwal,
                'total_masuk' => $totalMasuk,
                'total_keluar' => $totalKeluar,
                'saldo_akhir' => (float) $saldoAwal + $totalMasuk - $totalKeluar,
                'saldo_sekarang' => (float) $saldoSekarang,
            ],
        ]);
    }
}
