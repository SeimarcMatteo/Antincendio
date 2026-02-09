<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intervento_tecnico', function (Blueprint $table) {
            if (!Schema::hasColumn('intervento_tecnico', 'started_at')) {
                $table->dateTime('started_at')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('intervento_tecnico', 'ended_at')) {
                $table->dateTime('ended_at')->nullable()->after('started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intervento_tecnico', function (Blueprint $table) {
            if (Schema::hasColumn('intervento_tecnico', 'ended_at')) {
                $table->dropColumn('ended_at');
            }
            if (Schema::hasColumn('intervento_tecnico', 'started_at')) {
                $table->dropColumn('started_at');
            }
        });
    }
};
