<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Cliente;
use App\Models\Sede;

class SincronizzaClienti extends Command
{
    protected $signature = 'sincronizza:clienti';
    protected $description = 'Sincronizza clienti e sedi da MSSQL a MySQL (basata su codice_esterno)';

    public function handle()
    {
        $this->info("🔁 Inizio sincronizzazione clienti e sedi...");

        $clienti = DB::connection('sqlsrv')
            ->table('anagra')
            ->where('an_tipo', 'C')
            ->get();

        foreach ($clienti as $c) {
            $cliente = Cliente::updateOrCreate(
                ['codice_esterno' => $c->an_conto],
                [
                    'nome' => trim($c->an_descr1 . ' ' . $c->an_descr2),
                    'p_iva' => $c->an_pariva,
                    'email' => $c->an_email,
                    'telefono' => $c->an_telef,
                    'indirizzo' => $c->an_indir,
                    'cap' => $c->an_cap,
                    'citta' => $c->an_citta,
                    'provincia' => $c->an_prov,
                ]
            );

            $this->info("✔️ Cliente sincronizzato: {$cliente->nome} ({$c->an_conto})");

            // Recupero sedi collegate
            $sedi = DB::connection('sqlsrv')
                ->table('destdiv')
                ->where('dd_conto', $c->an_conto)
                ->get();

            foreach ($sedi as $s) {
                Sede::updateOrCreate(
                    [
                        'codice_esterno' => $s->dd_conto . '-' . $s->dd_coddest,
                        'cliente_id' => $cliente->id,
                    ],
                    [
                        'nome' => trim($s->dd_nomdest . ' ' . $s->dd_nomdest2),
                        'indirizzo' => $s->dd_inddest,
                        'cap' => $s->dd_capdest,
                        'citta' => $s->dd_locdest,
                        'provincia' => $s->dd_prodest,
                    ]
                );
            }

            if ($sedi->count() > 0) {
                $this->info("   → Sedi sincronizzate: {$sedi->count()}");
            }
        }

        $this->info("✅ Sincronizzazione completata con successo.");
        return Command::SUCCESS;
    }
}
