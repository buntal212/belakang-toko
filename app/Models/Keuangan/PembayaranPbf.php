<?php

namespace App\Models\Keuangan;

use App\Models\Master\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PembayaranPbf extends Model
{
    protected $table = 'tpembayaran_pbf';
    protected $guarded = ['id'];
    protected $casts = ['tanggal' => 'datetime', 'nominal' => 'decimal:2'];
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function pembuat() { return $this->belongsTo(User::class, 'created_by'); }
    public function rincian() { return $this->hasMany(PembayaranPbfRinci::class); }
}
