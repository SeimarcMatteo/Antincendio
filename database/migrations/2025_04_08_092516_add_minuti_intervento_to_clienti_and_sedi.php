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
    Schema::table('clienti', function (Blueprint $table) {
        $table->unsignedInteger('minuti_intervento')->default(60);
    });

    Schema::table('sedi', function (Blueprint $table) {
        $table->unsignedInteger('minuti_intervento')->default(60);
    });
}

public function down()
{
    Schema::table('clienti', function (Blueprint $table) {
        $table->dropColumn('minuti_intervento');
    });

    Schema::table('sedi', function (Blueprint $table) {
        $table->dropColumn('minuti_intervento');
    });
}

};
