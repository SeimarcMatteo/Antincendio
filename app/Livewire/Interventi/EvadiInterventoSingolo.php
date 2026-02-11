<?php

namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\Intervento;
use App\Models\Presidio;
use App\Models\PresidioIntervento;

use App\Models\PresidioRitirato;
use App\Models\Anomalia;
use App\Models\TipoEstintore;
use App\Models\InterventoTecnico;
use App\Models\TipoPresidio;
use Carbon\Carbon;

use Livewire\Attributes\On;

class EvadiInterventoSingolo extends Component
{
    public Intervento $intervento;
    public $input = [];
    public $vistaSchede = true;
    public $durataEffettiva;
    public array $marcaSuggestions = [];
    public array $previewSostituzione = [];
    public array $previewNuovo = [];

    public $formNuovoVisibile = false;
    public $nuovoPresidio = [];
    public $messaggioErrore = null;
    public $messaggioSuccesso = null;


    public $firmaCliente; // base64
    public $mostraFirma = false;
    public array $tipiIdranti = [];
    public array $tipiPorte = [];
    public array $editMode = [];
    public array $editPresidio = [];

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
        'marca_serbatoio' => null,
        'data_ultima_revisione' => null,
        'categoria' => 'Estintore',
        'idrante_tipo_id' => null,
        'idrante_lunghezza' => null,
        'idrante_sopra_suolo' => false,
        'idrante_sotto_suolo' => false,
        'porta_tipo_id' => null,
        'note' => '',
        'usa_ritiro' => false, // nuovo flag
    ];
    $this->previewNuovo = [];
    
}

