<?php

namespace App\Models\Stok;

use App\Models\Master\Barang;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StokMutasi extends Model
{
    protected $table = 'stok_mutasi';
    protected $guarded = ['id'];
    protected $casts = [
        'tanggal_mutasi' => 'datetime',
        'qty_masuk' => 'decimal:2',
        'qty_keluar' => 'decimal:2',
        'saldo_sebelum' => 'decimal:2',
        'saldo_sesudah' => 'decimal:2',
        'harga_per_unit' => 'decimal:4',
        'nilai_mutasi' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function stok() { return $this->belongsTo(Stok::class); }
    public function barang() { return $this->belongsTo(Barang::class); }
    public function pengguna() { return $this->belongsTo(User::class, 'created_by'); }
}
