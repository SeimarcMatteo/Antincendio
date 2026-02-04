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
        Schema::create('presidi_ritirati', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presidio_id')->constrained('presidi')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clienti');
            $table->foreignId('sede_id')->nullable()->constrained('sedi');
            $table->date('data_ritiro')->nullable();
            $table->string('note')->nullable();
            $table->enum('stato', ['disponibile', 'assegnato', 'rottamato'])->default('disponibile');
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presidi_ritirati');
    }
};
