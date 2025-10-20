<?php

namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\Intervento;
use App\Models\Presidio;
use App\Models\PresidioIntervento;

use App\Models\PresidioRitirato;
use App\Models\Anomalia;
use App\Models\TipoEstintore;
use Carbon\Carbon;

use Livewire\Attributes\On;

class EvadiInterventoSingolo extends Component
{
    public Intervento $intervento;
    public $input = [];
    public $vistaSchede = true;
    public $durataEffettiva;

    public $formNuovoVisibile = false;
    public $nuovoPresidio = [];
    public $messaggioErrore = null;
    public $messaggioSuccesso = null;


    public $firmaCliente; // base64
    public $mostraFirma = false;

    #[On('firmaClienteAcquisita')]
    public function salvaFirmaCliente($data)
    {
        $this->intervento->update([
            'firma_cliente_base64' => $data['base64']
        ]);

        $this->messaggioSuccesso = "Firma salvata con successo.";
    }

    

public function apriFormNuovoPresidio()
{
    $this->formNuovoVisibile = true;

    $this->nuovoPresidio = [
        'ubicazione' => '',
        'tipo_estintore_id' => null,
        'data_serbatoio' => null,
        'categoria' => 'Estintore',
        'note' => '',
        'usa_ritiro' => false, // nuovo flag
    ];
    
}

public function salvaNuovoPresidio()
{
    $clienteId = $this->intervento->cliente_id;
    $sedeId = $this->intervento->sede_id;
    $categoria = $this->nuovoPresidio['categoria'] ?? 'Estintore';
    if ($this->nuovoPresidio['usa_ritiro']) {
        $giacenza = \App\Models\GiacenzaPresidio::where('categoria', $this->nuovoPresidio['categoria'])
            ->where('tipo_estintore_id', $this->nuovoPresidio['tipo_estintore_id'])
            ->first();
    
        if (!$giacenza || $giacenza->quantita_disponibile < 1) {
            $this->messaggioErrore = 'Nessuna giacenza disponibile per questo tipo di presidio.';
            return;
        }
    
        $giacenza->decrement('quantita_disponibile');
    }
    $progressivo = Presidio::prossimoProgressivo($clienteId, $sedeId, $categoria);

    $presidio = Presidio::create([
        'cliente_id' => $clienteId,
        'sede_id' => $sedeId,
        'categoria' => $categoria,
        'progressivo' => $progressivo,
        'ubicazione' => $this->nuovoPresidio['ubicazione'],
        'tipo_estintore_id' => $this->nuovoPresidio['tipo_estintore_id'],
        'data_serbatoio' => $this->nuovoPresidio['data_serbatoio'],
        'note' => $this->nuovoPresidio['note'],
    ]);

    // Calcolo scadenze
    $presidio->load('tipoEstintore');
    $presidio->calcolaScadenze();
    $presidio->save();

    // Aggiunta al presidi_intervento
    $pi = $this->intervento->presidiIntervento()->create([
        'presidio_id' => $presidio->id,
        'esito' => 'non_verificato',
    ]);

    // Ricarico l'intervento completo con il nuovo legame
    $this->intervento->load('presidiIntervento.presidio');
    
    // Inizializzazione sicura input
    $this->input[$pi->id] = [
        'ubicazione' => $presidio->ubicazione,
        'note' => null,
        'esito' => 'non_verificato',
        'anomalie' => [],
        'sostituito_con' => 0,
        'sostituzione' => false,
        'nuovo_tipo_estintore_id' => null,
        'nuova_data_serbatoio' => null,
        'usa_ritiro' => false,
        'tipo_estintore_sigla' => optional($presidio->tipoEstintore)->sigla ?? '-',
        'deve_ritirare' => $this->verificaRitiroObbligato($presidio),
    ];

    $this->formNuovoVisibile = false;
    $this->messaggioSuccesso = 'Nuovo presidio aggiunto correttamente.';
    }


    
    public function mount(Intervento $intervento)
    {
        $this->intervento = $intervento->load('cliente', 'sede', 'presidiIntervento.presidio');
        $this->durataEffettiva = $this->intervento->durata_effettiva;

        // Se non esistono ancora presidi_intervento, generarli
        if ($this->intervento->presidiIntervento->isEmpty()) {
            $presidi = Presidio::where('cliente_id', $intervento->cliente_id)
            ->when(
                $intervento->sede_id,
                fn($q) => $q->where('sede_id', $intervento->sede_id),
                fn($q) => $q->whereNull('sede_id') // se sede_id è null
            )
            ->get();
        
    
            foreach ($presidi as $presidio) {
                $this->intervento->presidiIntervento()->create([
                    'presidio_id' => $presidio->id,
                    'esito' => 'non_verificato',
                ]);
            }
    
            // Reload dopo la creazione
            $this->intervento->load('presidiIntervento.presidio');
        }
    
        // Inizializzazione input per ogni presidio_intervento
        foreach ($this->intervento->presidiIntervento as $pi) {
            $presidio = $pi->presidio;
        
            $deveEssereRitirato = $this->verificaRitiroObbligato($presidio);
        
            $this->input[$pi->id] = [
                'ubicazione' => $presidio->ubicazione,
                'note' => $pi->note,
                'esito' => $pi->esito ?? 'non_verificato',
                'anomalie' => is_array($pi->anomalie) ? $pi->anomalie : [],
                'sostituito_con' => Presidio::find($pi->sostituito_con_presidio_id) ?? 0,
                'sostituzione' => false,
                'nuovo_tipo_estintore_id' => null,
                'nuova_data_serbatoio' => null,
                'usa_ritiro' => $pi->usa_ritiro ?? false,
                'tipo_estintore_sigla' => optional($presidio->tipoEstintore)->sigla ?? '-',
                'deve_ritirare' => $deveEssereRitirato,
            ];
        }
    }
    protected function verificaRitiroObbligato(Presidio $presidio): bool
    {
        $oggi = \Carbon\Carbon::today();
        return collect([
            $presidio->data_revisione,
            $presidio->data_collaudo,
            $presidio->data_fine_vita,
            $presidio->data_sostituzione,
        ])
        ->filter()
        ->contains(fn($data) => \Carbon\Carbon::parse($data)->isSameMonth($oggi));
    }

   
    public function rimuoviPresidioIntervento($id)
    {
        $pi = $this->intervento->presidiIntervento->firstWhere('id', $id);
    
        if (!$pi) {
            $this->messaggioErrore ="Presidio non trovato nell'intervento.";
            return;
        }
    
        unset($this->input[$id]);
        $pi->delete();
    
        $this->messaggioSuccesso = 'Presidio rimosso dall’intervento.';
        $this->intervento->load('presidiIntervento.presidio');
    }
    public function getAnomalieProperty()
    {
        return Anomalia::where('attiva', true)->get()->groupBy('categoria');
    }

