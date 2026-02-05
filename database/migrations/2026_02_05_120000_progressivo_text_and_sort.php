<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->convertTable('presidi');
        $this->convertTable('import_presidi');
    }

    public function down(): void
    {
        $this->revertTable('import_presidi');
        $this->revertTable('presidi');
    }

    private function convertTable(string $table): void
    {
        if (!Schema::hasTable($table)) return;

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE {$table} MODIFY progressivo VARCHAR(30)");
        }

        Schema::table($table, function (Blueprint $table) use ($table) {
            if (!Schema::hasColumn($table, 'progressivo_num')) {
                $table->unsignedInteger('progressivo_num')->nullable()->after('progressivo');
            }
            if (!Schema::hasColumn($table, 'progressivo_suffix')) {
                $table->string('progressivo_suffix', 20)->nullable()->after('progressivo_num');
            }
        });

        $this->backfill($table);
    }

    private function revertTable(string $table): void
    {
        if (!Schema::hasTable($table)) return;

        Schema::table($table, function (Blueprint $table) {
            if (Schema::hasColumn($table, 'progressivo_suffix')) {
                $table->dropColumn('progressivo_suffix');
            }
            if (Schema::hasColumn($table, 'progressivo_num')) {
                $table->dropColumn('progressivo_num');
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE {$table} MODIFY progressivo INT");
        }
    }

    private function backfill(string $table): void
    {
        DB::table($table)
            ->select('id', 'progressivo')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    $parsed = $this->parseProgressivo($row->progressivo);
                    if (!$parsed) continue;
                    DB::table($table)->where('id', $row->id)->update([
                        'progressivo' => $parsed['label'],
                        'progressivo_num' => $parsed['num'],
                        'progressivo_suffix' => $parsed['suffix'],
                    ]);
                }
            });
    }

    private function parseProgressivo($value): ?array
    {
        $raw = trim((string) $value);
        if ($raw === '') return null;

        $raw = preg_replace('/\s+/', ' ', $raw);
        $rawUp = mb_strtoupper($raw);

        if (!preg_match('/^(\d+)/', $rawUp, $m)) {
            return null;
        }

        $numStr = $m[1];
        $num = (int) $numStr;
        $rest = trim(mb_substr($rawUp, strlen($numStr)));
        $suffix = $rest !== '' ? preg_replace('/[^A-Z0-9]+/u', '', $rest) : '';

        $label = $suffix !== '' ? ($numStr . ' ' . $suffix) : (string) $numStr;

        return [
            'label' => $label,
            'num' => $num,
            'suffix' => $suffix !== '' ? $suffix : null,
        ];
    }
};
