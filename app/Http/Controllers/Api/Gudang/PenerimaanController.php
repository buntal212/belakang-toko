<?php

namespace App\Http\Controllers\Api\Gudang;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Gudang\PenerimaanRequest;
use App\Models\Gudang\Penerimaan;
use App\Services\Stok\StokFifoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PenerimaanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Penerimaan::query()
            ->with('supplier:id,nama')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nomortransaksi', 'like', "%{$search}%")
                        ->orWhere('nomorfaktur', 'like', "%{$search}%");
                });
            })
            ->when($request->supplier_id, fn ($query, $id) => $query->where('supplier_id', $id))
            ->when($request->tanggal_awal, fn ($query, $date) => $query->whereDate('tanggal', '>=', $date))
            ->when($request->tanggal_akhir, fn ($query, $date) => $query->whereDate('tanggal', '<=', $date))
            ->latest('tanggal')
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 12), 1), 50));

        return new JsonResponse($data);
    }

    public function show(int $id): JsonResponse
    {
        return new JsonResponse(['data' => $this->load(Penerimaan::findOrFail($id))]);
    }

    public function store(PenerimaanRequest $request): JsonResponse
    {
        $penerimaan = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $penerimaan = empty($data['id'])
                ? new Penerimaan()
                : Penerimaan::lockForUpdate()->findOrFail($data['id']);

            if ($penerimaan->exists && ((int) $penerimaan->flaging === 1 || $penerimaan->stok_terkirim_at)) {
                throw ValidationException::withMessages([
                    'penerimaan' => 'Penerimaan yang sudah dikirim ke stok tidak dapat diubah',
                ]);
            }

            if (!$penerimaan->exists) {
                $penerimaan->nomortransaksi = $this->nextNumber();
                $penerimaan->created_by = $request->user()->id;
            }

            $penerimaan->fill([
                'nomorfaktur' => $data['nomorfaktur'] ?? null,
                'tanggal' => $data['tanggal'],
                'tglfaktur' => $data['tglfaktur'] ?? null,
                'supplier_id' => $data['supplier_id'],
                'cara_bayar' => $data['cara_bayar'],
                'catatan' => $data['catatan'] ?? null,
                'status' => 'Draft',
                'flaging' => 0,
            ])->save();

            $penerimaan->rincian()->delete();
            $subtotal = 0;
            $totalDiskon = 0;
            $jumlahItem = 0;

            foreach ($data['rincian'] as $detail) {
                $qtyBesar = (float) $detail['qtybesar'];
                $isi = (int) $detail['isi'];
                $qtyKecil = $qtyBesar * $isi;
                $hargaBeli = (float) $detail['hargabeli'];
                $nilaiAwal = $qtyBesar * $hargaBeli;
                $diskonPersen = (float) ($detail['diskonpersen'] ?? 0);
                $diskon = min(
                    (float) ($detail['diskonnominal'] ?? 0) + ($nilaiAwal * $diskonPersen / 100),
                    $nilaiAwal
                );
                $total = max($nilaiAwal - $diskon, 0);

                $penerimaan->rincian()->create([
                    'barang_id' => $detail['barang_id'],
                    'qtybesar' => $qtyBesar,
                    'isi' => $isi,
                    'qtykecil' => $qtyKecil,
                    'hargabeli' => $hargaBeli,
                    'hargakecil' => $isi > 0 ? $hargaBeli / $isi : 0,
                    'diskonpersen' => $diskonPersen,
                    'diskonnominal' => $diskon,
                    'subtotal' => $nilaiAwal,
                    'total' => $total,
                ]);

                $jumlahItem += $qtyKecil;
                $subtotal += $nilaiAwal;
                $totalDiskon += $diskon;
            }

            $dasarPajak = max($subtotal - $totalDiskon, 0);
            $pajak = $dasarPajak * ((float) ($data['pajakpersen'] ?? 0) / 100);
            $grandTotal = $dasarPajak + $pajak;
            $penerimaan->update([
                'jumlahitem' => $jumlahItem,
                'subtotal' => $subtotal,
                'diskon' => $totalDiskon,
                'pajak' => $pajak,
                'grandtotal' => $grandTotal,
                'dibayar' => $data['cara_bayar'] === 'HUTANG' ? 0 : $grandTotal,
                'sisa_hutang' => $data['cara_bayar'] === 'HUTANG' ? $grandTotal : 0,
            ]);

            return $penerimaan;
        });

        return new JsonResponse([
            'message' => $request->id
                ? 'Penerimaan barang berhasil diperbarui'
                : 'Penerimaan barang berhasil disimpan',
            'data' => $this->load($penerimaan),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::transaction(function () use ($id) {
            $penerimaan = Penerimaan::lockForUpdate()->findOrFail($id);
            if ((int) $penerimaan->flaging === 1 || $penerimaan->stok_terkirim_at) {
                throw ValidationException::withMessages([
                    'penerimaan' => 'Penerimaan yang sudah dikirim ke stok tidak dapat dihapus',
                ]);
            }
            $penerimaan->delete();
        });

        return new JsonResponse(['message' => 'Penerimaan barang berhasil dihapus']);
    }

    public function kirimStok(int $id, Request $request, StokFifoService $service): JsonResponse
    {
        $penerimaan = $service->kirimPenerimaan(
            Penerimaan::findOrFail($id),
            $request->user()->id,
        );

        return new JsonResponse([
            'message' => 'Penerimaan berhasil dikirim ke stok',
            'data' => $penerimaan,
        ]);
    }

    private function nextNumber(): string
    {
        $prefix = 'TRM-'.now()->format('Ymd').'-';
        $last = Penerimaan::query()
            ->where('nomortransaksi', 'like', $prefix.'%')
            ->lockForUpdate()
            ->max('nomortransaksi');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function load(Penerimaan $penerimaan): Penerimaan
    {
        return $penerimaan->load([
            'supplier:id,nama',
            'rincian.barang:id,kodebarang,namabarang,satuanbesar,satuankecil',
        ]);
    }
}
