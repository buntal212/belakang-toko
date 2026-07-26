<?php

namespace App\Models\Penjualan;

use App\Models\Master\Pelanggan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Penjualan extends Model
{
    protected $table = 'tpenjualan';
    protected $guarded = ['id'];
    protected $casts = [
        'tanggal' => 'datetime',
        'jumlahitem' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'diskon' => 'decimal:2',
        'grandtotal' => 'decimal:2',
        'dibayar' => 'decimal:2',
        'kembalian' => 'decimal:2',
        'sisa_hutang' => 'decimal:2',
        'hpp' => 'decimal:2',
    ];

    public function rincian() { return $this->hasMany(PenjualanRinci::class); }
    public function pengguna() { return $this->belongsTo(User::class, 'created_by'); }
    public function pelanggan() { return $this->belongsTo(Pelanggan::class); }
    public function setoranBendahara() { return $this->belongsTo(SetoranBendahara::class); }
    public function retur() { return $this->hasMany(ReturPenjualan::class); }
}
