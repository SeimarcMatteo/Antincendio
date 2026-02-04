<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE import_presidi MODIFY categoria ENUM('Estintore','Idrante','Porta') NOT NULL DEFAULT 'Estintore'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE import_presidi MODIFY categoria ENUM('Estintore') NOT NULL DEFAULT 'Estintore'");
    }
};
