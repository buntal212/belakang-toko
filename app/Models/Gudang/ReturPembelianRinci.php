<?php

namespace App\Models\Gudang;

use App\Models\Master\Barang;
use App\Models\Stok\Stok;
use Illuminate\Database\Eloquent\Model;

class ReturPembelianRinci extends Model
{
    protected $table = 'tretur_pembelian_rinci';
    protected $guarded = ['id'];
    protected $casts = [
        'qty' => 'decimal:2',
        'qty_kecil' => 'decimal:2',
        'konversi' => 'integer',
        'harga_perolehan' => 'decimal:4',
        'subtotal' => 'decimal:2',
    ];

    public function retur() { return $this->belongsTo(ReturPembelian::class, 'retur_pembelian_id'); }
    public function barang() { return $this->belongsTo(Barang::class); }
    public function stok() { return $this->belongsTo(Stok::class); }
}
