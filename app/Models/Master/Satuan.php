<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Satuan extends Model
{
    use HasFactory;

    protected $table = 'msatuan';

    protected $fillable = [
        'satuan',
        'flaging',
    ];

    protected $casts = [
        'satuan' => 'string',
        'flaging' => 'integer',
    ];
}
