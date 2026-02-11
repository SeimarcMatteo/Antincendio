<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Livewire\Presidi\ImportaPresidi;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presidi', function (Blueprint $table) {
            if (!Schema::hasColumn('presidi', 'idrante_tipo_id')) {
                $table->unsignedBigInteger('idrante_tipo_id')->nullable()->after('idrante_tipo');
                $table->index('idrante_tipo_id');
            }
            if (!Schema::hasColumn('presidi', 'porta_tipo_id')) {
                $table->unsignedBigInteger('porta_tipo_id')->nullable()->after('porta_tipo');
                $table->index('porta_tipo_id');
            }
        });

        Schema::table('import_presidi', function (Blueprint $table) {
            if (!Schema::hasColumn('import_presidi', 'idrante_tipo_id')) {
                $table->unsignedBigInteger('idrante_tipo_id')->nullable()->after('idrante_tipo');
                $table->index('idrante_tipo_id');
            }
            if (!Schema::hasColumn('import_presidi', 'porta_tipo_id')) {
                $table->unsignedBigInteger('porta_tipo_id')->nullable()->after('porta_tipo');
                $table->index('porta_tipo_id');
            }
        });

        if (Schema::hasTable('tipi_presidio')) {
            $this->backfillTipoIds('presidi');
            $this->backfillTipoIds('import_presidi');
        }
    }

    public function down(): void
    {
        Schema::table('presidi', function (Blueprint $table) {
            if (Schema::hasColumn('presidi', 'idrante_tipo_id')) {
                $table->dropIndex(['idrante_tipo_id']);
                $table->dropColumn('idrante_tipo_id');
            }
            if (Schema::hasColumn('presidi', 'porta_tipo_id')) {
                $table->dropIndex(['porta_tipo_id']);
                $table->dropColumn('porta_tipo_id');
            }
        });

        Schema::table('import_presidi', function (Blueprint $table) {
            if (Schema::hasColumn('import_presidi', 'idrante_tipo_id')) {
                $table->dropIndex(['idrante_tipo_id']);
                $table->dropColumn('idrante_tipo_id');
            }
            if (Schema::hasColumn('import_presidi', 'porta_tipo_id')) {
                $table->dropIndex(['porta_tipo_id']);
                $table->dropColumn('porta_tipo_id');
            }
        });
    }

    private function backfillTipoIds(string $table): void
    {
        DB::table($table)
            ->whereIn('categoria', ['Idrante', 'Porta'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    if ($row->categoria === 'Idrante' && empty($row->idrante_tipo_id) && !empty($row->idrante_tipo)) {
                        $label = ImportaPresidi::normalizeIdranteTipo(
                            $row->idrante_tipo,
                            $row->idrante_lunghezza,
                            (bool) $row->idrante_sopra_suolo,
                            (bool) $row->idrante_sotto_suolo,
                            $row->idrante_tipo
                        );
                        $tipoId = ImportaPresidi::resolveTipoPresidioId('Idrante', $label);
                        if ($tipoId) {
                            DB::table($table)->where('id', $row->id)->update([
                                'idrante_tipo_id' => $tipoId,
                            ]);
                        }
                    }

                    if ($row->categoria === 'Porta' && empty($row->porta_tipo_id) && !empty($row->porta_tipo)) {
                        $label = ImportaPresidi::normalizePortaTipo($row->porta_tipo);
                        $tipoId = ImportaPresidi::resolveTipoPresidioId('Porta', $label);
                        if ($tipoId) {
                            DB::table($table)->where('id', $row->id)->update([
                                'porta_tipo_id' => $tipoId,
                            ]);
                        }
                    }
                }
            });
    }
};
