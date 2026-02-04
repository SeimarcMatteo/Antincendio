<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB; // <--- AGGIUNGI QUESTA RIGA
use Illuminate\Database\Seeder;

class ClassificazioniEstintoriSeeder extends Seeder
{
    public function run()
    {
        DB::table('classificazioni_estintori')->insert([
            [
                'nome' => 'Estintori a Polvere',
                'anni_revisione_dopo' => 5,
                'anni_revisione_prima' => 3,
                'anni_collaudo' => null,
                'anni_fine_vita' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Estintori ad Anidride Carbonica (CO₂)',
                'anni_revisione_dopo' => 5,
                'anni_revisione_prima' => 3,
                'anni_collaudo' => 10,
                'anni_fine_vita' => 18,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nome' => 'Estintori a Schiuma',
                'anni_revisione_dopo' => 4,
                'anni_revisione_prima' => 4,
                'anni_collaudo' => null,
                'anni_fine_vita' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}