    public function getTipiEstintoriProperty()
    {
        return TipoEstintore::orderBy('sigla')->get();
    }

    public function salva()
    {
        // Controlla se tutti i presidi sono stati verificati
        if (! $this->interventoCompletabile) {
            $this->messaggioErrore ='Verifica tutti i presidi prima di completare l’intervento.';
            return;
        }
        if ($this->interventoCompletabile) {
            foreach ($this->intervento->presidiIntervento as $pi) {
                $this->salvaPresidio($pi->id);
            }

            // Se tutti i presidi sono verificati, completa l'intervento
            $this->intervento->update(['stato' => 'Completato','durata_effettiva' => $this->durataEffettiva,]);
            $this->messaggioSuccesso ='Intervento evaso correttamente!';
            return redirect()->route('interventi.evadi');
        } else {
            // Mostra un messaggio di errore se non tutti i presidi sono verificati
            $this->messaggioErrore ='Devi verificare tutti i presidi prima di completare l\'intervento.';
        }
    }

    
    public function getInterventoCompletabileProperty(): bool
    {
        // Tutti i presidi devono avere esito definito e diverso da "non_verificato"
        return collect($this->input)
            ->filter(fn($p) => isset($p['esito']))
            ->every(fn($p) => $p['esito'] !== 'non_verificato');
    }
    public function toggleSostituzione($id)
    {
        $this->input[$id]['sostituzione'] = !$this->input[$id]['sostituzione'];
    }

