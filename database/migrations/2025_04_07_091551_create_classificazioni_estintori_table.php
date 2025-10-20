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
        Schema::create('classificazioni_estintori', function (Blueprint $table) {
            $table->id();
            $table->string('nome'); // es: Estintori a polvere
            $table->integer('anni_revisione_dopo'); // es: 5
            $table->integer('anni_revisione_prima'); // es: 3
            $table->integer('anni_collaudo')->nullable(); // solo per CO2
            $table->integer('anni_fine_vita'); // es: 10 o 18
            $table->timestamps();
        });

        Schema::table('tipi_estintori', function (Blueprint $table) {
            $table->foreignId('classificazione_id')->nullable()->constrained('classificazioni_estintori')->nullOnDelete();
        });

        Schema::table('presidi', function (Blueprint $table) {
            $table->boolean('flag_anomalia1')->default(false);
            $table->boolean('flag_anomalia2')->default(false);
            $table->boolean('flag_anomalia3')->default(false);
            $table->text('note')->nullable();

            $table->date('data_serbatoio')->nullable();
            $table->date('data_revisione')->nullable();
            $table->date('data_collaudo')->nullable();
            $table->date('data_fine_vita')->nullable();
            $table->date('data_sostituzione')->nullable();

            $table->boolean('flag_preventivo')->default(false);

        });
        Schema::table('presidi_storico', function (Blueprint $table) {
            $table->boolean('flag_anomalia1')->default(false);
            $table->boolean('flag_anomalia2')->default(false);
            $table->boolean('flag_anomalia3')->default(false);
        $table->text('note')->nullable();

            $table->date('data_serbatoio')->nullable();
            $table->date('data_revisione')->nullable();
            $table->date('data_collaudo')->nullable();
            $table->date('data_fine_vita')->nullable();
            $table->date('data_sostituzione')->nullable();

            $table->boolean('flag_preventivo')->default(false);

        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classificazioni_estintori');
    }
};
