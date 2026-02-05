<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedi', function (Blueprint $table) {
            $table->unsignedSmallInteger('minuti_intervento_mese1')->nullable()->after('minuti_intervento');
            $table->unsignedSmallInteger('minuti_intervento_mese2')->nullable()->after('minuti_intervento_mese1');
        });

        // Backfill: copia minuti_intervento
        DB::table('sedi')->update([
            'minuti_intervento_mese1' => DB::raw('COALESCE(minuti_intervento, 0)'),
            'minuti_intervento_mese2' => DB::raw('COALESCE(minuti_intervento, 0)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sedi', function (Blueprint $table) {
            $table->dropColumn(['minuti_intervento_mese1', 'minuti_intervento_mese2']);
        });
    }
};
