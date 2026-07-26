<?php

namespace App\Models\Penjualan;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SetoranBendahara extends Model
{
    protected $table = 'tsetoran_bendahara';
    protected $guarded = ['id'];

    protected $casts = [
        'tanggal' => 'datetime',
        'periode_mulai' => 'datetime',
        'periode_sampai' => 'datetime',
        'penjualan_tunai' => 'decimal:2',
        'retur_tunai' => 'decimal:2',
        'pembayaran_pelanggan' => 'decimal:2',
        'seharusnya_disetor' => 'decimal:2',
        'nominal_disetor' => 'decimal:2',
        'selisih' => 'decimal:2',
    ];

    public function kasir()
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function penjualan()
    {
        return $this->hasMany(Penjualan::class, 'setoran_bendahara_id');
    }

    public function pembayaranPelangganItems()
    {
        return $this->hasMany(PembayaranPelanggan::class, 'setoran_bendahara_id');
    }
}
