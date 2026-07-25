<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tpenjualan_rinci', function (Blueprint $table) {
            $table->decimal('qty_kecil', 18, 2)->after('qty');
            $table->unsignedInteger('konversi')->default(1)->after('qty_kecil');
        });
    }

    public function down(): void
    {
        Schema::table('tpenjualan_rinci', function (Blueprint $table) {
            $table->dropColumn(['qty_kecil', 'konversi']);
        });
    }
};
