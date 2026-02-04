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
        Schema::create('presidi_riutilizzo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presidio_id')->constrained('presidi')->onDelete('cascade');
            $table->foreignId('cliente_id')->nullable()->constrained('clienti')->nullOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedi')->nullOnDelete();
            $table->date('data_riutilizzo')->nullable();
            $table->boolean('is_rottamato')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presidi_riutilizzo');
    }
};
