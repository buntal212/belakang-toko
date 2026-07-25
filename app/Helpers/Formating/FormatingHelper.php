<?php

namespace App\Helpers\Formating;


class FormatingHelper
{

    public static function genKodeMaster($n, $kode)
    {
        $hasil = str_pad($n, 6, '0', STR_PAD_LEFT);
        return $kode . $hasil;
    }

    public static function notrans($n, $kode, $semester, $entitas)
    {
        $hasil = str_pad($n, 6, '0', STR_PAD_LEFT);
        return $hasil . '/' . $kode . '-' . $entitas . '/' . $semester . '/' .  date("Y");
    }

    public static function pergeserankas($n, $entitas)
    {
        $semester = 01;
        $hasil = str_pad($n, 6, '0', STR_PAD_LEFT);
        return $hasil . '/PK-' . $entitas . '/' . $semester . '/' .  date("Y");
    }

    public static function tagihan($n, $entitas)
    {
        $semester = 01;
        $hasil = str_pad($n, 6, '0', STR_PAD_LEFT);
        return $hasil . '/TG-' . $entitas . '/' . $semester . '/' .  date("Y");
    }

    public static function pembayaran($n, $entitas)
    {
        $semester = 01;
        $hasil = str_pad($n, 6, '0', STR_PAD_LEFT);
        return $hasil . '/PB-' . $entitas . '/' . $semester . '/' .  date("Y");
    }

    public static function nogu($n, $entitas)
    {
        $semester = 01;
        $hasil = str_pad($n, 6, '0', STR_PAD_LEFT);
        return $hasil . '/GU-' . $entitas . '/' . $semester . '/' .  date("Y");
    }
}
