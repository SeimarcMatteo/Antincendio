<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipi_estintori', function (Blueprint $table) {
            if (!Schema::hasColumn('tipi_estintori', 'codice_articolo_fatturazione_full')) {
                $table->string('codice_articolo_fatturazione_full', 50)
                    ->nullable()
                    ->after('codice_articolo_fatturazione');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tipi_estintori', function (Blueprint $table) {
            if (Schema::hasColumn('tipi_estintori', 'codice_articolo_fatturazione_full')) {
                $table->dropColumn('codice_articolo_fatturazione_full');
            }
        });
    }
};
