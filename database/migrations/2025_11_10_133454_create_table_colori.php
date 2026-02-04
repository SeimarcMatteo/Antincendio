<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('colori', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 30)->unique();
            $table->char('hex', 7); // Formato "#RRGGBB"
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('colori');
    }
};
