<?php

namespace App\Http\Controllers\Api\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Gudang\Penerimaan;
use App\Models\Gudang\ReturPembelian;
use App\Models\Gudang\ReturPembelianRinci;
use App\Services\Stok\ReturPembelianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReturPembelianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search'));
        $data = ReturPembelian::with([
            'penerimaan:id,nomortransaksi,nomorfaktur',
            'supplier:id,nama',
            'pengguna:id,name',
        ])->withCount('rincian')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('nomorretur', 'like', "%{$search}%")
                    ->orWhereHas('penerimaan', fn ($penerimaan) =>
                        $penerimaan->where('nomortransaksi', 'like', "%{$search}%")
                            ->orWhere('nomorfaktur', 'like', "%{$search}%")
                    );
            }))
            ->when($request->tanggal_awal, fn ($query, $value) => $query->whereDate('tanggal', '>=', $value))
            ->when($request->tanggal_akhir, fn ($query, $value) => $query->whereDate('tanggal', '<=', $value))
            ->latest('tanggal')->latest('id')
            ->paginate(min(max($request->integer('per_page', 12), 1), 50));

        $ringkasan = ReturPembelian::query()
            ->selectRaw('COUNT(*) as jumlah_retur')
            ->selectRaw('COALESCE(SUM(jumlahitem), 0) as jumlah_item')
            ->selectRaw('COALESCE(SUM(total), 0) as total_retur')
            ->whereDate('tanggal', today())->first();

        return new JsonResponse([...$data->toArray(), 'ringkasan' => $ringkasan]);
    }

    public function penerimaan(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search'));
        $data = Penerimaan::with('supplier:id,nama')
            ->where('flaging', 1)
            ->whereNotNull('stok_terkirim_at')
            ->whereHas('stok', fn ($stok) => $stok->where('qty_tersedia', '>', 0))
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('nomortransaksi', 'like', "%{$search}%")
                    ->orWhere('nomorfaktur', 'like', "%{$search}%");
            }))
            ->latest('tanggal')->latest('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 50));
        return new JsonResponse($data);
    }

    public function penerimaanDetail(int $id): JsonResponse
    {
        $penerimaan = Penerimaan::with([
            'supplier:id,nama',
            'rincian.barang:id,kodebarang,namabarang,satuanbesar,satuankecil',
            'rincian.stok',
        ])->findOrFail($id);
        if ((int) $penerimaan->flaging !== 1 || !$penerimaan->stok_terkirim_at) {
            throw ValidationException::withMessages([
                'penerimaan' => 'Penerimaan ini belum masuk stok dan tidak dapat diretur',
            ]);
        }
        $sudahDiretur = ReturPembelianRinci::whereIn(
            'penerimaan_rinci_id',
            $penerimaan->rincian->pluck('id'),
        )->selectRaw('penerimaan_rinci_id, SUM(qty_kecil) as qty_kecil')
            ->groupBy('penerimaan_rinci_id')->get()->keyBy('penerimaan_rinci_id');

        $penerimaan->rincian->each(function ($rinci) use ($sudahDiretur) {
            $isi = max((int) $rinci->isi, 1);
            $stokTersedia = (float) ($rinci->stok?->qty_tersedia ?? 0);
            $qtyDiretur = (float) ($sudahDiretur->get($rinci->id)?->qty_kecil ?? 0);
            $sisaPenerimaan = max((float) $rinci->qtykecil - $qtyDiretur, 0);
            $maksimalRetur = min($stokTersedia, $sisaPenerimaan);

            $rinci->setAttribute('qty_sudah_diretur', $qtyDiretur);
            $rinci->setAttribute('sisa_penerimaan_kecil', $sisaPenerimaan);
            $rinci->setAttribute('stok_tersedia_kecil', $stokTersedia);
            $rinci->setAttribute('stok_tersedia_besar', floor($stokTersedia / $isi));
            $rinci->setAttribute('stok_sisa_kecil', fmod($stokTersedia, $isi));
            $rinci->setAttribute('maksimal_retur_kecil', $maksimalRetur);
            $rinci->setAttribute('maksimal_retur_besar', floor($maksimalRetur / $isi));
        });
        return new JsonResponse(['data' => $penerimaan]);
    }

    public function show(int $id): JsonResponse
    {
        return new JsonResponse(['data' => ReturPembelian::with([
            'penerimaan:id,nomortransaksi,nomorfaktur,tanggal',
            'supplier:id,nama',
            'pengguna:id,name',
            'rincian.barang:id,kodebarang,namabarang',
        ])->findOrFail($id)]);
    }

    public function store(Request $request, ReturPembelianService $service): JsonResponse
    {
        $data = $request->validate([
            'penerimaan_id' => ['required', 'integer', 'exists:tpenerimaan,id'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.penerimaan_rinci_id' => ['required', 'integer', 'exists:tpenerimaan_rinci,id'],
            'items.*.jenis_satuan' => ['required', Rule::in(['KECIL', 'BESAR'])],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);
        return new JsonResponse([
            'message' => 'Retur pembelian berhasil disimpan',
            'data' => $service->simpan($data, $request->user()->id),
        ], 201);
    }

    public function destroy(int $id, ReturPembelianService $service): JsonResponse
    {
        $service->hapus(ReturPembelian::findOrFail($id));
        return new JsonResponse(['message' => 'Retur pembelian berhasil dihapus']);
    }
}
