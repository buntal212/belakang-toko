<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mbarang', function (Blueprint $table) {
            $table->id();
            $table->string('kodebarang', 255)->unique();
            $table->string('kodebarcode', 255)->nullable();
            $table->string('namabarang', 255);
            $table->string('merk', 255)->nullable();
            $table->string('satuanbesar', 255)->nullable();
            $table->string('satuankecil', 255)->nullable();
            $table->integer('isisatuan')->default(1);
            $table->decimal('limitstok', 18, 2)->default(0);
            $table->decimal('hargajual_satuankecil', 18, 2)->default(0);
            $table->decimal('hargajual_satuanbesar', 18, 2)->default(0);
            $table->tinyInteger('flaging')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mbarang');
    }
};
