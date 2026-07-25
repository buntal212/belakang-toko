<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class MenusHed extends Model
{
    protected $table = "admin_menus";
    protected $guarded = ['id'];

    public function rincian()
    {
        return $this->hasMany(MenusRinci::class,'menu_id','id');
    }
}
