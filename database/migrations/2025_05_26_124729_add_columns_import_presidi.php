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
        Schema::table('import_presidi', function (Blueprint $table) {
            // Estendere uso di campi esistenti
            $table->string('tipo_estintore')->nullable()->change(); // ora usato anche per idranti e porte
            $table->text('note')->nullable()->change();             // note malfunzionamenti idranti/porte
            // Nessun campo nuovo necessario per ora: anomalie, note, revisione/collaudo riutilizzati
        });
    }

    public function down(): void
    {
        Schema::table('import_presidi', function (Blueprint $table) {
            // Nessuna azione di rollback specifica necessaria
        });
    }
};
