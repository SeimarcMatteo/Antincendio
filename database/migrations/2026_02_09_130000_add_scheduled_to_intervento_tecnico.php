<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intervento_tecnico', function (Blueprint $table) {
            if (!Schema::hasColumn('intervento_tecnico', 'scheduled_start_at')) {
                $table->dateTime('scheduled_start_at')->nullable()->after('ended_at');
            }
            if (!Schema::hasColumn('intervento_tecnico', 'scheduled_end_at')) {
                $table->dateTime('scheduled_end_at')->nullable()->after('scheduled_start_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intervento_tecnico', function (Blueprint $table) {
            if (Schema::hasColumn('intervento_tecnico', 'scheduled_end_at')) {
                $table->dropColumn('scheduled_end_at');
            }
            if (Schema::hasColumn('intervento_tecnico', 'scheduled_start_at')) {
                $table->dropColumn('scheduled_start_at');
            }
        });
    }
};
