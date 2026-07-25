<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    use HasFactory;

    protected $table = 'mbarang';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'kodebarang' => 'string',
        'namabarang' => 'string',
        'jenisbarang' => 'string',
        'keterangan' => 'string',
        'merk' => 'string',
        'satuanbesar' => 'string',
        'satuankecil' => 'string',
        'isisatuan' => 'integer',
        'limitstok' => 'decimal:2',
        'hargajual_satuankecil' => 'decimal:2',
        'hargajual_satuanbesar' => 'decimal:2',
        'flaging' => 'integer',
    ];
}
