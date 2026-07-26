<?php

use App\Models\Penjualan\Penjualan;
use App\Models\Penjualan\PembayaranPelanggan;
use App\Models\Penjualan\ReturPenjualan;
use App\Models\Master\Pelanggan;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

it('menghitung dan menyimpan setoran ke bendahara per kasir', function () {
    $kasir = User::query()->first();
    $pembuat = User::factory()->create();
    $penjualan = Penjualan::create([
        'nomortransaksi' => 'JUAL-SETOR-001',
        'tanggal' => now()->subMinutes(20),
        'cara_bayar' => 'CASH',
        'jumlahitem' => 2,
        'subtotal' => 100000,
        'grandtotal' => 100000,
        'dibayar' => 100000,
        'kembalian' => 0,
        'hpp' => 50000,
        'status' => 'SELESAI',
        'created_by' => $kasir->id,
    ]);
    ReturPenjualan::create([
        'nomorretur' => 'RET-SETOR-001',
        'penjualan_id' => $penjualan->id,
        'tanggal' => now()->subMinutes(10),
        'alasan' => 'Retur untuk test setoran',
        'metode_pengembalian' => 'CASH',
        'jumlahitem' => 1,
        'total' => 20000,
        'status' => 'SELESAI',
        'created_by' => $kasir->id,
    ]);

    $tanggal = now()->toDateString();
    $kasirLain = User::factory()->create();
    Penjualan::create([
        'nomortransaksi' => 'JUAL-SETOR-KASIR-LAIN',
        'tanggal' => now()->subMinutes(5),
        'cara_bayar' => 'CASH',
        'jumlahitem' => 1,
        'subtotal' => 50000,
        'grandtotal' => 50000,
        'dibayar' => 50000,
        'kembalian' => 0,
        'hpp' => 25000,
        'status' => 'SELESAI',
        'created_by' => $kasirLain->id,
    ]);
    $this->actingAs($pembuat)
        ->getJson("/api/v1/penjualan/setoran-bendahara/preview?tanggal_awal={$tanggal}&tanggal_akhir={$tanggal}&kasir_id=all")
        ->assertOk()
        ->assertJsonPath('data.jumlah_penjualan', 2)
        ->assertJsonPath('data.kasir', null);

    $this->actingAs($pembuat)->getJson("/api/v1/penjualan/setoran-bendahara/preview?tanggal_awal={$tanggal}&tanggal_akhir={$tanggal}&kasir_id={$kasir->id}")
        ->assertOk()
        ->assertJsonPath('data.jumlah_penjualan', 1)
        ->assertJsonPath('data.jumlah_retur', 1)
        ->assertJsonPath('data.penjualan_tunai', 100000)
        ->assertJsonPath('data.retur_tunai', 20000)
        ->assertJsonPath('data.seharusnya_disetor', 80000)
        ->assertJsonPath('data.penjualan.0.id', $penjualan->id)
        ->assertJsonPath('data.penjualan.0.nomortransaksi', 'JUAL-SETOR-001')
        ->assertJsonPath('data.penjualan.0.retur_tunai', 20000)
        ->assertJsonPath('data.penjualan.0.netto_tunai', 80000)
        ->assertJsonPath('data.penjualan.0.pengguna.id', $kasir->id)
        ->assertJsonPath('data.kasir.id', $kasir->id);

    $response = $this->actingAs($pembuat)->postJson('/api/v1/penjualan/setoran-bendahara/simpan', [
        'kasir_id' => 'all',
        'tanggal_awal' => $tanggal,
        'tanggal_akhir' => $tanggal,
        'penjualan_ids' => [$penjualan->id],
        'nominal_disetor' => 79000,
        'catatan' => 'Selisih kurang seribu',
    ])->assertCreated()
        ->assertJsonPath('data.seharusnya_disetor', '80000.00')
        ->assertJsonPath('data.nominal_disetor', '79000.00')
        ->assertJsonPath('data.selisih', '-1000.00')
        ->assertJsonPath('data.kasir.id', $kasir->id);

    $this->assertDatabaseHas('tsetoran_bendahara', [
        'id' => $response->json('data.id'),
        'kasir_id' => $kasir->id,
        'periode_mulai' => $penjualan->tanggal->format('Y-m-d H:i:s'),
        'periode_sampai' => $penjualan->tanggal->format('Y-m-d H:i:s'),
        'jumlah_penjualan' => 1,
        'jumlah_retur' => 1,
        'seharusnya_disetor' => 80000,
        'nominal_disetor' => 79000,
        'selisih' => -1000,
        'created_by' => $pembuat->id,
    ]);
    $this->assertDatabaseHas('tpenjualan', [
        'id' => $penjualan->id,
        'setoran_bendahara_id' => $response->json('data.id'),
    ]);
    $this->getJson('/api/v1/penjualan/get-data?retur_eligible=1&search='.$penjualan->nomortransaksi)
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/penjualan/retur/transaksi/{$penjualan->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('penjualan');

    $this->getJson('/api/v1/penjualan/setoran-bendahara/get-data?kasir_id='.$kasir->id)
        ->assertOk()
        ->assertJsonPath('data.0.id', $response->json('data.id'))
        ->assertJsonPath('data.0.kasir.username', $kasir->username)
        ->assertJsonPath('ringkasan.total_disetor', 79000);

    $this->getJson("/api/v1/penjualan/setoran-bendahara/preview?tanggal_awal={$tanggal}&tanggal_akhir={$tanggal}")
        ->assertOk()
        ->assertJsonPath('data.jumlah_penjualan', 0)
        ->assertJsonPath('data.jumlah_retur', 0)
        ->assertJsonPath('data.seharusnya_disetor', 0)
        ->assertJsonCount(0, 'data.penjualan');
});

it('menolak setoran jika belum ada transaksi tunai baru', function () {
    $this->postJson('/api/v1/penjualan/setoran-bendahara/simpan', [
        'nominal_disetor' => 0,
        'tanggal_awal' => now()->toDateString(),
        'tanggal_akhir' => now()->toDateString(),
        'penjualan_ids' => [999999],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('penjualan_ids.0');
});

it('memasukkan pembayaran pelanggan tunai ke setoran bendahara', function () {
    $kasir = User::query()->first();
    $pelanggan = Pelanggan::create(['nama' => 'PELANGGAN SETOR']);
    $pembayaran = PembayaranPelanggan::create([
        'nomor_pembayaran' => 'BAYAR-SETOR-001',
        'tanggal' => now(),
        'pelanggan_id' => $pelanggan->id,
        'nominal' => 45000,
        'metode_pembayaran' => 'CASH',
        'created_by' => $kasir->id,
    ]);
    $tanggal = now()->toDateString();

    $this->getJson("/api/v1/penjualan/setoran-bendahara/preview?tanggal_awal={$tanggal}&tanggal_akhir={$tanggal}")
        ->assertOk()
        ->assertJsonPath('data.jumlah_pembayaran_pelanggan', 1)
        ->assertJsonPath('data.total_pembayaran_pelanggan', 45000)
        ->assertJsonPath('data.seharusnya_disetor', 45000)
        ->assertJsonPath('data.pembayaran_pelanggan.0.id', $pembayaran->id);

    $response = $this->postJson('/api/v1/penjualan/setoran-bendahara/simpan', [
        'tanggal_awal' => $tanggal,
        'tanggal_akhir' => $tanggal,
        'penjualan_ids' => [],
        'pembayaran_pelanggan_ids' => [$pembayaran->id],
        'nominal_disetor' => 45000,
    ])->assertCreated()
        ->assertJsonPath('data.pembayaran_pelanggan', '45000.00')
        ->assertJsonPath('data.jumlah_pembayaran_pelanggan', 1);

    $this->assertDatabaseHas('tpembayaran_pelanggan', [
        'id' => $pembayaran->id,
        'setoran_bendahara_id' => $response->json('data.id'),
    ]);

    $this->getJson("/api/v1/penjualan/setoran-bendahara/preview?tanggal_awal={$tanggal}&tanggal_akhir={$tanggal}")
        ->assertOk()
        ->assertJsonCount(0, 'data.pembayaran_pelanggan');
});
