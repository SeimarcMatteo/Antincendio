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
        //
        Schema::table('clienti', function (Blueprint $table) {
            $table->json('mesi_visita')->nullable()->after('provincia');

        });
        Schema::table('sedi', function (Blueprint $table) {
            $table->json('mesi_visita')->nullable()->after('provincia');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
