<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merk extends Model
{
    use HasFactory;

    protected $table = 'mmerk';

    protected $fillable = [
        'merk',
        'flaging'
    ];

    protected $casts = [
        'merk' => 'string',
        'flaging' => 'integer',
    ];
}