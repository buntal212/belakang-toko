<?php

namespace App\Http\Controllers\Api\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\Gudang\Penerimaan;
use App\Models\Keuangan\PembayaranPbf;
use App\Models\Keuangan\PembayaranPbfRinci;
use App\Services\Keuangan\PembayaranPbfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PembayaranPbfController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $apply = fn ($query) => $query
            ->when($request->search, fn ($builder, $search) => $builder->where(function ($query) use ($search) {
                $query->where('nomor_pembayaran', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($pbf) => $pbf->where('nama', 'like', "%{$search}%"));
            }))
            ->when($request->tanggal_awal, fn ($builder, $date) => $builder->whereDate('tanggal', '>=', $date))
            ->when($request->tanggal_akhir, fn ($builder, $date) => $builder->whereDate('tanggal', '<=', $date));
        $ringkasan = $apply(PembayaranPbf::query())
            ->selectRaw('COUNT(*) as jumlah_pembayaran')
            ->selectRaw('COALESCE(SUM(nominal), 0) as total_uang_keluar')->first();
        $items = $apply(PembayaranPbf::query())
            ->with([
                'supplier:id,nama,telepon', 'pembuat:id,name,username',
                'rincian:id,pembayaran_pbf_id,penerimaan_id',
                'rincian.penerimaan:id,nomortransaksi,nomorfaktur',
            ])->withCount('rincian')->latest('tanggal')->latest('id')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));
        return new JsonResponse([...$items->toArray(), 'ringkasan' => $ringkasan]);
    }

    public function preview(int $supplierId): JsonResponse
    {
        $faktur = Penerimaan::query()
            ->select(['id', 'nomortransaksi', 'nomorfaktur', 'tanggal', 'grandtotal', 'dibayar', 'sisa_hutang'])
            ->where('supplier_id', $supplierId)->where('cara_bayar', 'HUTANG')
            ->where('sisa_hutang', '>', 0)->orderBy('tanggal')->orderBy('id')->get();
        $totalTerbayar = PembayaranPbf::where('supplier_id', $supplierId)->sum('nominal');
        $jumlahLunas = PembayaranPbfRinci::query()
            ->whereHas('pembayaran', fn ($query) => $query->where('supplier_id', $supplierId))
            ->whereHas('penerimaan', fn ($query) => $query->where('sisa_hutang', '<=', 0))
            ->distinct()->count('penerimaan_id');
        return new JsonResponse(['data' => [
            'total_hutang' => (float) $faktur->sum('sisa_hutang'), 'jumlah_faktur' => $faktur->count(),
            'total_terbayar' => (float) $totalTerbayar, 'jumlah_faktur_lunas' => $jumlahLunas,
            'faktur' => $faktur,
        ]]);
    }

    public function store(Request $request, PembayaranPbfService $service): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:msupplier,id'],
            'nominal' => ['required', 'numeric', 'gt:0'],
            'metode_pembayaran' => ['required', Rule::in(['CASH', 'TRANSFER', 'DEBIT', 'QRIS'])],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
        return new JsonResponse([
            'message' => 'Pembayaran hutang ke PBF berhasil disimpan',
            'data' => $service->simpan($data, $request->user()->id),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        return new JsonResponse(['data' => PembayaranPbf::with([
            'supplier:id,nama,telepon', 'pembuat:id,name,username',
            'rincian.penerimaan:id,nomortransaksi,nomorfaktur,tanggal,grandtotal,dibayar,sisa_hutang',
        ])->findOrFail($id)]);
    }
}
