<?php

namespace App\Http\Controllers\Api\Penjualan;

use App\Http\Controllers\Controller;
use App\Models\Penjualan\PembayaranPelanggan;
use App\Models\Penjualan\PembayaranPelangganRinci;
use App\Models\Penjualan\Penjualan;
use App\Services\Penjualan\PembayaranPelangganService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PembayaranPelangganController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $applyFilters = fn ($query) => $query
            ->when($request->search, fn ($builder, $search) => $builder->where(function ($query) use ($search) {
                $query->where('nomor_pembayaran', 'like', "%{$search}%")
                    ->orWhereHas('pelanggan', fn ($pelanggan) => $pelanggan->where('nama', 'like', "%{$search}%"));
            }))
            ->when($request->pelanggan_id, fn ($builder, $id) => $builder->where('pelanggan_id', $id))
            ->when($request->tanggal_awal, fn ($builder, $date) => $builder->whereDate('tanggal', '>=', $date))
            ->when($request->tanggal_akhir, fn ($builder, $date) => $builder->whereDate('tanggal', '<=', $date));

        $ringkasan = $applyFilters(PembayaranPelanggan::query())
            ->selectRaw('COUNT(*) as jumlah_pembayaran')
            ->selectRaw('COALESCE(SUM(nominal), 0) as total_uang_masuk')
            ->first();
        $items = $applyFilters(PembayaranPelanggan::query())
            ->with([
                'pelanggan:id,nama,telepon',
                'pembuat:id,name,username',
                'rincian:id,pembayaran_pelanggan_id,penjualan_id',
                'rincian.penjualan:id,nomortransaksi',
            ])
            ->withCount('rincian')
            ->latest('tanggal')->latest('id')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return new JsonResponse([...$items->toArray(), 'ringkasan' => $ringkasan]);
    }

    public function preview(int $pelangganId): JsonResponse
    {
        $nota = Penjualan::query()
            ->select(['id', 'nomortransaksi', 'tanggal', 'grandtotal', 'sisa_hutang'])
            ->where('pelanggan_id', $pelangganId)
            ->where('cara_bayar', 'HUTANG')
            ->where('sisa_hutang', '>', 0)
            ->orderBy('tanggal')->orderBy('id')->get();
        $totalTerbayar = PembayaranPelanggan::query()
            ->where('pelanggan_id', $pelangganId)
            ->sum('nominal');
        $jumlahNotaTerbayar = PembayaranPelangganRinci::query()
            ->whereHas('pembayaran', fn ($query) => $query->where('pelanggan_id', $pelangganId))
            ->whereHas('penjualan', fn ($query) => $query->where('sisa_hutang', '<=', 0))
            ->distinct()
            ->count('penjualan_id');

        return new JsonResponse(['data' => [
            'total_hutang' => (float) $nota->sum('sisa_hutang'),
            'jumlah_nota' => $nota->count(),
            'total_terbayar' => (float) $totalTerbayar,
            'jumlah_nota_terbayar' => $jumlahNotaTerbayar,
            'nota' => $nota,
        ]]);
    }

    public function store(Request $request, PembayaranPelangganService $service): JsonResponse
    {
        $data = $request->validate([
            'pelanggan_id' => ['required', 'integer', 'exists:mpelanggan,id'],
            'nominal' => ['required', 'numeric', 'gt:0'],
            'metode_pembayaran' => ['required', Rule::in(['CASH', 'TRANSFER', 'DEBIT', 'QRIS'])],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        return new JsonResponse([
            'message' => 'Pembayaran pelanggan berhasil disimpan',
            'data' => $service->simpan($data, $request->user()->id),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        return new JsonResponse(['data' => PembayaranPelanggan::with([
            'pelanggan:id,nama,telepon',
            'pembuat:id,name,username',
            'rincian.penjualan:id,nomortransaksi,tanggal,grandtotal,sisa_hutang,status',
        ])->findOrFail($id)]);
    }
}
