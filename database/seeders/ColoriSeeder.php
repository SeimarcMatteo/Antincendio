<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Colore;

class ColoriSeeder extends Seeder
{
    public function run(): void
    {
        $palette = [
            ['nome'=>'Rosso',   'hex'=>'#EF4444'],
            ['nome'=>'Nero',    'hex'=>'#111827'],
            ['nome'=>'Blu',     'hex'=>'#3B82F6'],
            ['nome'=>'Verde',   'hex'=>'#22C55E'],
            ['nome'=>'Giallo',  'hex'=>'#EAB308'],
            ['nome'=>'Arancione','hex'=>'#F97316'],
            ['nome'=>'Grigio',  'hex'=>'#9CA3AF'],
        ];
        foreach ($palette as $c) {
            Colore::firstOrCreate(['nome'=>$c['nome']], ['hex'=>$c['hex']]);
        }
    }
}
