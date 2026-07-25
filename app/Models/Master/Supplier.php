<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'msupplier';

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'nama' => 'string',
        'alamat' => 'string',
        'telepon' => 'string',
        'rekening' => 'string',
    ];
}