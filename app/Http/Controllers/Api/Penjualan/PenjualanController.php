<?php

namespace App\Http\Controllers\Api\Penjualan;

use App\Http\Controllers\Controller;
use App\Models\Master\Barang;
use App\Models\Master\Pelanggan;
use App\Models\Penjualan\Penjualan;
use App\Services\Stok\StokFifoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PenjualanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search'));
        $data = Penjualan::query()
            ->with(['pengguna:id,name', 'pelanggan:id,nama,telepon'])
            ->withCount('rincian')
            ->when($request->boolean('retur_eligible'), fn ($query) =>
                $query->where('tanggal', '>=', now()->subDays(3))
                    ->whereNull('setoran_bendahara_id')
            )
            ->when($search, fn ($query) => $query->where('nomortransaksi', 'like', "%{$search}%"))
            ->when($request->cara_bayar, fn ($query, $value) => $query->where('cara_bayar', $value))
            ->when($request->tanggal_awal, fn ($query, $value) => $query->whereDate('tanggal', '>=', $value))
            ->when($request->tanggal_akhir, fn ($query, $value) => $query->whereDate('tanggal', '<=', $value))
            ->latest('tanggal')->latest('id')
            ->paginate(min(max($request->integer('per_page', 12), 1), 50));

        $ringkasan = Penjualan::query()
            ->selectRaw('COUNT(*) as jumlah_transaksi')
            ->selectRaw('COALESCE(SUM(grandtotal), 0) as total_penjualan')
            ->selectRaw('COALESCE(SUM(jumlahitem), 0) as jumlah_item')
            ->whereDate('tanggal', today())->first();

        return new JsonResponse([...$data->toArray(), 'ringkasan' => $ringkasan]);
    }

    public function show(int $id): JsonResponse
    {
        return new JsonResponse(['data' => Penjualan::with([
            'pengguna:id,name',
            'pelanggan:id,nama,telepon',
            'rincian.barang:id,kodebarang,namabarang',
        ])->findOrFail($id)]);
    }

    public function store(Request $request, StokFifoService $stokService): JsonResponse
    {
        $data = $request->validate([
            'cara_bayar' => ['required', Rule::in(['CASH', 'DEBIT', 'QRIS', 'HUTANG'])],
            'dibayar' => ['required', 'numeric', 'min:0'],
            'pelanggan_id' => ['nullable', 'integer', 'required_if:cara_bayar,HUTANG', 'exists:mpelanggan,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_id' => ['required', 'integer', 'exists:mbarang,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.jenis_satuan' => ['required', Rule::in(['KECIL', 'BESAR'])],
        ]);

        $penjualan = DB::transaction(function () use ($data, $request, $stokService) {
            $pelangganId = $data['pelanggan_id'] ?? null;
            if ($data['cara_bayar'] === 'HUTANG') {
                $pelanggan = Pelanggan::query()->lockForUpdate()->findOrFail($pelangganId);
                if ((int) $pelanggan->flaging === 1) {
                    throw ValidationException::withMessages([
                        'pelanggan_id' => 'Pelanggan yang dipilih sudah tidak aktif',
                    ]);
                }
            }

            $penjualan = Penjualan::create([
                'nomortransaksi' => $this->nextNumber(),
                'tanggal' => now(),
                'pelanggan_id' => $data['cara_bayar'] === 'HUTANG' ? $pelangganId : null,
                'cara_bayar' => $data['cara_bayar'],
                'created_by' => $request->user()->id,
            ]);
            $jumlahItem = $subtotal = $totalHpp = 0;

            foreach ($data['items'] as $item) {
                $barang = Barang::lockForUpdate()->findOrFail($item['barang_id']);
                $qty = (float) $item['qty'];
                $satuanBesar = $item['jenis_satuan'] === 'BESAR';
                if (
                    $satuanBesar
                    && (
                        !$barang->satuanbesar
                        || (int) $barang->isisatuan <= 0
                        || (float) $barang->hargajual_satuanbesar <= 0
                    )
                ) {
                    throw ValidationException::withMessages([
                        'satuan' => "Data satuan besar untuk {$barang->namabarang} belum lengkap di Master Barang",
                    ]);
                }
                $konversi = $satuanBesar ? max((int) $barang->isisatuan, 1) : 1;
                $qtyKecil = $qty * $konversi;
                $harga = $satuanBesar
                    ? (float) $barang->hargajual_satuanbesar
                    : (float) $barang->hargajual_satuankecil;
                $satuan = $satuanBesar ? $barang->satuanbesar : $barang->satuankecil;
                $hargaJualPerUnitKecil = $harga / $konversi;
                $total = $qty * $harga;
                $fifo = $stokService->keluarkanFifo(
                    $barang, $qtyKecil, 'PENJUALAN', $penjualan->id,
                    $penjualan->nomortransaksi, $request->user()->id,
                    'Penjualan '.$penjualan->nomortransaksi,
                    $hargaJualPerUnitKecil,
                );
                $penjualan->rincian()->create([
                    'barang_id' => $barang->id,
                    'qty' => $qty,
                    'qty_kecil' => $qtyKecil,
                    'konversi' => $konversi,
                    'satuan' => $satuan,
                    'harga' => $harga,
                    'subtotal' => $total,
                    'hpp' => $fifo['nilai'],
                    'alokasi_fifo' => $fifo['alokasi'],
                ]);
                $jumlahItem += $qtyKecil;
                $subtotal += $total;
                $totalHpp += $fifo['nilai'];
            }

            $hutang = $data['cara_bayar'] === 'HUTANG';
            $dibayar = $hutang
                ? 0
                : ($data['cara_bayar'] === 'CASH' ? (float) $data['dibayar'] : $subtotal);
            if (!$hutang && $dibayar < $subtotal) {
                throw ValidationException::withMessages(['dibayar' => 'Jumlah pembayaran kurang dari total transaksi']);
            }
            $penjualan->update([
                'jumlahitem' => $jumlahItem,
                'subtotal' => $subtotal,
                'grandtotal' => $subtotal,
                'dibayar' => $dibayar,
                'kembalian' => $hutang ? 0 : $dibayar - $subtotal,
                'sisa_hutang' => $hutang ? $subtotal : 0,
                'hpp' => $totalHpp,
                'status' => $hutang ? 'HUTANG' : 'SELESAI',
            ]);
            return $penjualan;
        });

        return new JsonResponse([
            'message' => 'Penjualan berhasil disimpan',
            'data' => $penjualan->load([
                'pengguna:id,name',
                'pelanggan:id,nama,telepon',
                'rincian.barang:id,kodebarang,namabarang',
            ]),
        ], 201);
    }

    private function nextNumber(): string
    {
        $prefix = 'JUAL-'.now()->format('Ymd').'-';
        $last = Penjualan::where('nomortransaksi', 'like', $prefix.'%')->lockForUpdate()->max('nomortransaksi');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
