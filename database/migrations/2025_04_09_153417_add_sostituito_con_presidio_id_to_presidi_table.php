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
        Schema::table('presidi', function (Blueprint $table) {
            $table->foreignId('sostituito_con_presidio_id')
                  ->nullable()  // Il campo può essere nullo, perché non tutti i presidi saranno sostituiti
                  ->constrained('presidi')  // Definisce il vincolo con la stessa tabella
                  ->cascadeOnDelete();  // Se il presidio sostituito viene eliminato, elimina anche il campo associato
    
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presidi', function (Blueprint $table) {
            $table->dropForeign(['sostituito_con_presidio_id']);  // Rimuove il vincolo della foreign key
            $table->dropColumn('sostituito_con_presidio_id');  // Rimuove il campo
        });
    }
};
