<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('interventi', function (Blueprint $table) {
            $table->id();
    
            $table->foreignId('cliente_id')->constrained('clienti')->onDelete('cascade');
            $table->foreignId('sede_id')->nullable()->constrained('sedi')->onDelete('set null');
    
            $table->date('data_intervento');
            $table->unsignedInteger('durata_minuti');
            $table->enum('stato', ['Pianificato', 'Completato'])->default('Pianificato');
            $table->string('zona')->nullable();
    
            $table->timestamps();
        });
    
        Schema::create('intervento_tecnico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervento_id')->constrained('interventi')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
    
            $table->unique(['intervento_id', 'user_id']);
        });
    }
    
};
