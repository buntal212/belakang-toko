<?php

namespace App\Http\Controllers\Api\Master;

use App\Helpers\Formating\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Master\BarangRequest;
use App\Models\Master\Barang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BarangController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $data = Barang::query()
            ->when($request->search, function ($query, $search) {
                $query->where('kodebarang', 'like', "%{$search}%")
                    ->orWhere('namabarang', 'like', "%{$search}%")
                    ->orWhere('jenisbarang', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%")
                    ->orWhere('merk', 'like', "%{$search}%");
            })
            ->where(function ($query) {
                $query->whereNull('flaging')
                    ->orWhere('flaging', '!=', 1);
            })
            ->orderBy('namabarang', 'asc')
            ->simplePaginate(min(max($request->integer('per_page', 12), 1), 50));

        return new JsonResponse($data);
    }

    public function indexall()
    {
        $data = Barang::query()
            ->where(function ($query) {
                $query->whereNull('flaging')
                    ->orWhere('flaging', '!=', 1);
            })
            ->orderBy('namabarang', 'asc')
            ->get();

        return new JsonResponse($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BarangRequest $request): JsonResponse
    {
        $kodebarang =  $request->kodebarang ?? null;
        $namabarang = collect([
            $request->jenisbarang,
            $request->merk,
            $request->keterangan,
        ])->filter(fn ($value) => filled($value))
            ->map(fn ($value) => strtoupper(trim($value)))
            ->implode(' ');

        if(!$kodebarang)
        {
            DB::select('call masterbarang(@nomor)');
            $nomor = DB::table('counter')->value('masterbarang');
            $kodebarang = FormatingHelper::genKodeMaster($nomor,'BRG');
        }

        $barang = Barang::updateOrCreate(

            [
                'kodebarang' => $kodebarang
            ],
            [
                'kodebarcode' => $request->kodebarcode,
                'namabarang' => $namabarang,
                'jenisbarang' => $request->jenisbarang
                    ? strtoupper($request->jenisbarang)
                    : null,
                'keterangan' => $request->keterangan
                    ? strtoupper($request->keterangan)
                    : null,
                'merk' => strtoupper($request->merk),
                'satuanbesar' => strtoupper($request->satuanbesar),
                'satuankecil' => strtoupper($request->satuankecil),
                'isisatuan' => $request->isisatuan,
                'limitstok' => $request->limitstok,
                'hargajual_satuankecil' => $request->hargajual_satuankecil,
                'hargajual_satuanbesar' => $request->hargajual_satuanbesar,
            ]
        );

        return new JsonResponse([
            'message' => $request->id
                ? 'Data barang berhasil diperbarui'
                : 'Data barang berhasil disimpan',
            'data' => $barang
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $barang = Barang::findOrFail($id);

        return new JsonResponse([
            'data' => $barang
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $barang = Barang::findOrFail($request->id);
        $barang->update([
            'flaging' => 1
        ]);

        return new JsonResponse([
            'message' => 'Data barang berhasil dihapus'
        ]);
    }
}
