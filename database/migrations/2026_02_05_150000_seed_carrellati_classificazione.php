<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('classificazioni_estintori')->updateOrInsert(
            ['id' => 4],
            [
                'nome' => 'Carrellati Schiuma',
                'anni_revisione_dopo' => 5,
                'anni_revisione_prima' => 5,
                'anni_collaudo' => null,
                'anni_fine_vita' => 5,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $tipi = [
            [
                'sigla' => 'ESSI100',
                'descrizione' => 'Estintore carrellato schiuma 100 L',
                'kg' => 100,
                'tipo' => 'Schiuma',
            ],
            [
                'sigla' => 'ESSI200',
                'descrizione' => 'Estintore carrellato schiuma 200 L',
                'kg' => 200,
                'tipo' => 'Schiuma',
            ],
        ];

        foreach ($tipi as $t) {
            $existing = DB::table('tipi_estintori')->where('sigla', $t['sigla'])->first();
            if ($existing) {
                DB::table('tipi_estintori')
                    ->where('id', $existing->id)
                    ->update(array_merge($t, [
                        'classificazione_id' => 4,
                        'updated_at' => $now,
                    ]));
            } else {
                DB::table('tipi_estintori')->insert(array_merge($t, [
                    'classificazione_id' => 4,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('tipi_estintori')->whereIn('sigla', ['ESSI100','ESSI200'])->delete();
        DB::table('classificazioni_estintori')->where('id', 4)->delete();
    }
};
