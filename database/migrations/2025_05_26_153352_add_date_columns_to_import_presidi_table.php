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
            // dopo data_serbatoio aggiungiamo le altre quattro date
            $table->date('data_revisione')->nullable()->after('data_serbatoio');
            $table->date('data_collaudo')->nullable()->after('data_revisione');
            $table->date('data_fine_vita')->nullable()->after('data_collaudo');
            $table->date('data_sostituzione')->nullable()->after('data_fine_vita');
        });
    }

    public function down(): void
    {
        Schema::table('import_presidi', function (Blueprint $table) {
            $table->dropColumn([
                'data_revisione',
                'data_collaudo',
                'data_fine_vita',
                'data_sostituzione',
            ]);
        });
    }
};
