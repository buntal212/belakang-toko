<?php

namespace App\Models\Stok;

use App\Models\Gudang\Penerimaan;
use App\Models\Gudang\PenerimaanRinci;
use App\Models\Master\Barang;
use App\Models\Master\Supplier;
use Illuminate\Database\Eloquent\Model;

class Stok extends Model
{
    protected $table = 'stok';
    protected $guarded = ['id'];
    protected $casts = [
        'tanggal_masuk' => 'date:Y-m-d',
        'tanggal_kadaluarsa' => 'date:Y-m-d',
        'qty_masuk' => 'decimal:2',
        'qty_keluar' => 'decimal:2',
        'qty_tersedia' => 'decimal:2',
        'harga_per_unit' => 'decimal:4',
        'nilai_awal' => 'decimal:2',
        'nilai_tersedia' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function barang() { return $this->belongsTo(Barang::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function penerimaan() { return $this->belongsTo(Penerimaan::class); }
    public function rincianPenerimaan() { return $this->belongsTo(PenerimaanRinci::class, 'penerimaan_rinci_id'); }
    public function mutasi() { return $this->hasMany(StokMutasi::class); }
}
