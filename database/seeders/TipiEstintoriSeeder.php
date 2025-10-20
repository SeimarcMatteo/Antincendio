<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB; // <--- AGGIUNGI QUESTA RIGA
use Illuminate\Database\Seeder;

class TipiEstintoriSeeder extends Seeder
{
    public function run()
    {
        DB::table('tipi_estintori')->insert([
            [
                'sigla' => 'P6',
                'descrizione' => 'Estintore a Polvere 6kg',
                'kg' => 6,
                'tipo' => 'Polvere',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sigla' => 'CO2_5',
                'descrizione' => 'Estintore CO₂ 5kg',
                'kg' => 5,
                'tipo' => 'CO2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sigla' => 'S9',
                'descrizione' => 'Estintore a Schiuma 9kg',
                'kg' => 9,
                'tipo' => 'Schiuma',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}