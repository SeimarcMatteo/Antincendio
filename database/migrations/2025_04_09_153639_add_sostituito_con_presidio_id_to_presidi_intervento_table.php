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
        Schema::table('presidi_intervento', function (Blueprint $table) {
            // Aggiungi il campo sostituito_con_presidio_id
            $table->foreignId('sostituito_con_presidio_id')
                ->nullable()  // Il campo può essere nullo se non ci sono sostituzioni
                ->constrained('presidi')  // Relazione con la tabella presidi
                ->cascadeOnDelete();  // Se il presidio sostituito viene eliminato, elimina anche il campo associato
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presidi_intervento', function (Blueprint $table) {
            // Rimuovi il campo sostituito_con_presidio_id
            $table->dropForeign(['sostituito_con_presidio_id']);  // Rimuove il vincolo di chiave esterna
            $table->dropColumn('sostituito_con_presidio_id');  // Rimuove il campo
        });
    }
};
