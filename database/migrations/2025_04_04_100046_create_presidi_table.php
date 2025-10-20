<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('presidi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clienti')->onDelete('cascade');
            $table->foreignId('sede_id')->constrained('sedi')->onDelete('cascade');
            $table->enum('categoria', ['Estintore', 'Idrante', 'Porta']);
            $table->unsignedInteger('progressivo'); // progressivo per cliente/sede/categoria
            $table->string('ubicazione')->nullable();
            $table->string('tipo_contratto')->nullable();
            $table->string('tipo_estintore')->nullable(); // es. "6 kg polvere"
            $table->date('data_manutenzione_annuale')->nullable();
            $table->date('data_revisione')->nullable();
            $table->date('data_collaudo')->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'sede_id', 'categoria', 'progressivo'], 'presidi_unique_progressivo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presidi');
    }
};
