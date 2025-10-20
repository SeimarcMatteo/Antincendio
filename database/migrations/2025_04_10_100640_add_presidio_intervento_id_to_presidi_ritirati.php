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
        Schema::table('presidi_ritirati', function (Blueprint $table) {
            $table->unsignedBigInteger('presidio_intervento_id')->nullable()->after('sede_id');

            $table->foreign('presidio_intervento_id')
                  ->references('id')
                  ->on('presidi_intervento')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presidi_ritirati', function (Blueprint $table) {
            $table->dropForeign(['presidio_intervento_id']);
            $table->dropColumn('presidio_intervento_id');
        });
    }
};
