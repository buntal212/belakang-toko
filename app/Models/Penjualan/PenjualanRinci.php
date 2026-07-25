<?php

namespace App\Models\Penjualan;

use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Model;

class PenjualanRinci extends Model
{
    protected $table = 'tpenjualan_rinci';
    protected $guarded = ['id'];
    protected $casts = [
        'qty' => 'decimal:2',
        'qty_kecil' => 'decimal:2',
        'konversi' => 'integer',
        'harga' => 'decimal:2',
        'diskon' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'hpp' => 'decimal:2',
        'alokasi_fifo' => 'array',
    ];

    public function penjualan() { return $this->belongsTo(Penjualan::class); }
    public function barang() { return $this->belongsTo(Barang::class); }
}
