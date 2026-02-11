<?php

namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\Intervento;
use App\Models\Presidio;
use App\Models\PresidioIntervento;
use App\Models\PresidioInterventoAnomalia;
use App\Models\Anomalia;
use App\Models\TipoEstintore;
use App\Models\InterventoTecnico;
use App\Models\TipoPresidio;
use App\Services\Interventi\OrdinePreventivoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

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
    public bool $showControlloAnnualeIdranti = false;
    public array $ordinePreventivo = [
        'found' => false,
        'error' => null,
        'header' => null,
        'rows' => [],
    ];

    #[On('firmaClienteAcquisita')]
    public function salvaFirmaCliente($data)
    {
        $this->intervento->update([
            'firma_cliente_base64' => $data['base64']
        ]);

        $this->messaggioSuccesso = "Firma salvata con successo.";
    }

    private function interventoRelations(): array
    {
        $rels = [
            'presidiIntervento.presidio.tipoEstintore.colore',
            'presidiIntervento.presidio.idranteTipoRef',
            'presidiIntervento.presidio.portaTipoRef',
        ];

        if (Schema::hasTable('presidio_intervento_anomalie')) {
            $rels[] = 'presidiIntervento.anomalieItems.anomalia';
        }

        return $rels;
    }

    

public function apriFormNuovoPresidio()
{
    $this->formNuovoVisibile = true;

    $this->nuovoPresidio = [
        'ubicazione' => '',
        'tipo_estintore_id' => null,
        'data_serbatoio' => null,
        'marca_serbatoio' => null,
        'marca_mb' => false,
        'data_ultima_revisione' => null,
        'categoria' => 'Estintore',
        'idrante_tipo_id' => null,
        'idrante_lunghezza' => null,
        'idrante_sopra_suolo' => false,
        'idrante_sotto_suolo' => false,
        'porta_tipo_id' => null,
        'note' => '',
        'usa_ritiro' => false,
    ];
    $this->previewNuovo = [];
    
}

public function updatedNuovoPresidioCategoria($categoria): void
{
    $categoria = (string) $categoria;
    if ($categoria !== 'Estintore') {
        $this->nuovoPresidio['tipo_estintore_id'] = null;
        $this->nuovoPresidio['data_serbatoio'] = null;
        $this->nuovoPresidio['data_ultima_revisione'] = null;
        $this->nuovoPresidio['marca_serbatoio'] = null;
        $this->nuovoPresidio['marca_mb'] = false;
        $this->nuovoPresidio['usa_ritiro'] = false;
    }
    if ($categoria !== 'Idrante') {
        $this->nuovoPresidio['idrante_tipo_id'] = null;
        $this->nuovoPresidio['idrante_lunghezza'] = null;
        $this->nuovoPresidio['idrante_sopra_suolo'] = false;
        $this->nuovoPresidio['idrante_sotto_suolo'] = false;
    }
    if ($categoria !== 'Porta') {
        $this->nuovoPresidio['porta_tipo_id'] = null;
    }

    if ($categoria !== 'Estintore') {
        $this->previewNuovo = [];
    } else {
        $this->aggiornaPreviewNuovo();
    }
}

public function updatedNuovoPresidioMarcaMb($checked): void
{
    $enabled = filter_var($checked, FILTER_VALIDATE_BOOL);
    if ($enabled) {
        $this->nuovoPresidio['marca_serbatoio'] = 'MB';
    } elseif ($this->normalizeMarca($this->nuovoPresidio['marca_serbatoio'] ?? null) === 'MB') {
        $this->nuovoPresidio['marca_serbatoio'] = null;
    }
    $this->aggiornaPreviewNuovo();
}

