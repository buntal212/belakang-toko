<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Master\MerkRequest;
use App\Models\Master\Merk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerkController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $data = Merk::query()
            ->when($request->search, function ($query, $search) {
                $query->where('merk', 'like', "%{$search}%");
            })
            ->where(function ($query) {
                $query->whereNull('flaging')
                    ->orWhere('flaging', '!=', 1);
            })
            ->orderBy('merk', 'asc')
            ->simplePaginate(min(max($request->integer('per_page', 15), 1), 50));

        return new JsonResponse($data);
    }

    /**
     * Display all active brands for select options.
     */
    public function getAll(): JsonResponse
    {
        $data = Merk::query()
            ->select([
                'merk as label',
                'merk as value',
            ])
            ->where(function ($query) {
                $query->whereNull('flaging')
                    ->orWhere('flaging', '!=', 1);
            })
            ->orderBy('merk', 'asc')
            ->get();

        return new JsonResponse($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MerkRequest $request): JsonResponse
    {
        $merk = Merk::updateOrCreate(
            [
                'id' => $request->id
            ],
            [
                'merk' => strtoupper($request->merk),
                'flaging' => 0
            ]
        );

        return new JsonResponse([
            'message' => $request->id
                ? 'Data merk berhasil diperbarui'
                : 'Data merk berhasil disimpan',
            'data' => $merk
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $merk = Merk::findOrFail($id);

        return new JsonResponse([
            'data' => $merk
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        $merk = Merk::findOrFail($id);
        $merk->update([
            'flaging' => 1
        ]);

        return new JsonResponse([
            'message' => 'Data merk berhasil dihapus'
        ]);
    }
}
