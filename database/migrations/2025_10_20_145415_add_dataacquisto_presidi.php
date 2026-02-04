<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/2025_10_20_000001_add_acquisto_to_presidi.php
return new class extends Migration {
    public function up(): void {
        Schema::table('presidi', function (Blueprint $t) {
            $t->date('data_acquisto')->nullable()->after('tipo_estintore_id');
            $t->date('scadenza_presidio')->nullable()->after('data_acquisto');
        });
        Schema::table('import_presidi', function (Blueprint $t) {
            $t->date('data_acquisto')->nullable()->after('tipo_estintore_id');
            $t->date('scadenza_presidio')->nullable()->after('data_acquisto');
        });
    }
    public function down(): void {
        Schema::table('presidi', fn (Blueprint $t) => $t->dropColumn(['data_acquisto','scadenza_presidio']));
        Schema::table('import_presidi', fn (Blueprint $t) => $t->dropColumn(['data_acquisto','scadenza_presidio']));
    }
};
