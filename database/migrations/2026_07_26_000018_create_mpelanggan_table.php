<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpelanggan', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150)->index();
            $table->text('alamat')->nullable();
            $table->string('telepon', 30)->nullable()->index();
            $table->string('email', 150)->nullable()->index();
            $table->tinyInteger('flaging')->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpelanggan');
    }
};
