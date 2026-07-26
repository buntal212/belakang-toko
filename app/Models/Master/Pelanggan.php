<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pelanggan extends Model
{
    use HasFactory;

    protected $table = 'mpelanggan';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'nama' => 'string',
        'alamat' => 'string',
        'telepon' => 'string',
        'email' => 'string',
        'flaging' => 'integer',
    ];
}
