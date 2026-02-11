<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('intervento_tecnico_sessioni')) {
            Schema::create('intervento_tecnico_sessioni', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('intervento_tecnico_id');
                $table->dateTime('started_at');
                $table->dateTime('ended_at')->nullable();
                $table->timestamps();

                $table->index(['intervento_tecnico_id', 'started_at'], 'idx_it_sessioni_tecnico_start');
                $table->index(['intervento_tecnico_id', 'ended_at'], 'idx_it_sessioni_tecnico_end');

                $table->foreign('intervento_tecnico_id', 'fk_it_sessioni_intervento_tecnico')
                    ->references('id')
                    ->on('intervento_tecnico')
                    ->onDelete('cascade');
            });
        }

        // Backfill: crea una sessione per i timer storici già presenti in intervento_tecnico
        DB::table('intervento_tecnico')
            ->select(['id', 'started_at', 'ended_at'])
            ->whereNotNull('started_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $exists = DB::table('intervento_tecnico_sessioni')
                        ->where('intervento_tecnico_id', $row->id)
                        ->exists();
                    if ($exists) {
                        continue;
                    }

                    DB::table('intervento_tecnico_sessioni')->insert([
                        'intervento_tecnico_id' => $row->id,
                        'started_at' => $row->started_at,
                        'ended_at' => $row->ended_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervento_tecnico_sessioni');
    }
};
