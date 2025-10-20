<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFatturazioneClienti extends Migration
{
    public function up(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            $table->enum('fatturazione_tipo', ['annuale', 'semestrale'])->nullable()->after('zona');
            $table->unsignedTinyInteger('mese_fatturazione')->nullable()->after('fatturazione_tipo'); // 1-12
        });
    }

    public function down(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            $table->dropColumn([
                'fatturazione_tipo',
                'mese_fatturazione',
            ]);
        });
    }
}
