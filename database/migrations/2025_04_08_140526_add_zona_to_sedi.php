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
        Schema::table('sedi', function (Blueprint $table) {
            $table->string('zona')->nullable()->after('citta');
        });
        Schema::table('clienti', function (Blueprint $table) {
            $table->string('zona')->nullable()->after('citta');
        });
                
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sedi', function (Blueprint $table) {
            //
        });
    }
};
