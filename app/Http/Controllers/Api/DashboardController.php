<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gudang\Penerimaan;
use App\Models\Gudang\ReturPembelian;
use App\Models\Penjualan\Penjualan;
use App\Models\Penjualan\ReturPenjualan;
use App\Models\Stok\Stok;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $hariIni = today();
        $kemarin = today()->subDay();

        $penjualanHariIni = $this->ringkasanPenjualan($hariIni);
        $penjualanKemarin = $this->ringkasanPenjualan($kemarin);
        $returPenjualanHariIni = (float) ReturPenjualan::whereDate('tanggal', $hariIni)->sum('total');
        $returPenjualanKemarin = (float) ReturPenjualan::whereDate('tanggal', $kemarin)->sum('total');
        $penjualanBersih = (float) $penjualanHariIni->total - $returPenjualanHariIni;
        $penjualanBersihKemarin = (float) $penjualanKemarin->total - $returPenjualanKemarin;

        $stok = Stok::query()
            ->selectRaw('COUNT(DISTINCT barang_id) as jumlah_barang')
            ->selectRaw('COALESCE(SUM(qty_tersedia), 0) as jumlah_unit')
            ->selectRaw('COALESCE(SUM(nilai_tersedia), 0) as nilai')
            ->first();

        $stokMenipis = DB::table('mbarang')
            ->leftJoin('stok', 'stok.barang_id', '=', 'mbarang.id')
            ->where('mbarang.limitstok', '>', 0)
            ->select([
                'mbarang.id',
                'mbarang.kodebarang',
                'mbarang.namabarang',
                'mbarang.satuankecil',
                'mbarang.limitstok',
            ])
            ->selectRaw('COALESCE(SUM(stok.qty_tersedia), 0) as stok_tersedia')
            ->groupBy([
                'mbarang.id',
                'mbarang.kodebarang',
                'mbarang.namabarang',
                'mbarang.satuankecil',
                'mbarang.limitstok',
            ])
            ->havingRaw('COALESCE(SUM(stok.qty_tersedia), 0) <= COALESCE(mbarang.limitstok, 0)')
            ->orderByRaw('COALESCE(SUM(stok.qty_tersedia), 0) ASC')
            ->limit(6)
            ->get();

        $jumlahStokMenipis = DB::query()
            ->fromSub(
                DB::table('mbarang')
                    ->leftJoin('stok', 'stok.barang_id', '=', 'mbarang.id')
                    ->where('mbarang.limitstok', '>', 0)
                    ->select('mbarang.id')
                    ->groupBy('mbarang.id', 'mbarang.limitstok')
                    ->havingRaw('COALESCE(SUM(stok.qty_tersedia), 0) <= COALESCE(mbarang.limitstok, 0)'),
                'stok_minimum',
            )
            ->count();

        return new JsonResponse([
            'generated_at' => now()->toIso8601String(),
            'ringkasan' => [
                'penjualan_bersih' => $penjualanBersih,
                'penjualan_kotor' => (float) $penjualanHariIni->total,
                'retur_penjualan' => $returPenjualanHariIni,
                'jumlah_transaksi' => (int) $penjualanHariIni->jumlah,
                'jumlah_item' => (float) $penjualanHariIni->item,
                'laba_kotor' => (float) $penjualanHariIni->total - (float) $penjualanHariIni->hpp - $returPenjualanHariIni,
                'perubahan_penjualan' => $this->persentasePerubahan($penjualanBersih, $penjualanBersihKemarin),
                'nilai_stok' => (float) ($stok->nilai ?? 0),
                'jumlah_stok' => (float) ($stok->jumlah_unit ?? 0),
                'jumlah_produk_tersedia' => (int) ($stok->jumlah_barang ?? 0),
                'stok_menipis' => $jumlahStokMenipis,
                'penerimaan_hari_ini' => (float) Penerimaan::whereDate('tanggal', $hariIni)->sum('grandtotal'),
                'retur_pembelian_hari_ini' => (float) ReturPembelian::whereDate('tanggal', $hariIni)->sum('total'),
            ],
            'tren_penjualan' => $this->trenPenjualan(),
            'produk_terlaris' => $this->produkTerlaris(),
            'stok_menipis' => $stokMenipis,
            'cara_bayar' => Penjualan::query()
                ->whereDate('tanggal', $hariIni)
                ->select('cara_bayar')
                ->selectRaw('COUNT(*) as jumlah')
                ->selectRaw('COALESCE(SUM(grandtotal), 0) as total')
                ->groupBy('cara_bayar')
                ->orderByDesc('total')
                ->get(),
            'aktivitas_terbaru' => $this->aktivitasTerbaru(),
        ]);
    }

    private function ringkasanPenjualan(Carbon $tanggal): object
    {
        return Penjualan::query()
            ->whereDate('tanggal', $tanggal)
            ->selectRaw('COUNT(*) as jumlah')
            ->selectRaw('COALESCE(SUM(jumlahitem), 0) as item')
            ->selectRaw('COALESCE(SUM(grandtotal), 0) as total')
            ->selectRaw('COALESCE(SUM(hpp), 0) as hpp')
            ->first();
    }

    private function trenPenjualan(): array
    {
        $mulai = today()->subDays(6);
        $penjualan = Penjualan::query()
            ->whereDate('tanggal', '>=', $mulai)
            ->selectRaw('DATE(tanggal) as hari, COUNT(*) as transaksi, SUM(grandtotal) as total')
            ->groupByRaw('DATE(tanggal)')
            ->get()
            ->keyBy('hari');
        $retur = ReturPenjualan::query()
            ->whereDate('tanggal', '>=', $mulai)
            ->selectRaw('DATE(tanggal) as hari, SUM(total) as total')
            ->groupByRaw('DATE(tanggal)')
            ->pluck('total', 'hari');

        return collect(range(0, 6))->map(function (int $offset) use ($mulai, $penjualan, $retur) {
            $tanggal = $mulai->copy()->addDays($offset);
            $key = $tanggal->toDateString();
            $jual = $penjualan->get($key);

            return [
                'tanggal' => $key,
                'label' => $tanggal->translatedFormat('D'),
                'total' => max((float) ($jual->total ?? 0) - (float) ($retur[$key] ?? 0), 0),
                'transaksi' => (int) ($jual->transaksi ?? 0),
            ];
        })->all();
    }

    private function produkTerlaris()
    {
        return DB::table('tpenjualan_rinci')
            ->join('tpenjualan', 'tpenjualan.id', '=', 'tpenjualan_rinci.penjualan_id')
            ->join('mbarang', 'mbarang.id', '=', 'tpenjualan_rinci.barang_id')
            ->where('tpenjualan.tanggal', '>=', now()->startOfMonth())
            ->select('mbarang.id', 'mbarang.kodebarang', 'mbarang.namabarang', 'mbarang.satuankecil')
            ->selectRaw('SUM(tpenjualan_rinci.qty_kecil) as qty_terjual')
            ->selectRaw('SUM(tpenjualan_rinci.subtotal) as omzet')
            ->groupBy('mbarang.id', 'mbarang.kodebarang', 'mbarang.namabarang', 'mbarang.satuankecil')
            ->orderByDesc('qty_terjual')
            ->limit(5)
            ->get();
    }

    private function aktivitasTerbaru()
    {
        $penjualan = Penjualan::latest('tanggal')->limit(6)->get()->map(fn ($item) => [
            'tipe' => 'PENJUALAN',
            'nomor' => $item->nomortransaksi,
            'tanggal' => $item->tanggal?->toIso8601String(),
            'total' => (float) $item->grandtotal,
            'keterangan' => $item->cara_bayar,
            'ikon' => 'shopping_cart_checkout',
            'warna' => 'primary',
        ]);
        $penerimaan = Penerimaan::with('supplier:id,nama')->latest('tanggal')->limit(6)->get()->map(fn ($item) => [
            'tipe' => 'PENERIMAAN',
            'nomor' => $item->nomortransaksi,
            'tanggal' => $item->tanggal?->toIso8601String(),
            'total' => (float) $item->grandtotal,
            'keterangan' => $item->supplier?->nama,
            'ikon' => 'move_to_inbox',
            'warna' => 'indigo',
        ]);
        $returJual = ReturPenjualan::latest('tanggal')->limit(6)->get()->map(fn ($item) => [
            'tipe' => 'RETUR PENJUALAN',
            'nomor' => $item->nomorretur,
            'tanggal' => $item->tanggal?->toIso8601String(),
            'total' => (float) $item->total,
            'keterangan' => $item->metode_pengembalian,
            'ikon' => 'assignment_return',
            'warna' => 'positive',
        ]);
        $returBeli = ReturPembelian::with('supplier:id,nama')->latest('tanggal')->limit(6)->get()->map(fn ($item) => [
            'tipe' => 'RETUR PEMBELIAN',
            'nomor' => $item->nomorretur,
            'tanggal' => $item->tanggal?->toIso8601String(),
            'total' => (float) $item->total,
            'keterangan' => $item->supplier?->nama,
            'ikon' => 'outbox',
            'warna' => 'negative',
        ]);

        return $penjualan
            ->concat($penerimaan)
            ->concat($returJual)
            ->concat($returBeli)
            ->sortByDesc('tanggal')
            ->take(7)
            ->values();
    }

    private function persentasePerubahan(float $sekarang, float $sebelumnya): float
    {
        if ($sebelumnya == 0.0) {
            return $sekarang > 0 ? 100 : 0;
        }

        return round((($sekarang - $sebelumnya) / abs($sebelumnya)) * 100, 1);
    }
}
