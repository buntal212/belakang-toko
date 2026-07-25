<?php

namespace App\Models\Penjualan;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ReturPenjualan extends Model
{
    protected $table = 'tretur_penjualan';
    protected $guarded = ['id'];
    protected $casts = [
        'tanggal' => 'datetime',
        'jumlahitem' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function penjualan() { return $this->belongsTo(Penjualan::class); }
    public function rincian() { return $this->hasMany(ReturPenjualanRinci::class); }
    public function pengguna() { return $this->belongsTo(User::class, 'created_by'); }
}
