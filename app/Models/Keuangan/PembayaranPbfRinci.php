<?php

namespace App\Models\Keuangan;

use App\Models\Gudang\Penerimaan;
use Illuminate\Database\Eloquent\Model;

class PembayaranPbfRinci extends Model
{
    protected $table = 'tpembayaran_pbf_rinci';
    protected $guarded = ['id'];
    protected $casts = ['nominal' => 'decimal:2'];
    public function pembayaran() { return $this->belongsTo(PembayaranPbf::class, 'pembayaran_pbf_id'); }
    public function penerimaan() { return $this->belongsTo(Penerimaan::class); }
}
