<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mbarang', 'keterangan')) {
            Schema::table('mbarang', function (Blueprint $table) {
                $table->string('keterangan', 255)->nullable()->after('jenisbarang');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mbarang', 'keterangan')) {
            Schema::table('mbarang', fn (Blueprint $table) => $table->dropColumn('keterangan'));
        }
    }
};
