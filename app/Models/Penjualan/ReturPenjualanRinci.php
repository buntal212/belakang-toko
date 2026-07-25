<?php

namespace App\Models\Penjualan;

use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Model;

class ReturPenjualanRinci extends Model
{
    protected $table = 'tretur_penjualan_rinci';
    protected $guarded = ['id'];
    protected $casts = [
        'qty' => 'decimal:2',
        'qty_kecil' => 'decimal:2',
        'konversi' => 'integer',
        'harga' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'alokasi_retur' => 'array',
    ];

    public function retur() { return $this->belongsTo(ReturPenjualan::class, 'retur_penjualan_id'); }
    public function penjualanRinci() { return $this->belongsTo(PenjualanRinci::class); }
    public function barang() { return $this->belongsTo(Barang::class); }
}
