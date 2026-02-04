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
        Schema::create('anomalie', function (Blueprint $table) {
            $table->id();
            $table->string('categoria'); // es. Estintore, Idrante, Porta
            $table->string('etichetta'); // testo dell’anomalia da mostrare
            $table->boolean('attiva')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalie');
    }
};
