<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('presidi_intervento', function (Blueprint $table) {
            $table->boolean('usa_ritiro')->default(false)->after('sostituito_con_presidio_id');
        });
    }

    public function down(): void
    {
        Schema::table('presidi_intervento', function (Blueprint $table) {
            $table->dropColumn('usa_ritiro');
        });
    }
};
