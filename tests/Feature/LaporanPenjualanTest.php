<?php

use App\Models\Master\Pelanggan;
use App\Models\Penjualan\Penjualan;
use App\Models\Penjualan\SetoranBendahara;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('menampilkan laporan penjualan beserta filter dan ringkasan', function () {
    $kasir = User::factory()->create();
    Sanctum::actingAs($kasir);
    $pelanggan = Pelanggan::create([
        'nama' => 'Pelanggan Laporan',
    ]);
    $setoran = SetoranBendahara::create([
        'nomor_setoran' => 'SETOR-LAP-001',
        'tanggal' => now(),
        'kasir_id' => $kasir->id,
        'periode_mulai' => now(),
        'periode_sampai' => now(),
        'created_by' => $kasir->id,
    ]);
    Penjualan::create([
        'nomortransaksi' => 'JUAL-LAP-001',
        'tanggal' => now(),
        'pelanggan_id' => $pelanggan->id,
        'setoran_bendahara_id' => $setoran->id,
        'cara_bayar' => 'HUTANG',
        'jumlahitem' => 2,
        'subtotal' => 100000,
        'grandtotal' => 90000,
        'dibayar' => 40000,
        'kembalian' => 0,
        'sisa_hutang' => 50000,
        'hpp' => 60000,
        'status' => 'HUTANG',
        'created_by' => $kasir->id,
    ]);

    $tanggal = now()->toDateString();
    $this->getJson("/api/v1/laporan/penjualan?tanggal_awal={$tanggal}&tanggal_akhir={$tanggal}&kasir_id={$kasir->id}&cara_bayar=HUTANG")
        ->assertOk()
        ->assertJsonPath('data.0.nomortransaksi', 'JUAL-LAP-001')
        ->assertJsonPath('data.0.pengguna.id', $kasir->id)
        ->assertJsonPath('data.0.pelanggan.id', $pelanggan->id)
        ->assertJsonPath('data.0.setoran_bendahara.nomor_setoran', 'SETOR-LAP-001')
        ->assertJsonPath('data.0.setoran_bendahara.kasir.id', $kasir->id)
        ->assertJsonPath('total', 1)
        ->assertJsonPath('ringkasan.jumlah_transaksi', 1)
        ->assertJsonPath('ringkasan.omzet', 90000)
        ->assertJsonPath('ringkasan.hpp', 60000)
        ->assertJsonPath('ringkasan.laba_kotor', 30000)
        ->assertJsonPath('ringkasan.piutang', 50000);

    $this->get("/api/v1/laporan/penjualan/excel?tanggal_awal={$tanggal}&tanggal_akhir={$tanggal}")
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $this->get("/api/v1/laporan/penjualan/pdf?tanggal_awal={$tanggal}&tanggal_akhir={$tanggal}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
