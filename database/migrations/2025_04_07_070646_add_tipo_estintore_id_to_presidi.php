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
        Schema::table('presidi', function (Blueprint $table) {
            $table->foreignId('tipo_estintore_id')->nullable()->constrained('tipi_estintori')->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presidi', function (Blueprint $table) {
            //
        });
    }
};
