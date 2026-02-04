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
        Schema::table('clienti', function (Blueprint $table) {
            $table->unsignedSmallInteger('minuti_intervento_mese1')->nullable()->after('minuti_intervento');
            $table->unsignedSmallInteger('minuti_intervento_mese2')->nullable()->after('minuti_intervento_mese1');
        });

        // Backfill: copia l’attuale minuti_intervento su entrambi
        DB::table('clienti')->update([
            'minuti_intervento_mese1' => DB::raw('COALESCE(minuti_intervento, 0)'),
            'minuti_intervento_mese2' => DB::raw('COALESCE(minuti_intervento, 0)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            $table->dropColumn(['minuti_intervento_mese1', 'minuti_intervento_mese2']);
        });
    }
};
