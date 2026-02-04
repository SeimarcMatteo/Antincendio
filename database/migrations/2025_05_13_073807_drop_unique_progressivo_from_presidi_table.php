<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
            // Rimuove in sicurezza FK da presidi_intervento, presidi_ritirati, ecc.
            $this->dropForeignSafe('presidi_intervento', 'presidio_id');
            $this->dropForeignSafe('presidi_intervento', 'sostituito_con_presidio_id');
            $this->dropForeignSafe('presidi_ritirati', 'presidio_id');
            $this->dropForeignSafe('presidi_riutilizzo', 'presidio_id');
            $this->dropForeignSafe('presidi_storico', 'presidio_id');
    
            // Il campo sostituito_con_presidio_id in presidi non ha FK: rimuovo solo l'indice se presente
            if (Schema::hasColumn('presidi', 'sostituito_con_presidio_id')) {
                try {
                    Schema::table('presidi', function (Blueprint $table) {
                        $table->dropIndex(['sostituito_con_presidio_id']);
                    });
                } catch (\Throwable $e) {
                    // Ignora se l'indice non esiste
                }
            }
    
            // Rimuove UNIQUE sul progressivo
            try {
                Schema::table('presidi', function (Blueprint $table) {
                    $table->dropUnique(['cliente_id', 'sede_id', 'categoria', 'progressivo']);
                });
            } catch (\Throwable $e) {
                // Ignora se già rimosso
            }
    
            // Ricrea le FK in modo pulito
            Schema::table('presidi', function (Blueprint $table) {
                $table->foreign('sostituito_con_presidio_id')->references('id')->on('presidi')->nullOnDelete();
            });
    
            Schema::table('presidi_intervento', function (Blueprint $table) {
                $table->foreign('presidio_id')->references('id')->on('presidi')->cascadeOnDelete();
                $table->foreign('sostituito_con_presidio_id')->references('id')->on('presidi')->nullOnDelete();
            });
    
            Schema::table('presidi_ritirati', function (Blueprint $table) {
                $table->foreign('presidio_id')->references('id')->on('presidi')->cascadeOnDelete();
            });
    
            Schema::table('presidi_riutilizzo', function (Blueprint $table) {
                $table->foreign('presidio_id')->references('id')->on('presidi')->cascadeOnDelete();
            });
    
            Schema::table('presidi_storico', function (Blueprint $table) {
                $table->foreign('presidio_id')->references('id')->on('presidi')->cascadeOnDelete();
            });
        }
    
        public function down(): void
        {
            // Ripristino il vincolo UNIQUE sul progressivo
            Schema::table('presidi', function (Blueprint $table) {
                $table->unique(['cliente_id', 'sede_id', 'categoria', 'progressivo']);
            });
        }
    
        private function dropForeignSafe(string $table, string $column): void
        {
            try {
                // Ottieni nome del vincolo se presente
                $fkName = DB::table('information_schema.KEY_COLUMN_USAGE')
                    ->where('TABLE_SCHEMA', DB::getDatabaseName())
                    ->where('TABLE_NAME', $table)
                    ->where('COLUMN_NAME', $column)
                    ->whereNotNull('REFERENCED_TABLE_NAME')
                    ->value('CONSTRAINT_NAME');
    
                if ($fkName) {
                    Schema::table($table, function (Blueprint $table) use ($fkName) {
                        $table->dropForeign($fkName);
                    });
                }
            } catch (\Throwable $e) {
                // Silenzia eventuali errori
            }
        }
    };