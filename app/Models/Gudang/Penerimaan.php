<?php

namespace App\Models\Gudang;

use App\Models\Master\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Penerimaan extends Model
{
    protected $table = 'tpenerimaan';
    protected $guarded = ['id'];
    protected $casts = [
        'tanggal' => 'date:Y-m-d', 'tglfaktur' => 'date:Y-m-d',
        'jumlahitem' => 'integer', 'subtotal' => 'decimal:2',
        'diskon' => 'decimal:2', 'pajak' => 'decimal:2', 'grandtotal' => 'decimal:2',
        'dibayar' => 'decimal:2', 'sisa_hutang' => 'decimal:2',
        'stok_terkirim_at' => 'datetime',
        'flaging' => 'integer',
    ];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function rincian() { return $this->hasMany(PenerimaanRinci::class); }
    public function pembuat() { return $this->belongsTo(User::class, 'created_by'); }
    public function stok() { return $this->hasMany(\App\Models\Stok\Stok::class); }
}
