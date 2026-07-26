<?php

use App\Models\Gudang\Penerimaan;
use App\Models\Master\Supplier;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('mencatat uang keluar dan mengalokasikan pembayaran ke faktur PBF tertua', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $supplier = Supplier::create(['nama' => 'PBF SEHAT']);
    $lama = Penerimaan::create([
        'nomortransaksi' => 'TRM-PBF-001', 'nomorfaktur' => 'F-PBF-001',
        'tanggal' => now()->subDay(), 'supplier_id' => $supplier->id, 'cara_bayar' => 'HUTANG',
        'status' => 'Terkunci', 'grandtotal' => 60000, 'dibayar' => 0, 'sisa_hutang' => 60000,
        'created_by' => $user->id,
    ]);
    $baru = Penerimaan::create([
        'nomortransaksi' => 'TRM-PBF-002', 'nomorfaktur' => 'F-PBF-002',
        'tanggal' => now(), 'supplier_id' => $supplier->id, 'cara_bayar' => 'HUTANG',
        'status' => 'Terkunci', 'grandtotal' => 40000, 'dibayar' => 0, 'sisa_hutang' => 40000,
        'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/keuangan/pembayaran-hutang-pbf/preview/{$supplier->id}")
        ->assertOk()->assertJsonPath('data.total_hutang', 100000)->assertJsonPath('data.jumlah_faktur', 2);
    $response = $this->postJson('/api/v1/keuangan/pembayaran-hutang-pbf', [
        'supplier_id' => $supplier->id, 'nominal' => 75000,
        'metode_pembayaran' => 'TRANSFER', 'catatan' => 'Transfer ke PBF',
    ])->assertCreated()
        ->assertJsonPath('data.nominal', '75000.00')
        ->assertJsonPath('data.rincian.0.penerimaan.id', $lama->id)
        ->assertJsonPath('data.rincian.0.nominal', '60000.00')
        ->assertJsonPath('data.rincian.1.penerimaan.id', $baru->id)
        ->assertJsonPath('data.rincian.1.nominal', '15000.00');
    $this->assertDatabaseHas('tpenerimaan', ['id' => $lama->id, 'dibayar' => 60000, 'sisa_hutang' => 0]);
    $this->assertDatabaseHas('tpenerimaan', ['id' => $baru->id, 'dibayar' => 15000, 'sisa_hutang' => 25000]);
    $this->assertDatabaseHas('tpembayaran_pbf', ['id' => $response->json('data.id'), 'nominal' => 75000]);
    $this->getJson("/api/v1/keuangan/pembayaran-hutang-pbf/preview/{$supplier->id}")
        ->assertOk()->assertJsonPath('data.total_terbayar', 75000)->assertJsonPath('data.jumlah_faktur_lunas', 1);
});
