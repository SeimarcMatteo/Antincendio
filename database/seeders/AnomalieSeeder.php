<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Anomalia;

class AnomalieSeeder extends Seeder
{
    public function run(): void
    {
        $anomalie = [
            // Estintore
            ['categoria' => 'Estintore', 'etichetta' => 'Etichetta mancante'],
            ['categoria' => 'Estintore', 'etichetta' => 'Sigillo danneggiato'],
            ['categoria' => 'Estintore', 'etichetta' => 'Pressione insufficiente'],
            ['categoria' => 'Estintore', 'etichetta' => 'Manometro non leggibile'],
            ['categoria' => 'Estintore', 'etichetta' => 'Ugello ostruito'],

            // Idrante
            ['categoria' => 'Idrante', 'etichetta' => 'Rubinetto ossidato'],
            ['categoria' => 'Idrante', 'etichetta' => 'Tubo danneggiato'],
            ['categoria' => 'Idrante', 'etichetta' => 'Valvola bloccata'],
            ['categoria' => 'Idrante', 'etichetta' => 'Targhetta illeggibile'],

            // Porta
            ['categoria' => 'Porta', 'etichetta' => 'Molla richiamo danneggiata'],
            ['categoria' => 'Porta', 'etichetta' => 'Guarnizione usurata'],
            ['categoria' => 'Porta', 'etichetta' => 'Chiusura non funzionante'],
            ['categoria' => 'Porta', 'etichetta' => 'Targhetta non presente'],
        ];

        foreach ($anomalie as $a) {
            Anomalia::firstOrCreate([
                'categoria' => $a['categoria'],
                'etichetta' => $a['etichetta'],
            ], ['attiva' => true]);
        }
    }
}
