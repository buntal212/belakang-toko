<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tpenerimaan', 'cara_bayar')) {
            Schema::table('tpenerimaan', function (Blueprint $table) {
                $table->string('cara_bayar', 10)->default('CASH')->after('supplier_id');
            });
        }
        if (!Schema::hasColumn('tpenerimaan', 'flaging')) {
            Schema::table('tpenerimaan', function (Blueprint $table) {
                $table->tinyInteger('flaging')->default(0)->after('status')->index();
            });
        }

        DB::table('tpenerimaan')
            ->whereNotNull('stok_terkirim_at')
            ->update(['flaging' => 1, 'status' => 'Terkunci']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('tpenerimaan', 'flaging')) {
            Schema::table('tpenerimaan', fn (Blueprint $table) => $table->dropColumn('flaging'));
        }
        if (Schema::hasColumn('tpenerimaan', 'cara_bayar')) {
            Schema::table('tpenerimaan', fn (Blueprint $table) => $table->dropColumn('cara_bayar'));
        }
    }
};