public function salvaNuovoPresidio()
{
    $clienteId = $this->intervento->cliente_id;
    $sedeId = $this->intervento->sede_id;
    $categoria = $this->nuovoPresidio['categoria'] ?? 'Estintore';
    if ($categoria === 'Estintore' && $this->nuovoPresidio['usa_ritiro']) {
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

    $tipoEstintoreId = $categoria === 'Estintore' ? $this->nuovoPresidio['tipo_estintore_id'] : null;
    $dataSerbatoio = $categoria === 'Estintore' ? $this->nuovoPresidio['data_serbatoio'] : null;
    $marcaSerbatoio = $categoria === 'Estintore' ? $this->normalizeMarca($this->nuovoPresidio['marca_serbatoio'] ?? null) : null;
    $dataUltimaRev = $categoria === 'Estintore' ? ($this->nuovoPresidio['data_ultima_revisione'] ?? null) : null;

    $idranteTipoId = $categoria === 'Idrante' ? ($this->nuovoPresidio['idrante_tipo_id'] ?? null) : null;
    $portaTipoId = $categoria === 'Porta' ? ($this->nuovoPresidio['porta_tipo_id'] ?? null) : null;

    $presidio = Presidio::create([
        'cliente_id' => $clienteId,
        'sede_id' => $sedeId,
        'categoria' => $categoria,
        'progressivo' => $progressivo,
        'ubicazione' => $this->nuovoPresidio['ubicazione'],
        'tipo_estintore_id' => $tipoEstintoreId,
        'data_serbatoio' => $dataSerbatoio,
        'marca_serbatoio' => $marcaSerbatoio,
        'data_ultima_revisione' => $dataUltimaRev,
        'idrante_tipo_id' => $idranteTipoId ?: null,
        'idrante_lunghezza' => $this->nuovoPresidio['idrante_lunghezza'] ?? null,
        'idrante_sopra_suolo' => $this->nuovoPresidio['idrante_sopra_suolo'] ?? false,
        'idrante_sotto_suolo' => $this->nuovoPresidio['idrante_sotto_suolo'] ?? false,
        'porta_tipo_id' => $portaTipoId ?: null,
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
    $this->intervento->load('presidiIntervento.presidio.tipoEstintore.colore', 'presidiIntervento.presidio.idranteTipoRef', 'presidiIntervento.presidio.portaTipoRef');
    
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
            'nuova_marca_serbatoio' => $presidio->marca_serbatoio,
            'nuova_data_ultima_revisione' => $presidio->data_ultima_revisione,
            'nuovo_idrante_tipo_id' => $presidio->idrante_tipo_id,
            'nuovo_idrante_lunghezza' => $presidio->idrante_lunghezza,
            'nuovo_idrante_sopra_suolo' => $presidio->idrante_sopra_suolo ?? false,
            'nuovo_idrante_sotto_suolo' => $presidio->idrante_sotto_suolo ?? false,
            'nuovo_porta_tipo_id' => $presidio->porta_tipo_id,
            'usa_ritiro' => false,
            'tipo_estintore_sigla' => optional($presidio->tipoEstintore)->sigla ?? '-',
            'deve_ritirare' => $this->verificaRitiroObbligato($presidio),
        ];

    $this->formNuovoVisibile = false;
    $this->messaggioSuccesso = 'Nuovo presidio aggiunto correttamente.';
    }


    
    public function mount(Intervento $intervento)
    {
        $this->intervento = $intervento->load('cliente', 'sede', 'tecnici', 'presidiIntervento.presidio.tipoEstintore.colore', 'presidiIntervento.presidio.idranteTipoRef', 'presidiIntervento.presidio.portaTipoRef');
        $this->durataEffettiva = $this->intervento->durata_effettiva;
        $this->marcaSuggestions = $this->caricaMarcheSuggerite();
        $this->tipiIdranti = TipoPresidio::where('categoria', 'Idrante')->orderBy('nome')->pluck('nome', 'id')->all();
        $this->tipiPorte = TipoPresidio::where('categoria', 'Porta')->orderBy('nome')->pluck('nome', 'id')->all();

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
            $this->intervento->load('presidiIntervento.presidio.tipoEstintore.colore', 'presidiIntervento.presidio.idranteTipoRef', 'presidiIntervento.presidio.portaTipoRef');
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
                'nuova_marca_serbatoio' => $presidio->marca_serbatoio,
                'nuova_data_ultima_revisione' => $presidio->data_ultima_revisione,
                'nuovo_idrante_tipo_id' => $presidio->idrante_tipo_id,
                'nuovo_idrante_lunghezza' => $presidio->idrante_lunghezza,
                'nuovo_idrante_sopra_suolo' => $presidio->idrante_sopra_suolo ?? false,
                'nuovo_idrante_sotto_suolo' => $presidio->idrante_sotto_suolo ?? false,
                'nuovo_porta_tipo_id' => $presidio->porta_tipo_id,
                'usa_ritiro' => $pi->usa_ritiro ?? false,
                'tipo_estintore_sigla' => optional($presidio->tipoEstintore)->sigla ?? '-',
                'deve_ritirare' => $deveEssereRitirato,
            ];
        }
    }

    public function avviaIntervento(): void
    {
        $userId = auth()->id();
        if (!$userId) {
            return;
        }

        InterventoTecnico::where('intervento_id', $this->intervento->id)
            ->where('user_id', $userId)
            ->update([
                'started_at' => now(),
            ]);

        $this->intervento->load('tecnici');
    }

    public function terminaIntervento(): void
    {
        $userId = auth()->id();
        if (!$userId) {
            return;
        }

        InterventoTecnico::where('intervento_id', $this->intervento->id)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now(),
            ]);

        $this->intervento->load('tecnici');
    }

    public function toggleEditPresidio(int $piId): void
    {
        $isOpen = (bool) ($this->editMode[$piId] ?? false);
        if ($isOpen) {
            $this->editMode[$piId] = false;
            return;
        }

        $pi = $this->intervento->presidiIntervento->firstWhere('id', $piId) ?? PresidioIntervento::find($piId);
        if (!$pi || !$pi->presidio) {
            return;
        }

        $p = $pi->presidio;
        $this->editPresidio[$piId] = [
            'progressivo' => $p->progressivo,
            'ubicazione' => $p->ubicazione,
            'tipo_estintore_id' => $p->tipo_estintore_id,
            'data_serbatoio' => $p->data_serbatoio ? \Carbon\Carbon::parse($p->data_serbatoio)->format('Y-m-d') : null,
            'data_ultima_revisione' => $p->data_ultima_revisione ? \Carbon\Carbon::parse($p->data_ultima_revisione)->format('Y-m-d') : null,
            'idrante_tipo_id' => $p->idrante_tipo_id,
            'porta_tipo_id' => $p->porta_tipo_id,
        ];

        $this->editMode[$piId] = true;
    }

    public function salvaModificaPresidio(int $piId): void
    {
        $pi = $this->intervento->presidiIntervento->firstWhere('id', $piId) ?? PresidioIntervento::find($piId);
        if (!$pi || !$pi->presidio) {
            return;
        }
        $p = $pi->presidio;
        $data = $this->editPresidio[$piId] ?? [];

        $progressivo = trim((string) ($data['progressivo'] ?? ''));
        if ($progressivo === '') {
            $this->messaggioErrore = 'Progressivo obbligatorio.';
            return;
        }

        $dup = Presidio::where('cliente_id', $p->cliente_id)
            ->where('sede_id', $p->sede_id)
            ->where('categoria', $p->categoria)
            ->where('progressivo', $progressivo)
            ->where('attivo', true)
            ->where('id', '!=', $p->id)
            ->exists();

        if ($dup) {
            $this->messaggioErrore = 'Esiste già un presidio attivo con questo progressivo.';
            return;
        }

        if (($p->categoria ?? 'Estintore') === 'Estintore') {
            if (empty($data['tipo_estintore_id']) || empty($data['data_serbatoio'])) {
                $this->messaggioErrore = 'Tipo estintore e data serbatoio sono obbligatori.';
                return;
            }
        }

        $p->progressivo = $progressivo;
        $p->ubicazione = $data['ubicazione'] ?? $p->ubicazione;

        if (($p->categoria ?? 'Estintore') === 'Estintore') {
            $p->tipo_estintore_id = $data['tipo_estintore_id'] ?? null;
            $p->data_serbatoio = $data['data_serbatoio'] ?? null;
            $p->data_ultima_revisione = $data['data_ultima_revisione'] ?? null;
            $p->calcolaScadenze();
        } elseif ($p->categoria === 'Idrante') {
            $p->idrante_tipo_id = $data['idrante_tipo_id'] ?? null;
        } elseif ($p->categoria === 'Porta') {
            $p->porta_tipo_id = $data['porta_tipo_id'] ?? null;
        }

        $p->save();

        $this->editMode[$piId] = false;
        $this->messaggioSuccesso = 'Presidio aggiornato.';
        $this->intervento->load('presidiIntervento.presidio.tipoEstintore.colore', 'presidiIntervento.presidio.idranteTipoRef', 'presidiIntervento.presidio.portaTipoRef');

        if (isset($this->input[$piId])) {
            $this->input[$piId]['ubicazione'] = $p->ubicazione;
            $this->input[$piId]['tipo_estintore_sigla'] = optional($p->tipoEstintore)->sigla ?? '-';
        }
    }

    public function salvaEsito(int $piId): void
    {
        $pi = $this->intervento->presidiIntervento->firstWhere('id', $piId) ?? PresidioIntervento::find($piId);
        if (!$pi) {
            return;
        }
        $pi->esito = $this->input[$piId]['esito'] ?? $pi->esito;
        $pi->save();
    }

    public function updated($name, $value): void
    {
        if (is_string($name) && str_starts_with($name, 'input.')) {
            $this->handleInputUpdate($value, $name);
        }
    }

    private function handleInputUpdate($value, $name): void
    {
        $segments = explode('.', (string) $name);
        if (empty($segments)) {
            return;
        }

        if ($segments[0] === 'input') {
            array_shift($segments);
        }

        if (count($segments) < 2) {
            return;
        }

        $piId = (int) $segments[0];
        $field = $segments[1] ?? null;
        if (!$piId || !$field) {
            return;
        }

        $pi = $this->intervento->presidiIntervento->firstWhere('id', $piId) ?? PresidioIntervento::find($piId);
        if (!$pi) {
            return;
        }

        if ($field === 'ubicazione') {
            $pi->presidio?->update(['ubicazione' => $this->input[$piId]['ubicazione'] ?? $value]);
            return;
        }

        if (in_array($field, ['esito', 'note', 'anomalie', 'usa_ritiro'], true)) {
            $payload = $value;
            if ($field === 'anomalie') {
                $payload = $this->input[$piId]['anomalie'] ?? [];
            }
            $pi->{$field} = $payload;
            $pi->save();
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
    $this->intervento->load('presidiIntervento.presidio.tipoEstintore.colore', 'presidiIntervento.presidio.idranteTipoRef', 'presidiIntervento.presidio.portaTipoRef');
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
            InterventoTecnico::where('intervento_id', $this->intervento->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);
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
        $cat = $vecchio->categoria ?? 'Estintore';

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

        if ($cat === 'Estintore' && ($dati['usa_ritiro'] ?? false)) {
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
            'tipo_estintore_id' => $cat === 'Estintore' ? ($dati['nuovo_tipo_estintore_id'] ?? null) : null,
            'data_serbatoio' => $cat === 'Estintore' ? ($dati['nuova_data_serbatoio'] ?? null) : null,
            'marca_serbatoio' => $cat === 'Estintore' ? $this->normalizeMarca($dati['nuova_marca_serbatoio'] ?? $vecchio->marca_serbatoio) : null,
            'data_ultima_revisione' => $cat === 'Estintore' ? ($dati['nuova_data_ultima_revisione'] ?? null) : null,
            'idrante_tipo_id' => $cat === 'Idrante' ? ($dati['nuovo_idrante_tipo_id'] ?? $vecchio->idrante_tipo_id) : null,
            'idrante_lunghezza' => $cat === 'Idrante' ? ($dati['nuovo_idrante_lunghezza'] ?? $vecchio->idrante_lunghezza) : null,
            'idrante_sopra_suolo' => $cat === 'Idrante' ? ($dati['nuovo_idrante_sopra_suolo'] ?? $vecchio->idrante_sopra_suolo) : false,
            'idrante_sotto_suolo' => $cat === 'Idrante' ? ($dati['nuovo_idrante_sotto_suolo'] ?? $vecchio->idrante_sotto_suolo) : false,
            'porta_tipo_id' => $cat === 'Porta' ? ($dati['nuovo_porta_tipo_id'] ?? $vecchio->porta_tipo_id) : null,
            'mesi_visita' => $vecchio->mesi_visita,
        ]);

        $nuovo->save();
        $nuovo->load('tipoEstintore');
        $nuovo->calcolaScadenze();
        $nuovo->save();

        $statoRitiro = $dati['stato_presidio_ritirato'] ?? null;
        if ($cat === 'Estintore' && $statoRitiro !== 'Rottamato') {
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
            $cat = $vecchio->categoria ?? 'Estintore';

            if ($pi->sostituito_con_presidio_id) {
                $this->messaggioErrore = "Il presidio #{$vecchio->id} è già stato sostituito.";
                return;
            }

            if ($cat === 'Estintore' && $pi->usa_ritiro) {
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
                'tipo_estintore_id' => $cat === 'Estintore' ? ($dati['nuovo_tipo_estintore_id'] ?? null) : null,
            'data_serbatoio' => $cat === 'Estintore' ? ($dati['nuova_data_serbatoio'] ?? null) : null,
            'marca_serbatoio' => $cat === 'Estintore' ? $this->normalizeMarca($dati['nuova_marca_serbatoio'] ?? $vecchio->marca_serbatoio) : null,
            'data_ultima_revisione' => $cat === 'Estintore' ? ($dati['nuova_data_ultima_revisione'] ?? null) : null,
            'idrante_tipo_id' => $cat === 'Idrante' ? ($dati['nuovo_idrante_tipo_id'] ?? $vecchio->idrante_tipo_id) : null,
            'idrante_lunghezza' => $cat === 'Idrante' ? ($dati['nuovo_idrante_lunghezza'] ?? $vecchio->idrante_lunghezza) : null,
            'idrante_sopra_suolo' => $cat === 'Idrante' ? ($dati['nuovo_idrante_sopra_suolo'] ?? $vecchio->idrante_sopra_suolo) : false,
            'idrante_sotto_suolo' => $cat === 'Idrante' ? ($dati['nuovo_idrante_sotto_suolo'] ?? $vecchio->idrante_sotto_suolo) : false,
            'porta_tipo_id' => $cat === 'Porta' ? ($dati['nuovo_porta_tipo_id'] ?? $vecchio->porta_tipo_id) : null,
            'mesi_visita' => $vecchio->mesi_visita,
        ]);

            $nuovo->save();
            $nuovo->load('tipoEstintore');
            $nuovo->calcolaScadenze();
            $nuovo->save();

            $statoRitiro = $dati['stato_presidio_ritirato'] ?? null;
            if ($cat === 'Estintore' && $statoRitiro !== 'Rottamato') {
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

    public function setMarcaMbSostituzione(int $id): void
    {
        if (!isset($this->input[$id])) {
            return;
        }
        $this->input[$id]['nuova_marca_serbatoio'] = 'MB';
        $this->aggiornaPreviewSostituzione($id);
    }

    public function setMarcaMbNuovo(): void
    {
        $this->nuovoPresidio['marca_serbatoio'] = 'MB';
        $this->aggiornaPreviewNuovo();
    }

    public function aggiornaPreviewSostituzione(int $id): void
    {
        $dati = $this->input[$id] ?? [];
        $this->previewSostituzione[$id] = $this->calcolaPreviewScadenze(
            $dati['nuovo_tipo_estintore_id'] ?? null,
            $dati['nuova_data_serbatoio'] ?? null,
            $dati['nuova_data_ultima_revisione'] ?? null,
            $dati['nuova_marca_serbatoio'] ?? null
        );
    }

    public function aggiornaPreviewNuovo(): void
    {
        $dati = $this->nuovoPresidio ?? [];
        $this->previewNuovo = $this->calcolaPreviewScadenze(
            $dati['tipo_estintore_id'] ?? null,
            $dati['data_serbatoio'] ?? null,
            $dati['data_ultima_revisione'] ?? null,
            $dati['marca_serbatoio'] ?? null
        );
    }

    private function calcolaPreviewScadenze($tipoId, $dataSerb, $ultimaRev, $marca): ?array
    {
        if (!$tipoId || (!$dataSerb && !$ultimaRev)) {
            return null;
        }

        $tipo = TipoEstintore::with('classificazione')->find($tipoId);
        if (!$tipo) {
            return null;
        }

        $tmp = new Presidio();
        $tmp->tipo_estintore_id = $tipoId;
        $tmp->data_serbatoio = $dataSerb;
        $tmp->data_ultima_revisione = $ultimaRev;
        $tmp->marca_serbatoio = $this->normalizeMarca($marca);

        $tmp->setRelation('tipoEstintore', $tipo);
        $tmp->setRelation('cliente', $this->intervento->cliente ?? new \App\Models\Cliente());
        $tmp->setRelation('sede', $this->intervento->sede ?? new \App\Models\Sede());

        $tmp->calcolaScadenze();

        return [
            'revisione' => $tmp->data_revisione,
            'collaudo' => $tmp->data_collaudo,
            'fine_vita' => $tmp->data_fine_vita,
            'sostituzione' => $tmp->data_sostituzione,
        ];
    }

    private function caricaMarcheSuggerite(): array
    {
        $marche = Presidio::query()
            ->whereNotNull('marca_serbatoio')
            ->pluck('marca_serbatoio')
            ->map(fn ($m) => $this->normalizeMarca($m))
            ->filter()
            ->unique()
            ->values();

        if (!$marche->contains('MB')) {
            $marche->prepend('MB');
        } else {
            $marche = collect(['MB'])->merge($marche->reject(fn ($m) => $m === 'MB'));
        }

        return $marche->values()->all();
    }

    private function normalizeMarca(?string $marca): ?string
    {
        $marca = trim((string) $marca);
        return $marca === '' ? null : mb_strtoupper($marca);
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
