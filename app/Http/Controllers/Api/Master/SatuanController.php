<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Master\SatuanRequest;
use App\Http\Requests\Master\Satuan\StoreSatuanRequest;
use App\Models\Master\Satuan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SatuanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       $data = Satuan::query()
            ->when(request('search'), fn($q, $search) =>
                $q->where('satuan', 'like', "%{$search}%")
            )
             ->where(function ($query) {
                $query->whereNull('flaging')
                    ->orWhere('flaging', '!=', '1');
            })
            ->orderBy('satuan')
            ->simplePaginate(min(max(request()->integer('per_page', 15), 1), 50));

        return new JsonResponse($data);
        }

    /**
     * Display all active units for select options.
     */
    public function getAll(): JsonResponse
    {
        $data = Satuan::query()
            ->select([
                'satuan as label',
                'satuan as value',
            ])
            ->where(function ($query) {
                $query->whereNull('flaging')
                    ->orWhere('flaging', '!=', '1');
            })
            ->orderBy('satuan', 'asc')
            ->get();

        return new JsonResponse($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SatuanRequest $request): JsonResponse
    {
        $data = $request->validated();

        $satuan = Satuan::updateOrCreate(
            [
               'id' => $request->id
            ],
            [
                'satuan' => strtoupper($data['satuan'])
            ]
        );

        return new JsonResponse([
            'message' => 'Data satuan berhasil disimpan',
            'data' => $satuan,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $satuan = Satuan::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail satuan berhasil diambil',
            'data' => $satuan,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SatuanRequest $request, $id): JsonResponse
    {
        $satuan = Satuan::findOrFail($id);
        $satuan->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data satuan berhasil diupdate',
            'data' => $satuan,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $satuan = Satuan::findOrFail($id);
        $satuan->update(['flaging' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Data satuan berhasil dihapus',
        ]);
    }
}
