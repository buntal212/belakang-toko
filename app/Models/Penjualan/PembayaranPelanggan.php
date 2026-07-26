<?php

namespace App\Models\Penjualan;

use App\Models\Master\Pelanggan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PembayaranPelanggan extends Model
{
    protected $table = 'tpembayaran_pelanggan';
    protected $guarded = ['id'];
    protected $casts = ['tanggal' => 'datetime', 'nominal' => 'decimal:2'];

    public function pelanggan() { return $this->belongsTo(Pelanggan::class); }
    public function pembuat() { return $this->belongsTo(User::class, 'created_by'); }
    public function rincian() { return $this->hasMany(PembayaranPelangganRinci::class); }
    public function setoranBendahara() { return $this->belongsTo(SetoranBendahara::class); }
}
