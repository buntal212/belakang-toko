<?php

namespace App\Http\Controllers\Api\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Stok\Stok;
use App\Models\Stok\StokMutasi;
use App\Models\Master\Barang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StokController extends Controller
{
    public function produkTersedia(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search'));
        $barcode = trim((string) $request->input('barcode'));

        $data = Barang::query()
            ->select([
                'mbarang.id',
                'mbarang.kodebarang',
                'mbarang.kodebarcode',
                'mbarang.namabarang',
                'mbarang.jenisbarang',
                'mbarang.merk',
                'mbarang.keterangan',
                'mbarang.satuankecil',
                'mbarang.satuanbesar',
                'mbarang.isisatuan',
                'mbarang.hargajual_satuankecil',
                'mbarang.hargajual_satuanbesar',
            ])
            ->selectRaw('COALESCE(SUM(stok.qty_tersedia), 0) as stok_tersedia')
            ->join('stok', 'stok.barang_id', '=', 'mbarang.id')
            ->where('stok.qty_tersedia', '>', 0)
            ->when($barcode, fn ($query) => $query->where('mbarang.kodebarcode', $barcode))
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('mbarang.kodebarang', 'like', "%{$search}%")
                        ->orWhere('mbarang.kodebarcode', 'like', "%{$search}%")
                        ->orWhere('mbarang.namabarang', 'like', "%{$search}%")
                        ->orWhere('mbarang.jenisbarang', 'like', "%{$search}%")
                        ->orWhere('mbarang.merk', 'like', "%{$search}%");
                });
            })
            ->groupBy([
                'mbarang.id',
                'mbarang.kodebarang',
                'mbarang.kodebarcode',
                'mbarang.namabarang',
                'mbarang.jenisbarang',
                'mbarang.merk',
                'mbarang.keterangan',
                'mbarang.satuankecil',
                'mbarang.satuanbesar',
                'mbarang.isisatuan',
                'mbarang.hargajual_satuankecil',
                'mbarang.hargajual_satuanbesar',
            ])
            ->orderBy('mbarang.namabarang')
            ->paginate(min(max($request->integer('per_page', 24), 1), 50));

        return new JsonResponse($data);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search'));

        $data = Stok::query()
            ->with([
                'barang:id,kodebarang,namabarang,jenisbarang,merk,keterangan,satuanbesar,satuankecil,isisatuan',
                'supplier:id,nama',
                'penerimaan:id,nomortransaksi,nomorfaktur',
            ])
            ->withCount('mutasi')
            ->withSum([
                'mutasi as qty_penerimaan' => fn ($query) =>
                    $query->where('sumber_tipe', 'PENERIMAAN')->where('tipe', 'MASUK'),
            ], 'qty_masuk')
            ->withSum([
                'mutasi as qty_penjualan' => fn ($query) =>
                    $query->where('sumber_tipe', 'PENJUALAN')->where('tipe', 'KELUAR'),
            ], 'qty_keluar')
            ->withSum([
                'mutasi as qty_retur_penjualan' => fn ($query) =>
                    $query->where('sumber_tipe', 'RETUR_PENJUALAN')->where('tipe', 'MASUK'),
            ], 'qty_masuk')
            ->withSum([
                'mutasi as qty_retur_pembelian' => fn ($query) =>
                    $query->where('sumber_tipe', 'RETUR_PEMBELIAN')->where('tipe', 'KELUAR'),
            ], 'qty_keluar')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('kode_lot', 'like', "%{$search}%")
                        ->orWhereHas('barang', fn ($barang) => $barang
                            ->where('kodebarang', 'like', "%{$search}%")
                            ->orWhere('namabarang', 'like', "%{$search}%"))
                        ->orWhereHas('penerimaan', fn ($penerimaan) => $penerimaan
                            ->where('nomortransaksi', 'like', "%{$search}%")
                            ->orWhere('nomorfaktur', 'like', "%{$search}%"));
                });
            })
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->barang_id, fn ($query, $id) => $query->where('barang_id', $id))
            ->orderBy('tanggal_masuk')
            ->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 12), 1), 50));

        $ringkasan = Stok::query()
            ->selectRaw('COUNT(*) as jumlah_lot')
            ->selectRaw('COUNT(DISTINCT barang_id) as jumlah_barang')
            ->selectRaw('COALESCE(SUM(qty_tersedia), 0) as total_qty')
            ->selectRaw('COALESCE(SUM(nilai_tersedia), 0) as total_nilai')
            ->first();
        $pergerakan = StokMutasi::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN sumber_tipe = 'PENERIMAAN' AND tipe = 'MASUK' THEN qty_masuk ELSE 0 END), 0) as total_penerimaan")
            ->selectRaw("COALESCE(SUM(CASE WHEN sumber_tipe = 'PENJUALAN' AND tipe = 'KELUAR' THEN qty_keluar ELSE 0 END), 0) as total_penjualan")
            ->selectRaw("COALESCE(SUM(CASE WHEN sumber_tipe = 'RETUR_PENJUALAN' AND tipe = 'MASUK' THEN qty_masuk ELSE 0 END), 0) as total_retur_penjualan")
            ->selectRaw("COALESCE(SUM(CASE WHEN sumber_tipe = 'RETUR_PEMBELIAN' AND tipe = 'KELUAR' THEN qty_keluar ELSE 0 END), 0) as total_retur_pembelian")
            ->first();
        foreach ($pergerakan->getAttributes() as $key => $value) {
            $ringkasan->setAttribute($key, $value);
        }

        return new JsonResponse([
            'data' => $data->items(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'from' => $data->firstItem(),
            'to' => $data->lastItem(),
            'ringkasan' => $ringkasan,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $stok = Stok::with([
            'barang:id,kodebarang,namabarang,jenisbarang,merk,keterangan,satuanbesar,satuankecil,isisatuan',
            'supplier:id,nama',
            'penerimaan:id,nomortransaksi,nomorfaktur,tanggal',
            'mutasi' => fn ($query) => $query->latest('tanggal_mutasi')->latest('id'),
        ])->findOrFail($id);

        return new JsonResponse(['data' => $stok]);
    }
}
