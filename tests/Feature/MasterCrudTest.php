<?php

use App\Models\Master\Barang;
use App\Models\Master\Merk;
use App\Models\Master\Pelanggan;
use App\Models\Master\Satuan;
use App\Models\Master\Supplier;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

it('menjalankan CRUD satuan', function () {
    $id = $this->postJson('/api/v1/master/satuan/simpan', ['satuan' => 'Dus'])
        ->assertOk()->json('data.id');

    $this->getJson('/api/v1/master/satuan/get-satuan')
        ->assertOk()->assertJsonPath('data.0.satuan', 'DUS');

    $this->postJson('/api/v1/master/satuan/simpan', ['id' => $id, 'satuan' => 'Karton'])
        ->assertOk()->assertJsonPath('data.satuan', 'KARTON');

    $this->deleteJson("/api/v1/master/satuan/hapus/{$id}")->assertOk();
    expect(Satuan::find($id)->flaging)->toBe(1);
});

it('menjalankan CRUD supplier', function () {
    $id = $this->postJson('/api/v1/master/supplier/simpan', [
        'nama' => 'Supplier Utama', 'telepon' => '08123',
    ])->assertOk()->json('data.id');

    $this->getJson('/api/v1/master/supplier/get-data')
        ->assertOk()->assertJsonPath('data.0.nama', 'SUPPLIER UTAMA');

    $this->postJson('/api/v1/master/supplier/simpan', [
        'id' => $id, 'nama' => 'Supplier Baru',
    ])->assertOk()->assertJsonPath('data.nama', 'SUPPLIER BARU');

    $this->deleteJson("/api/v1/master/supplier/hapus/{$id}")->assertOk();
    expect(Supplier::find($id)->flaging)->toBe(1);
});

it('menjalankan CRUD merk', function () {
    $id = $this->postJson('/api/v1/master/merk/simpan', ['merk' => 'Maju'])
        ->assertOk()->json('data.id');

    $this->getJson('/api/v1/master/merk/get-data')
        ->assertOk()->assertJsonPath('data.0.merk', 'MAJU');

    $this->postJson('/api/v1/master/merk/simpan', ['id' => $id, 'merk' => 'Makmur'])
        ->assertOk()->assertJsonPath('data.merk', 'MAKMUR');

    $this->deleteJson("/api/v1/master/merk/hapus/{$id}")->assertOk();
    expect(Merk::find($id)->flaging)->toBe(1);
});

it('menjalankan CRUD barang', function () {
    $payload = [
        'kodebarang' => 'BRG0001',
        'namabarang' => 'Air Mineral',
        'jenisbarang' => 'Minuman',
        'keterangan' => '600 ML',
        'merk' => 'SEGAR',
        'satuanbesar' => 'DUS',
        'satuankecil' => 'BOTOL',
        'isisatuan' => 24,
        'limitstok' => 10,
        'hargajual_satuankecil' => 4000,
        'hargajual_satuanbesar' => 90000,
    ];
    $id = $this->postJson('/api/v1/master/barang/simpan', $payload)
        ->assertOk()->json('data.id');

    $this->getJson('/api/v1/master/barang/get-data')
        ->assertOk()
        ->assertJsonPath('data.0.kodebarang', 'BRG0001')
        ->assertJsonPath('data.0.jenisbarang', 'MINUMAN')
        ->assertJsonPath('data.0.keterangan', '600 ML');

    $this->postJson('/api/v1/master/barang/simpan', [
        ...$payload, 'namabarang' => 'Nama ini diabaikan', 'keterangan' => '1 Liter',
    ])->assertOk()->assertJsonPath('data.namabarang', 'MINUMAN SEGAR 1 LITER');

    $this->postJson('/api/v1/master/barang/hapus', ['id' => $id])->assertOk();
    expect(Barang::find($id)->flaging)->toBe(1);
});

it('menjalankan CRUD pelanggan', function () {
    $id = $this->postJson('/api/v1/master/pelanggan/simpan', [
        'nama' => 'Budi Santoso',
        'alamat' => 'Jalan Merdeka',
        'telepon' => '08123456789',
        'email' => 'BUDI@EXAMPLE.COM',
    ])->assertOk()
        ->assertJsonPath('data.nama', 'BUDI SANTOSO')
        ->assertJsonPath('data.alamat', 'JALAN MERDEKA')
        ->assertJsonPath('data.email', 'budi@example.com')
        ->json('data.id');

    $this->getJson('/api/v1/master/pelanggan/get-data?search=08123456789')
        ->assertOk()
        ->assertJsonPath('data.0.id', $id);

    $this->postJson('/api/v1/master/pelanggan/simpan', [
        'id' => $id,
        'nama' => 'Budi Baru',
        'email' => 'baru@example.com',
    ])->assertOk()
        ->assertJsonPath('data.nama', 'BUDI BARU');

    $this->getJson("/api/v1/master/pelanggan/detail/{$id}")
        ->assertOk()->assertJsonPath('data.id', $id);

    $this->deleteJson("/api/v1/master/pelanggan/hapus/{$id}")->assertOk();
    expect(Pelanggan::find($id)->flaging)->toBe(1);
});
