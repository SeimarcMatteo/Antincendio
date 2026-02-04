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
        Schema::create('giacenze_presidi', function (Blueprint $table) {
            $table->id();
            $table->string('categoria'); // Es: Estintore, Idrante, ecc.
            $table->foreignId('tipo_estintore_id')->constrained('tipi_estintori')->onDelete('cascade');
            $table->unsignedInteger('quantita')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('giacenze_presidi');
    }
};
