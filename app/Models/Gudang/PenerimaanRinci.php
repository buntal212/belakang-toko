<?php

namespace App\Models\Gudang;

use App\Models\Master\Barang;
use Illuminate\Database\Eloquent\Model;

class PenerimaanRinci extends Model
{
    protected $table = 'tpenerimaan_rinci';
    protected $guarded = ['id'];
    protected $casts = [
        'qtybesar' => 'decimal:2', 'isi' => 'integer', 'qtykecil' => 'decimal:2',
        'hargabeli' => 'decimal:2', 'hargakecil' => 'decimal:2',
        'diskonpersen' => 'decimal:2', 'diskonnominal' => 'decimal:2',
        'subtotal' => 'decimal:2', 'total' => 'decimal:2',
    ];

    public function penerimaan() { return $this->belongsTo(Penerimaan::class); }
    public function barang() { return $this->belongsTo(Barang::class); }
    public function stok() { return $this->hasOne(\App\Models\Stok\Stok::class, 'penerimaan_rinci_id'); }
}
