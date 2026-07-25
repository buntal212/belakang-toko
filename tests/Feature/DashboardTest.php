<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('mewajibkan autentikasi untuk dashboard', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});

it('menampilkan seluruh bagian ringkasan dashboard', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'generated_at',
            'ringkasan' => [
                'penjualan_bersih',
                'jumlah_transaksi',
                'laba_kotor',
                'nilai_stok',
                'stok_menipis',
            ],
            'tren_penjualan',
            'produk_terlaris',
            'stok_menipis',
            'cara_bayar',
            'aktivitas_terbaru',
        ])
        ->assertJsonCount(7, 'tren_penjualan');
});
