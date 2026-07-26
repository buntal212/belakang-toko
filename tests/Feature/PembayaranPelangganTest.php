<?php

use App\Models\Master\Pelanggan;
use App\Models\Penjualan\Penjualan;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('mencatat uang masuk dan mengalokasikan pembayaran ke nota hutang tertua', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $pelanggan = Pelanggan::create(['nama' => 'PELANGGAN HUTANG']);
    $notaLama = Penjualan::create([
        'nomortransaksi' => 'JUAL-HUTANG-001', 'tanggal' => now()->subDay(),
        'pelanggan_id' => $pelanggan->id, 'cara_bayar' => 'HUTANG', 'jumlahitem' => 1,
        'subtotal' => 60000, 'grandtotal' => 60000, 'dibayar' => 0, 'kembalian' => 0,
        'sisa_hutang' => 60000, 'hpp' => 40000, 'status' => 'HUTANG', 'created_by' => $user->id,
    ]);
    $notaBaru = Penjualan::create([
        'nomortransaksi' => 'JUAL-HUTANG-002', 'tanggal' => now(),
        'pelanggan_id' => $pelanggan->id, 'cara_bayar' => 'HUTANG', 'jumlahitem' => 1,
        'subtotal' => 40000, 'grandtotal' => 40000, 'dibayar' => 0, 'kembalian' => 0,
        'sisa_hutang' => 40000, 'hpp' => 25000, 'status' => 'HUTANG', 'created_by' => $user->id,
    ]);

    $this->getJson("/api/v1/penjualan/pembayaran-pelanggan/preview/{$pelanggan->id}")
        ->assertOk()->assertJsonPath('data.total_hutang', 100000)->assertJsonPath('data.jumlah_nota', 2);

    $response = $this->postJson('/api/v1/penjualan/pembayaran-pelanggan', [
        'pelanggan_id' => $pelanggan->id,
        'nominal' => 75000,
        'metode_pembayaran' => 'CASH',
        'catatan' => 'Uang masuk dari pelanggan',
    ])->assertCreated()
        ->assertJsonPath('data.nominal', '75000.00')
        ->assertJsonPath('data.rincian.0.penjualan.id', $notaLama->id)
        ->assertJsonPath('data.rincian.0.nominal', '60000.00')
        ->assertJsonPath('data.rincian.1.penjualan.id', $notaBaru->id)
        ->assertJsonPath('data.rincian.1.nominal', '15000.00');

    $this->assertDatabaseHas('tpenjualan', ['id' => $notaLama->id, 'sisa_hutang' => 0, 'status' => 'SELESAI']);
    $this->assertDatabaseHas('tpenjualan', ['id' => $notaBaru->id, 'sisa_hutang' => 25000, 'status' => 'HUTANG']);
    $this->assertDatabaseHas('tpembayaran_pelanggan', ['id' => $response->json('data.id'), 'nominal' => 75000]);

    $this->getJson("/api/v1/penjualan/pembayaran-pelanggan/preview/{$pelanggan->id}")
        ->assertOk()
        ->assertJsonPath('data.total_hutang', 25000)
        ->assertJsonPath('data.jumlah_nota', 1)
        ->assertJsonPath('data.total_terbayar', 75000)
        ->assertJsonPath('data.jumlah_nota_terbayar', 1);

    $this->getJson('/api/v1/penjualan/pembayaran-pelanggan')
        ->assertOk()
        ->assertJsonPath('data.0.rincian.0.penjualan.nomortransaksi', 'JUAL-HUTANG-001')
        ->assertJsonPath('data.0.rincian.1.penjualan.nomortransaksi', 'JUAL-HUTANG-002')
        ->assertJsonPath('ringkasan.total_uang_masuk', 75000);
});
