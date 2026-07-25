<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mbarang', 'jenisbarang')) {
            Schema::table('mbarang', function (Blueprint $table) {
                $table->string('jenisbarang', 100)->nullable()->after('namabarang');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mbarang', 'jenisbarang')) {
            Schema::table('mbarang', fn (Blueprint $table) => $table->dropColumn('jenisbarang'));
        }
    }
};