public function updatedNuovoPresidioMarcaSerbatoio($value): void
{
    $this->nuovoPresidio['marca_mb'] = $this->normalizeMarca($value) === 'MB';
    $this->aggiornaPreviewNuovo();
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
    
        if (!$giacenza || (int) $giacenza->quantita < 1) {
            $this->messaggioErrore = 'Nessuna giacenza disponibile per questo tipo di presidio.';
            return;
        }
    
        $giacenza->decrement('quantita');
    }
    $progressivo = Presidio::prossimoProgressivo($clienteId, $sedeId, $categoria);

    $tipoEstintoreId = $categoria === 'Estintore' ? $this->nuovoPresidio['tipo_estintore_id'] : null;
    $dataSerbatoio = $categoria === 'Estintore' ? $this->nuovoPresidio['data_serbatoio'] : null;
    $marcaInput = ($this->nuovoPresidio['marca_mb'] ?? false) ? 'MB' : ($this->nuovoPresidio['marca_serbatoio'] ?? null);
    $marcaSerbatoio = $categoria === 'Estintore' ? $this->normalizeMarca($marcaInput) : null;
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
    $this->intervento->load(...$this->interventoRelations());
    
    // Inizializzazione sicura input
        $this->input[$pi->id] = [
            'ubicazione' => $presidio->ubicazione,
            'note' => null,
            'esito' => 'non_verificato',
            'anomalie' => [],
            'anomalie_riparate' => [],
            'sostituito_con' => 0,
            'sostituzione' => false,
            'nuovo_tipo_estintore_id' => null,
            'nuova_data_serbatoio' => null,
            'nuova_marca_serbatoio' => $presidio->marca_serbatoio,
            'nuova_marca_mb' => $this->normalizeMarca($presidio->marca_serbatoio) === 'MB',
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
        $this->intervento = $intervento->load(
            'cliente',
            'sede',
            'tecnici',
            ...$this->interventoRelations()
        );
        $this->durataEffettiva = $this->intervento->durata_effettiva;
        $this->showControlloAnnualeIdranti = $this->isMeseMinutaggioPiuAlto();
        $this->marcaSuggestions = $this->caricaMarcheSuggerite();
        $this->tipiIdranti = TipoPresidio::whereRaw('LOWER(categoria) = ?', ['idrante'])
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
        $this->tipiPorte = TipoPresidio::whereRaw('LOWER(categoria) = ?', ['porta'])
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();

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
            $this->intervento->load(...$this->interventoRelations());
        }
    
        // Inizializzazione input per ogni presidio_intervento
        foreach ($this->intervento->presidiIntervento as $pi) {
            $presidio = $pi->presidio;
            [$anomalieIds, $anomalieRiparate] = $this->extractAnomalieState($pi);
        
            $deveEssereRitirato = $this->verificaRitiroObbligato($presidio);
        
            $this->input[$pi->id] = [
                'ubicazione' => $presidio->ubicazione,
                'note' => $pi->note,
                'esito' => $pi->esito ?? 'non_verificato',
                'anomalie' => $anomalieIds,
                'anomalie_riparate' => $anomalieRiparate,
                'sostituito_con' => Presidio::find($pi->sostituito_con_presidio_id) ?? 0,
                'sostituzione' => false,
                'nuovo_tipo_estintore_id' => null,
                'nuova_data_serbatoio' => null,
                'nuova_marca_serbatoio' => $presidio->marca_serbatoio,
                'nuova_marca_mb' => $this->normalizeMarca($presidio->marca_serbatoio) === 'MB',
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

        $this->caricaOrdinePreventivo();
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
        $this->intervento->load(...$this->interventoRelations());

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

        if ($field === 'nuova_marca_mb') {
            $enabled = filter_var($value, FILTER_VALIDATE_BOOL);
            if ($enabled) {
                $this->input[$piId]['nuova_marca_serbatoio'] = 'MB';
            } elseif ($this->normalizeMarca($this->input[$piId]['nuova_marca_serbatoio'] ?? null) === 'MB') {
                $this->input[$piId]['nuova_marca_serbatoio'] = null;
            }
            $this->aggiornaPreviewSostituzione($piId);
            return;
        }

        if ($field === 'nuova_marca_serbatoio') {
            $this->input[$piId]['nuova_marca_mb'] = $this->normalizeMarca($value) === 'MB';
            $this->aggiornaPreviewSostituzione($piId);
            return;
        }

        if ($field === 'ubicazione') {
            $pi->presidio?->update(['ubicazione' => $this->input[$piId]['ubicazione'] ?? $value]);
            return;
        }

        if ($field === 'anomalie') {
            $selected = $this->normalizeAnomalieIds($this->input[$piId]['anomalie'] ?? []);
            $riparate = $this->normalizeAnomalieRiparate($selected, $this->input[$piId]['anomalie_riparate'] ?? []);
            $this->input[$piId]['anomalie'] = $selected;
            $this->input[$piId]['anomalie_riparate'] = $riparate;
            $this->syncAnomaliePresidioIntervento($pi, $selected, $riparate);
            return;
        }

        if ($field === 'anomalie_riparate') {
            $selected = $this->normalizeAnomalieIds($this->input[$piId]['anomalie'] ?? []);
            $riparate = $this->normalizeAnomalieRiparate($selected, $this->input[$piId]['anomalie_riparate'] ?? []);
            $this->input[$piId]['anomalie_riparate'] = $riparate;
            $this->syncAnomaliePresidioIntervento($pi, $selected, $riparate);
            return;
        }

        if (in_array($field, ['esito', 'note', 'usa_ritiro'], true)) {
            $pi->{$field} = $value;
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
    $this->intervento->load(...$this->interventoRelations());
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
            $this->messaggioSuccesso ='Intervento evaso correttamente. Apertura rapportino in corso...';
            $this->dispatch(
                'intervento-completato',
                pdfUrl: route('rapportino.pdf', $this->intervento->id),
                redirectUrl: route('interventi.evadi')
            );
            return;
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

        $nuovaMarca = ($dati['nuova_marca_mb'] ?? false) ? 'MB' : ($dati['nuova_marca_serbatoio'] ?? $vecchio->marca_serbatoio);

        $nuovo = new Presidio([
            'cliente_id' => $vecchio->cliente_id,
            'sede_id' => $vecchio->sede_id,
            'categoria' => $vecchio->categoria,
            'progressivo' => $vecchio->progressivo,
            'ubicazione' => $vecchio->ubicazione,
            'tipo_estintore_id' => $cat === 'Estintore' ? ($dati['nuovo_tipo_estintore_id'] ?? null) : null,
            'data_serbatoio' => $cat === 'Estintore' ? ($dati['nuova_data_serbatoio'] ?? null) : null,
            'marca_serbatoio' => $cat === 'Estintore' ? $this->normalizeMarca($nuovaMarca) : null,
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

        $anomalieIds = $this->normalizeAnomalieIds($dati['anomalie'] ?? []);
        $anomalieRiparate = $this->normalizeAnomalieRiparate($anomalieIds, $dati['anomalie_riparate'] ?? []);

        $pi->note = $dati['note'];
        $pi->esito = $dati['esito'];
        $pi->anomalie = $anomalieIds;
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

            $nuovaMarca = ($dati['nuova_marca_mb'] ?? false) ? 'MB' : ($dati['nuova_marca_serbatoio'] ?? $vecchio->marca_serbatoio);

            $nuovo = new Presidio([
                'cliente_id' => $vecchio->cliente_id,
                'sede_id' => $vecchio->sede_id,
                'categoria' => $vecchio->categoria,
                'progressivo' => $vecchio->progressivo,
                'ubicazione' => $vecchio->ubicazione,
                'tipo_estintore_id' => $cat === 'Estintore' ? ($dati['nuovo_tipo_estintore_id'] ?? null) : null,
            'data_serbatoio' => $cat === 'Estintore' ? ($dati['nuova_data_serbatoio'] ?? null) : null,
            'marca_serbatoio' => $cat === 'Estintore' ? $this->normalizeMarca($nuovaMarca) : null,
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
        $this->syncAnomaliePresidioIntervento($pi, $anomalieIds, $anomalieRiparate);
        $this->messaggioSuccesso = 'Presidio aggiornato correttamente.';
    }

    public function setMarcaMbSostituzione(int $id): void
    {
        if (!isset($this->input[$id])) {
            return;
        }
        $this->input[$id]['nuova_marca_serbatoio'] = 'MB';
        $this->input[$id]['nuova_marca_mb'] = true;
        $this->aggiornaPreviewSostituzione($id);
    }

    public function setMarcaMbNuovo(): void
    {
        $this->nuovoPresidio['marca_serbatoio'] = 'MB';
        $this->nuovoPresidio['marca_mb'] = true;
        $this->aggiornaPreviewNuovo();
    }

    public function aggiornaPreviewSostituzione(int $id): void
    {
        $dati = $this->input[$id] ?? [];
        $marca = ($dati['nuova_marca_mb'] ?? false) ? 'MB' : ($dati['nuova_marca_serbatoio'] ?? null);
        $this->previewSostituzione[$id] = $this->calcolaPreviewScadenze(
            $dati['nuovo_tipo_estintore_id'] ?? null,
            $dati['nuova_data_serbatoio'] ?? null,
            $dati['nuova_data_ultima_revisione'] ?? null,
            $marca
        );
    }

    public function aggiornaPreviewNuovo(): void
    {
        $dati = $this->nuovoPresidio ?? [];
        $marca = ($dati['marca_mb'] ?? false) ? 'MB' : ($dati['marca_serbatoio'] ?? null);
        $this->previewNuovo = $this->calcolaPreviewScadenze(
            $dati['tipo_estintore_id'] ?? null,
            $dati['data_serbatoio'] ?? null,
            $dati['data_ultima_revisione'] ?? null,
            $marca
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

    private function isMeseMinutaggioPiuAlto(): bool
    {
        if (!$this->intervento?->data_intervento) {
            return false;
        }

        $meseIntervento = (int) Carbon::parse($this->intervento->data_intervento)->month;
        $sede = $this->intervento->sede;

        if ($sede && $this->hasMesePiuAlto($sede, $meseIntervento)) {
            return true;
        }

        $cliente = $this->intervento->cliente;
        if ($cliente && $this->hasMesePiuAlto($cliente, $meseIntervento)) {
            return true;
        }

        return false;
    }

    private function hasMesePiuAlto($entity, int $meseIntervento): bool
    {
        $mesiVisita = $this->normalizzaMesiVisita($entity->mesi_visita ?? []);
        if (count($mesiVisita) < 2 || !in_array($meseIntervento, $mesiVisita, true)) {
            return false;
        }

        $corrente = (int) ($entity->minutiPerMese($meseIntervento) ?? 0);
        $altroMese = collect($mesiVisita)->first(fn ($m) => (int) $m !== $meseIntervento);
        $altro = (int) ($entity->minutiPerMese((int) $altroMese) ?? 0);

        return $corrente > $altro;
    }

    private function normalizzaMesiVisita($raw): array
    {
        $map = ['gen'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'mag'=>5,'giu'=>6,'lug'=>7,'ago'=>8,'set'=>9,'ott'=>10,'nov'=>11,'dic'=>12];
        $out = [];

        if (is_array($raw) && array_values($raw) === $raw) {
            foreach ($raw as $v) {
                $out[] = is_numeric($v) ? (int) $v : ($map[mb_strtolower((string) $v)] ?? null);
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $k => $v) {
                if (!$v) continue;
                $out[] = is_numeric($k) ? (int) $k : ($map[mb_strtolower((string) $k)] ?? null);
            }
        }

        $out = array_values(array_filter(array_unique($out), fn ($m) => $m >= 1 && $m <= 12));
        sort($out);
        return array_slice($out, 0, 2);
    }

    public function ricaricaOrdinePreventivo(): void
    {
        $this->caricaOrdinePreventivo();
        if ($this->ordinePreventivo['found']) {
            $this->messaggioSuccesso = 'Ordine preventivo aggiornato da Business.';
        } else {
            $this->messaggioErrore = $this->ordinePreventivo['error'] ?? 'Ordine preventivo non trovato.';
        }
    }

    public function getRiepilogoOrdineProperty(): array
    {
        $svc = $this->ordineService();
        $righeIntervento = $svc->buildRigheIntervento($this->intervento->presidiIntervento);
        $confronto = $svc->buildConfronto(
            $this->ordinePreventivo['rows'] ?? [],
            $righeIntervento['rows'] ?? []
        );
        $anomalie = $svc->buildAnomalieSummaryFromInput($this->input, $this->anomalyLabelMap());

        return [
            'righe_intervento' => $righeIntervento['rows'] ?? [],
            'presidi_senza_codice' => $righeIntervento['missing_mapping'] ?? [],
            'confronto' => $confronto,
            'anomalie' => $anomalie,
        ];
    }

    private function caricaOrdinePreventivo(): void
    {
        $codiceEsterno = (string) ($this->intervento->cliente?->codice_esterno ?? '');
        $this->ordinePreventivo = $this->ordineService()->caricaOrdineApertoPerCliente($codiceEsterno);
    }

    private function ordineService(): OrdinePreventivoService
    {
        return app(OrdinePreventivoService::class);
    }

    private function anomalyLabelMap(): array
    {
        return collect($this->anomalie)
            ->flatten(1)
            ->pluck('etichetta', 'id')
            ->toArray();
    }

    private function extractAnomalieState(PresidioIntervento $pi): array
    {
        if (Schema::hasTable('presidio_intervento_anomalie')) {
            $items = $pi->relationLoaded('anomalieItems')
                ? $pi->anomalieItems
                : $pi->anomalieItems()->get(['anomalia_id', 'riparata']);

            if ($items->isNotEmpty()) {
                $ids = $items->pluck('anomalia_id')
                    ->filter(fn ($id) => is_numeric($id))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $riparate = $items->mapWithKeys(function ($row) {
                    return [(int) $row->anomalia_id => (bool) $row->riparata];
                })->all();

                return [$ids, $riparate];
            }
        }

        $ids = $this->normalizeAnomalieIds($pi->getRawOriginal('anomalie'));
        $riparate = [];
        foreach ($ids as $id) {
            $riparate[$id] = false;
        }

        return [$ids, $riparate];
    }

    private function normalizeAnomalieIds($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeAnomalieRiparate(array $selectedIds, $raw): array
    {
        $selectedMap = array_fill_keys($selectedIds, true);
        $raw = is_array($raw) ? $raw : [];

        $out = [];
        foreach ($selectedIds as $id) {
            $out[$id] = filter_var($raw[$id] ?? false, FILTER_VALIDATE_BOOL);
        }

        // Rimuove eventuali voci fuori selezione
        foreach (array_keys($raw) as $id) {
            $idInt = (int) $id;
            if (!isset($selectedMap[$idInt])) {
                unset($out[$idInt]);
            }
        }

        return $out;
    }

    private function syncAnomaliePresidioIntervento(PresidioIntervento $pi, array $selectedIds, array $riparateMap): void
    {
        $selectedIds = $this->normalizeAnomalieIds($selectedIds);
        $riparateMap = $this->normalizeAnomalieRiparate($selectedIds, $riparateMap);

        if (Schema::hasTable('presidio_intervento_anomalie')) {
            $existing = $pi->anomalieItems()->pluck('id', 'anomalia_id');
            $toDelete = $existing->filter(fn ($id, $anomaliaId) => !in_array((int) $anomaliaId, $selectedIds, true))->values();

            if ($toDelete->isNotEmpty()) {
                PresidioInterventoAnomalia::whereIn('id', $toDelete)->delete();
            }

            foreach ($selectedIds as $anomaliaId) {
                $pi->anomalieItems()->updateOrCreate(
                    ['anomalia_id' => $anomaliaId],
                    ['riparata' => (bool) ($riparateMap[$anomaliaId] ?? false)]
                );
            }
        }

        $pi->anomalie = $selectedIds;
        $pi->save();
    }




    

    public function render()
    {
        return view('livewire.interventi.evadi-intervento-singolo', [
            'interventoCompletabile' => $this->interventoCompletabile,
            'anomalie' => $this->anomalie,
            'tipiEstintori' => $this->tipiEstintori,
            'ordinePreventivo' => $this->ordinePreventivo,
            'riepilogoOrdine' => $this->riepilogoOrdine,
        ])->layout('layouts.app');
    }
}
