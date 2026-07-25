<?php

namespace App\Models\Gudang;

use App\Models\Master\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ReturPembelian extends Model
{
    protected $table = 'tretur_pembelian';
    protected $guarded = ['id'];
    protected $casts = ['tanggal' => 'datetime', 'jumlahitem' => 'decimal:2', 'total' => 'decimal:2'];

    public function penerimaan() { return $this->belongsTo(Penerimaan::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function rincian() { return $this->hasMany(ReturPembelianRinci::class); }
    public function pengguna() { return $this->belongsTo(User::class, 'created_by'); }
}
