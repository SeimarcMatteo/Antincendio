<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tipi_estintori', function (Blueprint $table) {
            $table->foreignId('colore_id')
                ->nullable()
                ->after('classificazione_id')
                ->constrained('colori')
                ->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('tipi_estintori', function (Blueprint $table) {
            $table->dropConstrainedForeignId('colore_id');
        });
    }
};
