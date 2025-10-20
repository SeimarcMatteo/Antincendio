<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('presidi_intervento', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('intervento_id');
            $table->unsignedBigInteger('presidio_id');

            $table->enum('esito', ['verificato', 'non_verificato', 'anomalie'])->default('non_verificato');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('intervento_id')->references('id')->on('interventi')->onDelete('cascade');
            $table->foreign('presidio_id')->references('id')->on('presidi')->onDelete('cascade');

            $table->unique(['intervento_id', 'presidio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presidi_intervento');
    }
};
