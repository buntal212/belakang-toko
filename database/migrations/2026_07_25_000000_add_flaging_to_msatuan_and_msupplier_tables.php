<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('msatuan', 'flaging')) {
            Schema::table('msatuan', function (Blueprint $table) {
                $table->tinyInteger('flaging')->default(0)->after('satuan');
            });
        }

        if (!Schema::hasColumn('msupplier', 'flaging')) {
            Schema::table('msupplier', function (Blueprint $table) {
                $table->tinyInteger('flaging')->default(0)->after('rekening');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('msatuan', 'flaging')) {
            Schema::table('msatuan', fn (Blueprint $table) => $table->dropColumn('flaging'));
        }
        if (Schema::hasColumn('msupplier', 'flaging')) {
            Schema::table('msupplier', fn (Blueprint $table) => $table->dropColumn('flaging'));
        }
    }
};
