<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interventi', function (Blueprint $table) {
            if (!Schema::hasColumn('interventi', 'note')) {
                $table->text('note')->nullable()->after('zona');
            }
        });
    }

    public function down(): void
    {
        Schema::table('interventi', function (Blueprint $table) {
            if (Schema::hasColumn('interventi', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
