<?php

use App\Models\Master\Barang;
use App\Models\Master\Supplier;
use App\Models\User;
use App\Services\Stok\StokFifoService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

it('menyimpan penerimaan dan mengirim lot stok secara idempoten', function () {
    $supplier = Supplier::create(['nama' => 'SUPPLIER TEST']);
    $barang = Barang::create([
        'kodebarang' => 'BRG-TEST',
        'namabarang' => 'BARANG TEST',
        'satuanbesar' => 'DUS',
        'satuankecil' => 'PCS',
        'isisatuan' => 10,
        'hargajual_satuankecil' => 15000,
        'hargajual_satuanbesar' => 140000,
    ]);

    $payload = [
        'tanggal' => '2026-07-25',
        'tglfaktur' => '2026-07-24',
        'nomorfaktur' => 'F-001',
        'supplier_id' => $supplier->id,
        'cara_bayar' => 'HUTANG',
        'status' => 'Draft',
        'pajakpersen' => 10,
        'rincian' => [[
            'barang_id' => $barang->id,
            'qtybesar' => 2,
            'isi' => 10,
            'hargabeli' => 100000,
            'diskonpersen' => 5,
            'diskonnominal' => 0,
        ]],
    ];

    $response = $this->postJson('/api/v1/gudang/penerimaan/simpan', $payload)
        ->assertOk()
        ->assertJsonPath('data.jumlahitem', 20)
        ->assertJsonPath('data.subtotal', '200000.00')
        ->assertJsonPath('data.diskon', '10000.00')
        ->assertJsonPath('data.pajak', '19000.00')
        ->assertJsonPath('data.grandtotal', '209000.00');

    $id = $response->json('data.id');
    expect($response->json('data.nomortransaksi'))->toStartWith('TRM-'.now()->format('Ymd').'-');

    $this->getJson('/api/v1/gudang/penerimaan/get-data')
        ->assertOk()->assertJsonPath('data.0.id', $id);

    $this->getJson("/api/v1/gudang/penerimaan/detail/{$id}")
        ->assertOk()->assertJsonPath('data.rincian.0.barang.id', $barang->id);

    $this->postJson('/api/v1/gudang/penerimaan/simpan', [
        ...$payload, 'id' => $id, 'status' => 'Draft', 'pajakpersen' => 0,
    ])->assertOk()
        ->assertJsonPath('data.status', 'Draft')
        ->assertJsonPath('data.grandtotal', '190000.00');

    $this->postJson("/api/v1/gudang/penerimaan/kirim-stok/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'Terkunci')
        ->assertJsonPath('data.flaging', 1)
        ->assertJsonPath('data.cara_bayar', 'HUTANG')
        ->assertJsonStructure(['data' => ['stok_terkirim_at']]);

    $this->assertDatabaseHas('stok', [
        'penerimaan_id' => $id,
        'barang_id' => $barang->id,
        'qty_masuk' => 20,
        'qty_tersedia' => 20,
        'status' => 'TERSEDIA',
    ]);
    $this->assertDatabaseHas('stok_mutasi', [
        'barang_id' => $barang->id,
        'tipe' => 'MASUK',
        'qty_masuk' => 20,
        'saldo_sesudah' => 20,
    ]);

    $this->postJson("/api/v1/gudang/penerimaan/kirim-stok/{$id}")->assertUnprocessable();
    $this->postJson('/api/v1/gudang/penerimaan/simpan', [...$payload, 'id' => $id])
        ->assertUnprocessable();
    $this->deleteJson("/api/v1/gudang/penerimaan/hapus/{$id}")->assertUnprocessable();

    $draftId = $this->postJson('/api/v1/gudang/penerimaan/simpan', [
        ...$payload, 'nomorfaktur' => 'F-002',
    ])->assertOk()->json('data.id');
    $this->deleteJson("/api/v1/gudang/penerimaan/hapus/{$draftId}")->assertOk();
    $this->assertDatabaseMissing('tpenerimaan', ['id' => $draftId]);
});

it('menolak penerimaan tanpa rincian', function () {
    $supplier = Supplier::create(['nama' => 'SUPPLIER TEST']);

    $this->postJson('/api/v1/gudang/penerimaan/simpan', [
        'tanggal' => '2026-07-25',
        'supplier_id' => $supplier->id,
        'rincian' => [],
    ])->assertUnprocessable();
});

it('menolak kirim stok jika harga jual kecil atau besar di bawah harga perolehan', function () {
    $supplier = Supplier::create(['nama' => 'SUPPLIER VALIDASI HARGA']);
    $barang = Barang::create([
        'kodebarang' => 'BRG-HARGA-RENDAH',
        'namabarang' => 'BARANG HARGA RENDAH',
        'satuanbesar' => 'DUS',
        'satuankecil' => 'PCS',
        'isisatuan' => 10,
        'hargajual_satuankecil' => 9000,
        'hargajual_satuanbesar' => 90000,
    ]);
    $id = $this->postJson('/api/v1/gudang/penerimaan/simpan', [
        'tanggal' => '2026-07-25',
        'supplier_id' => $supplier->id,
        'cara_bayar' => 'CASH',
        'rincian' => [[
            'barang_id' => $barang->id,
            'qtybesar' => 1,
            'isi' => 10,
            'hargabeli' => 100000,
        ]],
    ])->assertOk()->json('data.id');

    $this->postJson("/api/v1/gudang/penerimaan/kirim-stok/{$id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('harga_jual')
        ->assertJsonPath('errors.harga_jual.0', fn ($message) =>
            str_contains($message, 'satuan besar')
            && str_contains($message, 'satuan kecil')
        );

    $this->assertDatabaseHas('tpenerimaan', ['id' => $id, 'status' => 'Draft', 'flaging' => 0]);
    $this->assertDatabaseMissing('stok', ['penerimaan_id' => $id]);
    $this->assertDatabaseMissing('stok_mutasi', ['sumber_id' => $id, 'sumber_tipe' => 'PENERIMAAN']);
});

it('mengeluarkan stok berdasarkan lot paling lama dengan metode FIFO', function () {
    $user = User::query()->first();
    $supplier = Supplier::create(['nama' => 'SUPPLIER FIFO']);
    $barang = Barang::create([
        'kodebarang' => 'BRG-FIFO',
        'namabarang' => 'BARANG FIFO',
        'satuanbesar' => 'DUS',
        'satuankecil' => 'PCS',
        'isisatuan' => 10,
        'hargajual_satuankecil' => 25000,
        'hargajual_satuanbesar' => 250000,
    ]);

    foreach (['2026-07-01', '2026-07-10'] as $tanggal) {
        $id = $this->postJson('/api/v1/gudang/penerimaan/simpan', [
            'tanggal' => $tanggal,
            'supplier_id' => $supplier->id,
            'cara_bayar' => 'CASH',
            'rincian' => [[
                'barang_id' => $barang->id,
                'qtybesar' => 1,
                'isi' => 10,
                'hargabeli' => 100000,
            ]],
        ])->assertOk()->json('data.id');
        $this->postJson("/api/v1/gudang/penerimaan/kirim-stok/{$id}")->assertOk();
    }

    $hasil = app(StokFifoService::class)->keluarkanFifo(
        $barang, 15, 'PENJUALAN', 1, 'PJ-001', $user->id, 'Tes FIFO'
    );

    expect($hasil['alokasi'])->toHaveCount(2);
    $lots = \App\Models\Stok\Stok::orderBy('tanggal_masuk')->get();
    expect((float) $lots[0]->qty_tersedia)->toBe(0.0)
        ->and($lots[0]->status)->toBe('HABIS')
        ->and((float) $lots[1]->qty_tersedia)->toBe(5.0);
    $this->assertDatabaseCount('stok_mutasi', 4);
});

it('menyimpan penjualan, mengurangi stok FIFO, dan menampilkan list beserta detail', function () {
    $supplier = Supplier::create(['nama' => 'SUPPLIER PENJUALAN']);
    $barang = Barang::create([
        'kodebarang' => 'BRG-JUAL',
        'namabarang' => 'BARANG PENJUALAN',
        'satuanbesar' => 'DUS',
        'satuankecil' => 'PCS',
        'isisatuan' => 10,
        'hargajual_satuankecil' => 15000,
        'hargajual_satuanbesar' => 140000,
    ]);

    $penerimaanId = $this->postJson('/api/v1/gudang/penerimaan/simpan', [
        'tanggal' => '2026-07-25',
        'supplier_id' => $supplier->id,
        'cara_bayar' => 'CASH',
        'rincian' => [[
            'barang_id' => $barang->id,
            'qtybesar' => 2,
            'isi' => 10,
            'hargabeli' => 100000,
        ]],
    ])->assertOk()->json('data.id');
    $this->postJson("/api/v1/gudang/penerimaan/kirim-stok/{$penerimaanId}")->assertOk();

    $response = $this->postJson('/api/v1/penjualan/simpan', [
        'cara_bayar' => 'CASH',
        'dibayar' => 50000,
        'items' => [['barang_id' => $barang->id, 'qty' => 3, 'jenis_satuan' => 'KECIL']],
    ])->assertCreated()
        ->assertJsonPath('data.grandtotal', '45000.00')
        ->assertJsonPath('data.kembalian', '5000.00')
        ->assertJsonPath('data.rincian.0.qty', '3.00');

    $id = $response->json('data.id');
    expect($response->json('data.nomortransaksi'))->toStartWith('JUAL-');
    $this->assertDatabaseHas('stok', ['barang_id' => $barang->id, 'qty_tersedia' => 17]);
    $this->assertDatabaseHas('stok_mutasi', [
        'barang_id' => $barang->id,
        'tipe' => 'KELUAR',
        'qty_keluar' => 3,
    ]);

    $this->getJson('/api/v1/penjualan/get-data')
        ->assertOk()
        ->assertJsonPath('data.0.id', $id);
    $this->getJson("/api/v1/penjualan/detail/{$id}")
        ->assertOk()
        ->assertJsonPath('data.rincian.0.barang.id', $barang->id);

    $penjualanBesar = $this->postJson('/api/v1/penjualan/simpan', [
        'cara_bayar' => 'QRIS',
        'dibayar' => 140000,
        'items' => [['barang_id' => $barang->id, 'qty' => 1, 'jenis_satuan' => 'BESAR']],
    ])->assertCreated()
        ->assertJsonPath('data.grandtotal', '140000.00')
        ->assertJsonPath('data.rincian.0.qty', '1.00')
        ->assertJsonPath('data.rincian.0.qty_kecil', '10.00')
        ->assertJsonPath('data.rincian.0.konversi', 10)
        ->assertJsonPath('data.rincian.0.satuan', 'DUS');
    $this->assertDatabaseHas('stok', ['barang_id' => $barang->id, 'qty_tersedia' => 7]);

    $penjualanBesarId = $penjualanBesar->json('data.id');
    $rinciBesarId = $penjualanBesar->json('data.rincian.0.id');
    $this->getJson("/api/v1/penjualan/retur/transaksi/{$penjualanBesarId}")
        ->assertOk()
        ->assertJsonPath('data.rincian.0.qty_bisa_diretur', 1);

    $retur = $this->postJson('/api/v1/penjualan/retur/simpan', [
        'penjualan_id' => $penjualanBesarId,
        'alasan' => 'Kemasan barang rusak',
        'metode_pengembalian' => 'CASH',
        'items' => [['penjualan_rinci_id' => $rinciBesarId, 'qty' => 1]],
    ])->assertCreated()
        ->assertJsonPath('data.total', '140000.00')
        ->assertJsonPath('data.rincian.0.qty_kecil', '10.00');
    $returId = $retur->json('data.id');

    $this->assertDatabaseHas('stok', ['barang_id' => $barang->id, 'qty_tersedia' => 17]);
    $this->assertDatabaseHas('stok_mutasi', [
        'barang_id' => $barang->id,
        'tipe' => 'MASUK',
        'sumber_tipe' => 'RETUR_PENJUALAN',
        'qty_masuk' => 10,
    ]);
    $this->getJson('/api/v1/penjualan/retur/get-data')
        ->assertOk()->assertJsonPath('data.0.id', $returId);
    $this->getJson("/api/v1/penjualan/retur/detail/{$returId}")
        ->assertOk()->assertJsonPath('data.rincian.0.barang.id', $barang->id);
    $this->postJson('/api/v1/penjualan/retur/simpan', [
        'penjualan_id' => $penjualanBesarId,
        'alasan' => 'Retur melebihi sisa',
        'metode_pengembalian' => 'CASH',
        'items' => [['penjualan_rinci_id' => $rinciBesarId, 'qty' => 1]],
    ])->assertUnprocessable();

    \App\Models\Penjualan\Penjualan::whereKey($penjualanBesarId)
        ->update(['tanggal' => now()->subDays(4)]);
    $nomorPenjualanBesar = $penjualanBesar->json('data.nomortransaksi');
    $this->getJson('/api/v1/penjualan/get-data?retur_eligible=1&search='.$nomorPenjualanBesar)
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/penjualan/retur/transaksi/{$penjualanBesarId}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('penjualan');

    $this->deleteJson("/api/v1/penjualan/retur/hapus/{$returId}")->assertOk();
    $this->assertDatabaseMissing('tretur_penjualan', ['id' => $returId]);
    $this->assertDatabaseHas('stok', ['barang_id' => $barang->id, 'qty_tersedia' => 7]);
    $this->assertDatabaseMissing('stok_mutasi', [
        'sumber_tipe' => 'RETUR_PENJUALAN',
        'sumber_id' => $returId,
    ]);
});

it('memblokir penjualan ketika harga perolehan lot FIFO lebih besar dari harga jual', function () {
    $supplier = Supplier::create(['nama' => 'SUPPLIER HARGA RUGI']);
    $barang = Barang::create([
        'kodebarang' => 'BRG-RUGI',
        'namabarang' => 'BARANG HARGA RUGI',
        'satuanbesar' => 'DUS',
        'satuankecil' => 'PCS',
        'isisatuan' => 10,
        'hargajual_satuankecil' => 25000,
        'hargajual_satuanbesar' => 250000,
    ]);

    $penerimaanId = $this->postJson('/api/v1/gudang/penerimaan/simpan', [
        'tanggal' => '2026-07-25',
        'supplier_id' => $supplier->id,
        'cara_bayar' => 'CASH',
        'rincian' => [[
            'barang_id' => $barang->id,
            'qtybesar' => 1,
            'isi' => 10,
            'hargabeli' => 200000,
        ]],
    ])->assertOk()->json('data.id');
    $this->postJson("/api/v1/gudang/penerimaan/kirim-stok/{$penerimaanId}")->assertOk();
    $barang->update(['hargajual_satuankecil' => 15000]);

    $this->postJson('/api/v1/penjualan/simpan', [
        'cara_bayar' => 'CASH',
        'dibayar' => 15000,
        'items' => [['barang_id' => $barang->id, 'qty' => 1, 'jenis_satuan' => 'KECIL']],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('harga_jual');

    $this->assertDatabaseMissing('tpenjualan', ['cara_bayar' => 'CASH']);
    $this->assertDatabaseHas('stok', [
        'barang_id' => $barang->id,
        'qty_tersedia' => 10,
        'qty_keluar' => 0,
    ]);
    $this->assertDatabaseMissing('stok_mutasi', [
        'barang_id' => $barang->id,
        'tipe' => 'KELUAR',
    ]);
});

it('meretur pembelian hanya dari penerimaan yang sudah masuk stok dan tidak melebihi saldo lot', function () {
    $supplier = Supplier::create(['nama' => 'SUPPLIER RETUR BELI']);
    $barang = Barang::create([
        'kodebarang' => 'BRG-RET-BELI',
        'namabarang' => 'BARANG RETUR PEMBELIAN',
        'satuanbesar' => 'DUS',
        'satuankecil' => 'PCS',
        'isisatuan' => 10,
        'hargajual_satuankecil' => 15000,
        'hargajual_satuanbesar' => 140000,
    ]);
    $penerimaan = $this->postJson('/api/v1/gudang/penerimaan/simpan', [
        'tanggal' => now()->toDateString(),
        'supplier_id' => $supplier->id,
        'cara_bayar' => 'CASH',
        'rincian' => [[
            'barang_id' => $barang->id,
            'qtybesar' => 2,
            'isi' => 10,
            'hargabeli' => 100000,
        ]],
    ])->assertOk();
    $penerimaanId = $penerimaan->json('data.id');
    $rinciId = $penerimaan->json('data.rincian.0.id');
    $this->postJson("/api/v1/gudang/penerimaan/kirim-stok/{$penerimaanId}")->assertOk();

    $this->postJson('/api/v1/penjualan/simpan', [
        'cara_bayar' => 'CASH',
        'dibayar' => 75000,
        'items' => [['barang_id' => $barang->id, 'qty' => 5, 'jenis_satuan' => 'KECIL']],
    ])->assertCreated();
    $this->assertDatabaseHas('stok', ['barang_id' => $barang->id, 'qty_tersedia' => 15]);

    $this->getJson('/api/v1/gudang/retur-pembelian/penerimaan')
        ->assertOk()->assertJsonPath('data.0.id', $penerimaanId);
    $this->getJson("/api/v1/gudang/retur-pembelian/penerimaan/{$penerimaanId}")
        ->assertOk()
        ->assertJsonPath('data.rincian.0.qtykecil', '20.00')
        ->assertJsonPath('data.rincian.0.stok_tersedia_kecil', 15)
        ->assertJsonPath('data.rincian.0.stok_tersedia_besar', 1)
        ->assertJsonPath('data.rincian.0.stok_sisa_kecil', 5)
        ->assertJsonPath('data.rincian.0.maksimal_retur_kecil', 15)
        ->assertJsonPath('data.rincian.0.maksimal_retur_besar', 1);

    $retur = $this->postJson('/api/v1/gudang/retur-pembelian/simpan', [
        'penerimaan_id' => $penerimaanId,
        'alasan' => 'Barang dikembalikan ke supplier',
        'items' => [[
            'penerimaan_rinci_id' => $rinciId,
            'jenis_satuan' => 'BESAR',
            'qty' => 1,
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.rincian.0.qty_kecil', '10.00')
        ->assertJsonPath('data.rincian.0.satuan', 'DUS')
        ->assertJsonPath('data.total', '100000.00');
    $returId = $retur->json('data.id');

    $this->assertDatabaseHas('stok', ['barang_id' => $barang->id, 'qty_tersedia' => 5]);
    $this->assertDatabaseHas('stok_mutasi', [
        'barang_id' => $barang->id,
        'tipe' => 'KELUAR',
        'sumber_tipe' => 'RETUR_PEMBELIAN',
        'qty_keluar' => 10,
    ]);
    $this->getJson('/api/v1/gudang/stok/get-data?search=BRG-RET-BELI')
        ->assertOk()
        ->assertJsonPath('data.0.qty_penerimaan', 20)
        ->assertJsonPath('data.0.qty_penjualan', 5)
        ->assertJsonPath('data.0.qty_retur_penjualan', null)
        ->assertJsonPath('data.0.qty_retur_pembelian', 10)
        ->assertJsonPath('ringkasan.total_penerimaan', 20)
        ->assertJsonPath('ringkasan.total_penjualan', 5)
        ->assertJsonPath('ringkasan.total_retur_penjualan', 0)
        ->assertJsonPath('ringkasan.total_retur_pembelian', 10);
    $this->getJson('/api/v1/gudang/retur-pembelian/get-data')
        ->assertOk()->assertJsonPath('data.0.id', $returId);
    $this->getJson("/api/v1/gudang/retur-pembelian/detail/{$returId}")
        ->assertOk()->assertJsonPath('data.rincian.0.barang.id', $barang->id);

    $this->postJson('/api/v1/gudang/retur-pembelian/simpan', [
        'penerimaan_id' => $penerimaanId,
        'alasan' => 'Jumlah melebihi saldo lot',
        'items' => [[
            'penerimaan_rinci_id' => $rinciId,
            'jenis_satuan' => 'BESAR',
            'qty' => 1,
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('qty');

    $this->deleteJson("/api/v1/gudang/retur-pembelian/hapus/{$returId}")->assertOk();
    $this->assertDatabaseMissing('tretur_pembelian', ['id' => $returId]);
    $this->assertDatabaseHas('stok', ['barang_id' => $barang->id, 'qty_tersedia' => 15]);
    $this->assertDatabaseMissing('stok_mutasi', [
        'sumber_tipe' => 'RETUR_PEMBELIAN',
        'sumber_id' => $returId,
    ]);
});
