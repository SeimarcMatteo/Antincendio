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
        Schema::table('import_presidi', function (Blueprint $table) {
            $table->date('data_ultima_revisione')->nullable()->after('data_serbatoio');
        });
        Schema::table('presidi', function (Blueprint $table) {
            $table->date('data_ultima_revisione')->nullable()->after('data_serbatoio');
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
