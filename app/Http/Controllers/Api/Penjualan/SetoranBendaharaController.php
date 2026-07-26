<?php

namespace App\Http\Controllers\Api\Penjualan;

use App\Http\Controllers\Controller;
use App\Models\Penjualan\SetoranBendahara;
use App\Models\User;
use App\Services\Penjualan\SetoranBendaharaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetoranBendaharaController extends Controller
{
    public function preview(Request $request, SetoranBendaharaService $service): JsonResponse
    {
        $kasirRules = $request->input('kasir_id') === 'all'
            ? ['nullable', 'in:all']
            : ['nullable', 'integer', 'exists:users,id'];
        $data = $request->validate([
            'tanggal_awal' => ['required', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_awal'],
            'kasir_id' => $kasirRules,
        ]);
        $mulai = Carbon::createFromFormat('Y-m-d', $data['tanggal_awal'])->startOfDay();
        $sampai = Carbon::createFromFormat('Y-m-d', $data['tanggal_akhir'])->endOfDay();
        $kasir = ($data['kasir_id'] ?? null) === 'all'
            ? null
            : (isset($data['kasir_id']) ? User::findOrFail($data['kasir_id']) : $request->user());

        return new JsonResponse([
            'data' => $service->preview($kasir, $mulai, $sampai),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $kasirId = $request->integer('kasir_id');

        $data = SetoranBendahara::query()
            ->with(['kasir:id,name,username', 'pembuat:id,name'])
            ->when($kasirId, fn ($query) => $query->where('kasir_id', $kasirId))
            ->when($request->search, fn ($query, $search) => $query
                ->where(function ($builder) use ($search) {
                    $builder->where('nomor_setoran', 'like', "%{$search}%")
                        ->orWhereHas('kasir', fn ($kasir) => $kasir
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%"));
                }))
            ->when($request->tanggal_awal, fn ($query, $value) =>
                $query->where('tanggal', '>=', Carbon::createFromFormat('Y-m-d', $value)->startOfDay())
            )
            ->when($request->tanggal_akhir, fn ($query, $value) =>
                $query->where('tanggal', '<=', Carbon::createFromFormat('Y-m-d', $value)->endOfDay())
            )
            ->latest('tanggal')
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));

        $ringkasan = SetoranBendahara::query()
            ->whereDate('tanggal', today())
            ->when($kasirId, fn ($query) => $query->where('kasir_id', $kasirId))
            ->selectRaw('COUNT(*) as jumlah_setoran')
            ->selectRaw('COALESCE(SUM(seharusnya_disetor), 0) as total_seharusnya')
            ->selectRaw('COALESCE(SUM(nominal_disetor), 0) as total_disetor')
            ->selectRaw('COALESCE(SUM(selisih), 0) as total_selisih')
            ->first();

        return new JsonResponse([...$data->toArray(), 'ringkasan' => $ringkasan]);
    }

    public function store(Request $request, SetoranBendaharaService $service): JsonResponse
    {
        $request->merge([
            'penjualan_ids' => $request->input('penjualan_ids', []),
            'pembayaran_pelanggan_ids' => $request->input('pembayaran_pelanggan_ids', []),
        ]);
        $kasirRules = $request->input('kasir_id') === 'all'
            ? ['nullable', 'in:all']
            : ['nullable', 'integer', 'exists:users,id'];
        $data = $request->validate([
            'kasir_id' => $kasirRules,
            'nominal_disetor' => ['required', 'numeric', 'min:0'],
            'tanggal_awal' => ['required', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_awal'],
            'penjualan_ids' => ['present', 'array'],
            'penjualan_ids.*' => ['required', 'integer', 'distinct', 'exists:tpenjualan,id'],
            'pembayaran_pelanggan_ids' => ['present', 'array'],
            'pembayaran_pelanggan_ids.*' => ['required', 'integer', 'distinct', 'exists:tpembayaran_pelanggan,id'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
        if (empty($data['penjualan_ids']) && empty($data['pembayaran_pelanggan_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'setoran' => 'Pilih minimal satu penjualan tunai atau pembayaran pelanggan',
            ]);
        }

        $setoran = $service->simpan($request->user(), $data);

        return new JsonResponse([
            'message' => 'Setoran ke bendahara berhasil disimpan',
            'data' => $setoran->load([
                'kasir:id,name,username',
                'pembuat:id,name',
                'penjualan:id,setoran_bendahara_id,nomortransaksi,tanggal,grandtotal',
                'pembayaranPelangganItems:id,setoran_bendahara_id,nomor_pembayaran,tanggal,pelanggan_id,nominal',
                'pembayaranPelangganItems.pelanggan:id,nama',
            ]),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        return new JsonResponse([
            'data' => SetoranBendahara::with([
                'kasir:id,name,username',
                'pembuat:id,name',
                'penjualan:id,setoran_bendahara_id,nomortransaksi,tanggal,grandtotal',
                'pembayaranPelangganItems:id,setoran_bendahara_id,nomor_pembayaran,tanggal,pelanggan_id,nominal',
                'pembayaranPelangganItems.pelanggan:id,nama',
            ])
                ->findOrFail($id),
        ]);
    }
}
