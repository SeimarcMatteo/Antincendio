<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilizzi_giacenze', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervento_id')->constrained('interventi')->onDelete('cascade');
            $table->foreignId('presidio_intervento_id')->constrained('presidi_intervento')->onDelete('cascade');
            $table->string('categoria');
            $table->foreignId('tipo_estintore_id')->constrained('tipi_estintori')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilizzi_giacenze');
    }
};
