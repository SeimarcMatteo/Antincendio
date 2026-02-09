<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipi_presidio', function (Blueprint $table) {
            $table->id();
            $table->string('categoria', 20);
            $table->string('nome', 100);
            $table->timestamps();

            $table->unique(['categoria', 'nome']);
        });

        // Backfill da valori esistenti (se presenti)
        if (Schema::hasColumn('presidi', 'idrante_tipo')) {
            $idranti = DB::table('presidi')
                ->whereNotNull('idrante_tipo')
                ->where('idrante_tipo', '!=', '')
                ->distinct()
                ->pluck('idrante_tipo');

            foreach ($idranti as $val) {
                DB::table('tipi_presidio')->updateOrInsert([
                    'categoria' => 'Idrante',
                    'nome' => mb_strtoupper(trim($val)),
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasColumn('presidi', 'porta_tipo')) {
            $porte = DB::table('presidi')
                ->whereNotNull('porta_tipo')
                ->where('porta_tipo', '!=', '')
                ->distinct()
                ->pluck('porta_tipo');

            foreach ($porte as $val) {
                DB::table('tipi_presidio')->updateOrInsert([
                    'categoria' => 'Porta',
                    'nome' => mb_strtoupper(trim($val)),
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipi_presidio');
    }
};
