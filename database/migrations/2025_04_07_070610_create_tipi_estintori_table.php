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
        Schema::create('tipi_estintori', function (Blueprint $table) {
            $table->id();
            $table->string('sigla'); // Es: CO2, POLVERE, SCHIUMA
            $table->string('descrizione')->nullable(); // es: Estintore a CO2 5kg
            $table->integer('kg')->nullable();
            $table->enum('tipo', ['CO2', 'Polvere', 'Schiuma', 'Altro']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipi_estintori');
    }
};
