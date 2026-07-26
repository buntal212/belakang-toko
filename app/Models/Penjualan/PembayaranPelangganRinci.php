<?php

namespace App\Models\Penjualan;

use Illuminate\Database\Eloquent\Model;

class PembayaranPelangganRinci extends Model
{
    protected $table = 'tpembayaran_pelanggan_rinci';
    protected $guarded = ['id'];
    protected $casts = ['nominal' => 'decimal:2'];

    public function pembayaran() { return $this->belongsTo(PembayaranPelanggan::class, 'pembayaran_pelanggan_id'); }
    public function penjualan() { return $this->belongsTo(Penjualan::class); }
}
