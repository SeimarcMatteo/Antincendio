<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presidi', function (Blueprint $table) {
            if (!Schema::hasColumn('presidi', 'marca_serbatoio')) {
                $table->string('marca_serbatoio', 20)->nullable()->after('data_serbatoio');
            }
        });

        Schema::table('import_presidi', function (Blueprint $table) {
            if (!Schema::hasColumn('import_presidi', 'marca_serbatoio')) {
                $table->string('marca_serbatoio', 20)->nullable()->after('data_serbatoio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('presidi', function (Blueprint $table) {
            if (Schema::hasColumn('presidi', 'marca_serbatoio')) {
                $table->dropColumn('marca_serbatoio');
            }
        });

        Schema::table('import_presidi', function (Blueprint $table) {
            if (Schema::hasColumn('import_presidi', 'marca_serbatoio')) {
                $table->dropColumn('marca_serbatoio');
            }
        });
    }
};
