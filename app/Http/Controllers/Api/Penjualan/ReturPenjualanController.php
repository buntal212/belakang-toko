<?php

namespace App\Http\Controllers\Api\Penjualan;

use App\Http\Controllers\Controller;
use App\Models\Penjualan\Penjualan;
use App\Models\Penjualan\ReturPenjualan;
use App\Models\Penjualan\ReturPenjualanRinci;
use App\Services\Stok\ReturPenjualanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReturPenjualanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search'));
        $data = ReturPenjualan::query()
            ->with(['penjualan:id,nomortransaksi', 'pengguna:id,name'])
            ->withCount('rincian')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('nomorretur', 'like', "%{$search}%")
                    ->orWhereHas('penjualan', fn ($jual) => $jual->where('nomortransaksi', 'like', "%{$search}%"));
            }))
            ->when($request->tanggal_awal, fn ($query, $value) => $query->whereDate('tanggal', '>=', $value))
            ->when($request->tanggal_akhir, fn ($query, $value) => $query->whereDate('tanggal', '<=', $value))
            ->latest('tanggal')->latest('id')
            ->paginate(min(max($request->integer('per_page', 12), 1), 50));

        $ringkasan = ReturPenjualan::query()
            ->selectRaw('COUNT(*) as jumlah_retur')
            ->selectRaw('COALESCE(SUM(jumlahitem), 0) as jumlah_item')
            ->selectRaw('COALESCE(SUM(total), 0) as total_retur')
            ->whereDate('tanggal', today())->first();

        return new JsonResponse([...$data->toArray(), 'ringkasan' => $ringkasan]);
    }

    public function show(int $id): JsonResponse
    {
        return new JsonResponse(['data' => ReturPenjualan::with([
            'penjualan:id,nomortransaksi,tanggal,cara_bayar',
            'pengguna:id,name',
            'rincian.barang:id,kodebarang,namabarang',
        ])->findOrFail($id)]);
    }

    public function transaksi(int $id): JsonResponse
    {
        $penjualan = Penjualan::with([
            'pengguna:id,name',
            'rincian.barang:id,kodebarang,namabarang',
        ])->findOrFail($id);
        if ($penjualan->setoran_bendahara_id !== null) {
            throw ValidationException::withMessages([
                'penjualan' => 'Transaksi sudah disetor ke bendahara dan tidak dapat diretur',
            ]);
        }
        if ($penjualan->tanggal->lt(now()->subDays(3))) {
            throw ValidationException::withMessages([
                'penjualan' => 'Transaksi sudah melewati batas retur 3 hari',
            ]);
        }
        $sudahDiretur = ReturPenjualanRinci::whereIn('penjualan_rinci_id', $penjualan->rincian->pluck('id'))
            ->selectRaw('penjualan_rinci_id, SUM(qty) as qty, SUM(qty_kecil) as qty_kecil')
            ->groupBy('penjualan_rinci_id')
            ->get()->keyBy('penjualan_rinci_id');

        $penjualan->rincian->each(function ($rinci) use ($sudahDiretur) {
            $retur = $sudahDiretur->get($rinci->id);
            $rinci->setAttribute('qty_diretur', (float) ($retur?->qty ?? 0));
            $rinci->setAttribute('qty_bisa_diretur', max((float) $rinci->qty - (float) ($retur?->qty ?? 0), 0));
        });

        return new JsonResponse(['data' => $penjualan]);
    }

    public function store(Request $request, ReturPenjualanService $service): JsonResponse
    {
        $data = $request->validate([
            'penjualan_id' => ['required', 'integer', 'exists:tpenjualan,id'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
            'metode_pengembalian' => ['required', Rule::in(['CASH', 'TRANSFER', 'SALDO'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.penjualan_rinci_id' => ['required', 'integer', 'exists:tpenjualan_rinci,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        return new JsonResponse([
            'message' => 'Retur penjualan berhasil disimpan',
            'data' => $service->simpan($data, $request->user()->id),
        ], 201);
    }

    public function destroy(int $id, ReturPenjualanService $service): JsonResponse
    {
        $service->hapus(ReturPenjualan::findOrFail($id));
        return new JsonResponse(['message' => 'Retur penjualan berhasil dihapus']);
    }
}