    public function sostituisciPresidio($id)
    {
        $dati = $this->input[$id];
        $pi = $this->intervento->presidiIntervento->firstWhere('id', $id);
        if (!$pi) return;

        $vecchio = $pi->presidio;

        $duplicato = Presidio::where('cliente_id', $vecchio->cliente_id)
            ->where('sede_id', $vecchio->sede_id)
            ->where('categoria', $vecchio->categoria)
            ->where('progressivo', $vecchio->progressivo)
            ->where('attivo', true)
            ->first();

        if ($duplicato && $duplicato->id !== $vecchio->id) {
            $this->messaggioErrore = 'Esiste già un presidio attivo con questo progressivo.';
            return;
        }

        if ($dati['usa_ritiro'] ?? false) {
            $giacenza = \App\Models\GiacenzaPresidio::firstOrCreate([
                'categoria' => $vecchio->categoria,
                'tipo_estintore_id' => $dati['nuovo_tipo_estintore_id'],
            ], [
                'quantita' => 0
            ]);

            if ($giacenza->quantita <= 0) {
                $this->messaggioErrore = "Nessuna giacenza disponibile per tipo {$dati['nuovo_tipo_estintore_id']}.";
                return;
            }

            $giacenza->decrement('quantita');
        }

        $nuovo = new Presidio([
            'cliente_id' => $vecchio->cliente_id,
            'sede_id' => $vecchio->sede_id,
            'categoria' => $vecchio->categoria,
            'progressivo' => $vecchio->progressivo,
            'ubicazione' => $vecchio->ubicazione,
            'tipo_estintore_id' => $dati['nuovo_tipo_estintore_id'],
            'data_serbatoio' => $dati['nuova_data_serbatoio'],
            'mesi_visita' => $vecchio->mesi_visita,
        ]);

        $nuovo->save();
        $nuovo->load('tipoEstintore');
        $nuovo->calcolaScadenze();
        $nuovo->save();

        $statoRitiro = $dati['stato_presidio_ritirato'] ?? null;
        if ($statoRitiro !== 'Rottamato') {
            $giacenza = \App\Models\GiacenzaPresidio::firstOrCreate([
                'categoria' => $vecchio->categoria,
                'tipo_estintore_id' => $vecchio->tipo_estintore_id,
            ], [
                'quantita' => 0
            ]);
            $giacenza->increment('quantita');
        }

        $vecchio->attivo = false;
        $vecchio->sostituito_con_presidio_id = $nuovo->id;
        $vecchio->save();

        $pi->sostituito_con_presidio_id = $nuovo->id;
        $pi->save();

        $this->messaggioSuccesso = 'Presidio sostituito con successo!';
    }



    
    public function salvaPresidio($id)
    {
        $dati = $this->input[$id];
        $pi = $this->intervento->presidiIntervento->firstWhere('id', $id);

        if (!$pi) return;

        $pi->note = $dati['note'];
        $pi->esito = $dati['esito'];
        $pi->anomalie = $dati['anomalie'] ?? [];
        $pi->usa_ritiro = $dati['usa_ritiro'] ?? false;

        if ($dati['sostituzione']) {
            $vecchio = $pi->presidio;

            if ($pi->sostituito_con_presidio_id) {
                $this->messaggioErrore = "Il presidio #{$vecchio->id} è già stato sostituito.";
                return;
            }

            if ($pi->usa_ritiro) {
                $giacenza = \App\Models\GiacenzaPresidio::firstOrCreate([
                    'categoria' => $vecchio->categoria,
                    'tipo_estintore_id' => $dati['nuovo_tipo_estintore_id'],
                ], [
                    'quantita' => 0
                ]);

                if ($giacenza->quantita <= 0) {
                    $this->messaggioErrore = "Nessuna giacenza disponibile per tipo {$dati['nuovo_tipo_estintore_id']}.";
                    return;
                }

                $giacenza->decrement('quantita');
            }

            $nuovo = new Presidio([
                'cliente_id' => $vecchio->cliente_id,
                'sede_id' => $vecchio->sede_id,
                'categoria' => $vecchio->categoria,
                'progressivo' => $vecchio->progressivo,
                'ubicazione' => $vecchio->ubicazione,
                'tipo_estintore_id' => $dati['nuovo_tipo_estintore_id'],
                'data_serbatoio' => $dati['nuova_data_serbatoio'],
                'mesi_visita' => $vecchio->mesi_visita,
            ]);

            $nuovo->save();
            $nuovo->load('tipoEstintore');
            $nuovo->calcolaScadenze();
            $nuovo->save();

            $statoRitiro = $dati['stato_presidio_ritirato'] ?? null;
            if ($statoRitiro !== 'Rottamato') {
                $giacenza = \App\Models\GiacenzaPresidio::firstOrCreate([
                    'categoria' => $vecchio->categoria,
                    'tipo_estintore_id' => $vecchio->tipo_estintore_id,
                ], [
                    'quantita' => 0
                ]);
                $giacenza->increment('quantita');
            }

            $vecchio->attivo = false;
            $vecchio->save();

            $pi->sostituito_con_presidio_id = $nuovo->id;
        }

        $pi->save();
        $this->messaggioSuccesso = 'Presidio aggiornato correttamente.';
    }




    
    

    public function render()
    {
        return view('livewire.interventi.evadi-intervento-singolo', [
            'interventoCompletabile' => $this->interventoCompletabile,
            'anomalie' => $this->anomalie,
            'tipiEstintori' => $this->tipiEstintori,
        ])->layout('layouts.app');
    }
}
