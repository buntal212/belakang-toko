<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Master\SupplierRequest;
use App\Models\Master\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $data = Supplier::query()
            ->when($request->search, function ($query, $search) {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%")
                    ->orWhere('telepon', 'like', "%{$search}%")
                    ->orWhere('rekening', 'like', "%{$search}%");
            })
            ->where(function ($query) {
                $query->whereNull('flaging')
                    ->orWhere('flaging', '!=', '1');
            })
            ->orderBy('nama', 'asc')
            ->simplePaginate(min(max($request->integer('per_page', 15), 1), 50));

        return new JsonResponse($data);
    }

    public function indexall()
    {
        $data = Supplier::query()
            ->where(function ($query) {
                $query->whereNull('flaging')
                    ->orWhere('flaging', '!=', '1');
            })
            ->orderBy('nama', 'asc')
            ->get();

        return new JsonResponse($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::updateOrCreate(
            [
                'id' => $request->id
            ],
            [
                'nama' => strtoupper($request->nama),
                'alamat' => $request->alamat,
                'telepon' => $request->telepon,
                'rekening' => $request->rekening,
            ]
        );

        return new JsonResponse([
            'message' => $request->id
                ? 'Data supplier berhasil diperbarui'
                : 'Data supplier berhasil disimpan',
            'data' => $supplier
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);

        return new JsonResponse([
            'data' => $supplier
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update(['flaging' => 1]);

        return new JsonResponse([
            'message' => 'Data supplier berhasil dihapus'
        ]);
    }
}
