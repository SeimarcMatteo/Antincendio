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
        Schema::create('import_presidi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clienti');
            $table->foreignId('sede_id')->nullable()->constrained('sedi');
            $table->enum('categoria', ['Estintore'])->default('Estintore');
            $table->integer('progressivo');
            $table->string('ubicazione')->nullable();
            $table->string('tipo_contratto')->nullable();
            $table->string('tipo_estintore')->nullable();
            $table->unsignedBigInteger('tipo_estintore_id')->nullable();
            $table->date('data_serbatoio')->nullable();
            $table->boolean('flag_anomalia1')->default(0);
            $table->boolean('flag_anomalia2')->default(0);
            $table->boolean('flag_anomalia3')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_presidi');
    }
};
