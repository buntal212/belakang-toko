<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Master\PelangganRequest;
use App\Models\Master\Pelanggan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PelangganController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $data = Pelanggan::query()
            ->where(function ($query) {
                $query->whereNull('flaging')
                    ->orWhere('flaging', '!=', 1);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('nama', 'like', "%{$search}%")
                        ->orWhere('alamat', 'like', "%{$search}%")
                        ->orWhere('telepon', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama')
            ->simplePaginate(min(max($request->integer('per_page', 12), 1), 50));

        return new JsonResponse($data);
    }

    public function store(PelangganRequest $request): JsonResponse
    {
        $pelanggan = Pelanggan::updateOrCreate(
            ['id' => $request->integer('id') ?: null],
            [
                'nama' => strtoupper($request->string('nama')->toString()),
                'alamat' => $this->uppercaseNullable($request->input('alamat')),
                'telepon' => $request->input('telepon'),
                'email' => strtolower((string) $request->input('email')),
                'flaging' => 0,
            ],
        );

        return new JsonResponse([
            'message' => $request->filled('id')
                ? 'Data pelanggan berhasil diperbarui'
                : 'Data pelanggan berhasil disimpan',
            'data' => $pelanggan,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return new JsonResponse([
            'data' => Pelanggan::query()->findOrFail($id),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        Pelanggan::query()->findOrFail($id)->update(['flaging' => 1]);

        return new JsonResponse([
            'message' => 'Data pelanggan berhasil dihapus',
        ]);
    }

    private function uppercaseNullable(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : strtoupper($normalized);
    }
}
