<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipi_estintori', function (Blueprint $table) {
            if (!Schema::hasColumn('tipi_estintori', 'codice_articolo_fatturazione')) {
                $table->string('codice_articolo_fatturazione', 50)->nullable()->after('sigla');
                $table->index('codice_articolo_fatturazione', 'idx_tipi_est_codice_art_fatt');
            }
        });

        Schema::table('tipi_presidio', function (Blueprint $table) {
            if (!Schema::hasColumn('tipi_presidio', 'codice_articolo_fatturazione')) {
                $table->string('codice_articolo_fatturazione', 50)->nullable()->after('nome');
                $table->index('codice_articolo_fatturazione', 'idx_tipi_presidio_codice_art_fatt');
            }
        });

        // Backfill per compatibilita': usa SIGLA per estintori e NOME per altri presidi
        DB::table('tipi_estintori')
            ->whereNull('codice_articolo_fatturazione')
            ->update([
                'codice_articolo_fatturazione' => DB::raw('NULLIF(sigla, \'\')'),
            ]);

        DB::table('tipi_presidio')
            ->whereNull('codice_articolo_fatturazione')
            ->update([
                'codice_articolo_fatturazione' => DB::raw('NULLIF(nome, \'\')'),
            ]);
    }

    public function down(): void
    {
        Schema::table('tipi_estintori', function (Blueprint $table) {
            if (Schema::hasColumn('tipi_estintori', 'codice_articolo_fatturazione')) {
                $table->dropIndex('idx_tipi_est_codice_art_fatt');
                $table->dropColumn('codice_articolo_fatturazione');
            }
        });

        Schema::table('tipi_presidio', function (Blueprint $table) {
            if (Schema::hasColumn('tipi_presidio', 'codice_articolo_fatturazione')) {
                $table->dropIndex('idx_tipi_presidio_codice_art_fatt');
                $table->dropColumn('codice_articolo_fatturazione');
            }
        });
    }
};
