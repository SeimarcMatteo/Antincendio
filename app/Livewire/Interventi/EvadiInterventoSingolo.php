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
use App\Models\InterventoTecnicoSessione;
use App\Models\MailQueueItem;
use App\Jobs\ProcessMailQueueItemJob;
use App\Models\TipoPresidio;
use App\Services\Clienti\BusinessFormaPagamentoService;
use App\Services\Interventi\OrdinePreventivoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

use Livewire\Attributes\On;

class EvadiInterventoSingolo extends Component
{
    private ?array $anomalyMapCache = null;

    public Intervento $intervento;
    public $input = [];
    public $vistaSchede = true;
    public $durataEffettiva;
    public bool $richiedePagamentoManutentore = false;
    public ?string $formaPagamentoDescrizione = null;
    public ?string $pagamentoMetodo = null;
    public $pagamentoImporto = null;
    public ?string $noteInterventoGenerali = null;
    public ?string $noteClienteAnagrafica = null;
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
    public bool $timerSessioniEnabled = false;
    public array $timerSessioni = [];
    public array $timerSessioniForm = [];
    public bool $timerAttivo = false;
    public int $timerTotaleMinuti = 0;
    public bool $timerDisponibilePerUtente = false;
    public array $ordinePreventivo = [
        'found' => false,
        'error' => null,
        'header' => null,
        'rows' => [],
    ];
    public array $prezziExtraManuali = [];

    #[On('firmaClienteAcquisita')]
    public function salvaFirmaCliente($payload = null): void
    {
        $base64 = null;
        if (is_string($payload)) {
            $base64 = $payload;
        } elseif (is_array($payload)) {
            $base64 = $payload['base64'] ?? ($payload['data']['base64'] ?? null);
        }

        $base64 = is_string($base64) ? trim($base64) : null;
        if (!$base64 || !str_starts_with($base64, 'data:image/')) {
            $this->messaggioErrore = 'Firma non valida. Riprova.';
            return;
        }

        $this->intervento->update([
            'firma_cliente_base64' => $base64
        ]);

        $this->messaggioSuccesso = 'Firma salvata con successo.';
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

public function updatedNuovoPresidioMarcaSerbatoio($value): void
{
    $this->nuovoPresidio['marca_serbatoio'] = $this->normalizeMarca((string) $value);
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
        $this->timerSessioniEnabled = Schema::hasTable('intervento_tecnico_sessioni');

        $this->intervento = $intervento->load(
            'cliente',
            'sede',
            'tecnici',
            ...$this->interventoRelations()
        );
        $this->durataEffettiva = (int) ($this->intervento->durata_effettiva ?? 0);
        $this->refreshFormaPagamentoFromBusiness(false);
        $this->hydrateFormaPagamentoFromCliente();
        $this->pagamentoMetodo = $this->normalizePagamentoMetodo($this->intervento->pagamento_metodo);
        $this->pagamentoImporto = $this->intervento->pagamento_importo !== null
            ? number_format((float) $this->intervento->pagamento_importo, 2, '.', '')
            : null;
        $this->noteInterventoGenerali = $this->intervento->note;
        $this->noteClienteAnagrafica = $this->intervento->cliente?->note;
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
            ->attivi()
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

        $this->prezziExtraManuali = $this->loadPrezziExtraManualiFromPayload();
        $this->caricaOrdinePreventivo();
        $this->refreshTimerState();
    }

    public function avviaIntervento(): void
    {
        $it = $this->currentInterventoTecnico();
        if (!$it) {
            $this->messaggioErrore = 'Tecnico non associato a questo intervento.';
            return;
        }

        if (!$this->timerSessioniEnabled) {
            $it->update([
                'started_at' => now(),
                'ended_at' => null,
            ]);
            $this->intervento->load('tecnici');
            $this->refreshTimerState();
            return;
        }

        $active = $it->sessioni()
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first();

        if ($active) {
            $this->messaggioErrore = 'Hai già una sessione timer in corso.';
            return;
        }

        $it->sessioni()->create([
            'started_at' => now(),
            'ended_at' => null,
        ]);

        $this->sincronizzaPivotTimer($it);
        $this->intervento->load('tecnici');
        $this->refreshTimerState();
        $this->messaggioSuccesso = 'Sessione timer avviata.';
    }

    public function terminaIntervento(): void
    {
        $it = $this->currentInterventoTecnico();
        if (!$it) {
            $this->messaggioErrore = 'Tecnico non associato a questo intervento.';
            return;
        }

        if (!$this->timerSessioniEnabled) {
            $it->update(['ended_at' => now()]);
            $this->intervento->load('tecnici');
            $this->refreshTimerState();
            return;
        }

        $active = $it->sessioni()
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first();

        if (!$active) {
            $this->messaggioErrore = 'Nessuna sessione timer in corso.';
            return;
        }

        $active->ended_at = now();
        $active->save();

        $this->sincronizzaPivotTimer($it);
        $this->intervento->load('tecnici');
        $this->refreshTimerState();
        $this->messaggioSuccesso = 'Sessione timer chiusa.';
    }

    public function salvaSessioneTimer(int $sessioneId): void
    {
        if (!$this->timerSessioniEnabled) {
            return;
        }

        $it = $this->currentInterventoTecnico();
        if (!$it) {
            $this->messaggioErrore = 'Tecnico non associato a questo intervento.';
            return;
        }

        $sessione = InterventoTecnicoSessione::where('id', $sessioneId)
            ->where('intervento_tecnico_id', $it->id)
            ->first();
        if (!$sessione) {
            $this->messaggioErrore = 'Sessione timer non trovata.';
            return;
        }

        $data = $this->timerSessioniForm[$sessioneId] ?? [];
        $startRaw = trim((string) ($data['started_at'] ?? ''));
        $endRaw = trim((string) ($data['ended_at'] ?? ''));

        if ($startRaw === '') {
            $this->messaggioErrore = 'Data/ora inizio obbligatoria.';
            return;
        }

        try {
            $start = Carbon::parse($startRaw);
        } catch (\Throwable $e) {
            $this->messaggioErrore = 'Formato data/ora inizio non valido.';
            return;
        }

        $end = null;
        if ($endRaw !== '') {
            try {
                $end = Carbon::parse($endRaw);
            } catch (\Throwable $e) {
                $this->messaggioErrore = 'Formato data/ora fine non valido.';
                return;
            }
            if ($end->lt($start)) {
                $this->messaggioErrore = 'La fine non può essere precedente all\'inizio.';
                return;
            }
        }

        $sessione->started_at = $start;
        $sessione->ended_at = $end;
        $sessione->save();

        $this->sincronizzaPivotTimer($it);
        $this->intervento->load('tecnici');
        $this->refreshTimerState();
        $this->messaggioSuccesso = 'Sessione timer aggiornata.';
    }

    private function currentInterventoTecnico(): ?InterventoTecnico
    {
        $userId = auth()->id();
        if (!$userId) {
            return null;
        }

        return InterventoTecnico::where('intervento_id', $this->intervento->id)
            ->where('user_id', $userId)
            ->first();
    }

    public function associaTecnicoCorrenteTimer(): void
    {
        $userId = auth()->id();
        if (!$userId) {
            $this->messaggioErrore = 'Utente non autenticato.';
            return;
        }

        $exists = InterventoTecnico::where('intervento_id', $this->intervento->id)
            ->where('user_id', $userId)
            ->exists();

        if (!$exists) {
            $this->intervento->tecnici()->attach($userId, [
                'scheduled_start_at' => null,
                'scheduled_end_at' => null,
            ]);
        }

        $this->intervento->load('tecnici');
        $this->refreshTimerState();
        $this->messaggioSuccesso = 'Tecnico associato all\'intervento. Timer disponibile.';
    }

    private function sincronizzaPivotTimer(InterventoTecnico $it): void
    {
        if (!$this->timerSessioniEnabled) {
            return;
        }

        $sessioni = $it->sessioni()
            ->orderBy('started_at')
            ->get(['started_at', 'ended_at']);

        if ($sessioni->isEmpty()) {
            $it->started_at = null;
            $it->ended_at = null;
            $it->save();
            return;
        }

        $firstStart = $sessioni->first()?->started_at;
        $hasOpen = $sessioni->contains(fn ($s) => $s->ended_at === null);
        $lastEnd = $sessioni->whereNotNull('ended_at')->max('ended_at');

        $it->started_at = $firstStart;
        $it->ended_at = $hasOpen ? null : $lastEnd;
        $it->save();
    }

    private function refreshTimerState(): void
    {
        $this->timerSessioni = [];
        $this->timerSessioniForm = [];
        $this->timerAttivo = false;
        $this->timerTotaleMinuti = 0;
        $this->timerDisponibilePerUtente = false;

        $it = $this->currentInterventoTecnico();
        if (!$it) {
            return;
        }

        $this->timerDisponibilePerUtente = true;

        if (!$this->timerSessioniEnabled) {
            $start = $it->started_at ? Carbon::parse($it->started_at) : null;
            $end = $it->ended_at ? Carbon::parse($it->ended_at) : null;
            if ($start) {
                $min = $start->diffInMinutes($end ?: now());
                $this->timerSessioni[] = [
                    'id' => 0,
                    'started_at' => $start->format('d/m/Y H:i'),
                    'ended_at' => $end?->format('d/m/Y H:i'),
                    'minutes' => $min,
                    'is_open' => $end === null,
                ];
                $this->timerAttivo = $end === null;
                $this->timerTotaleMinuti = (int) $min;
            }
            $this->durataEffettiva = $this->calcolaDurataEffettivaTotaleIntervento();
            return;
        }

        $sessioni = $it->sessioni()
            ->orderByDesc('started_at')
            ->get();

        foreach ($sessioni as $sessione) {
            $start = Carbon::parse($sessione->started_at);
            $end = $sessione->ended_at ? Carbon::parse($sessione->ended_at) : null;
            $minutes = $start->diffInMinutes($end ?: now());

            $this->timerSessioni[] = [
                'id' => $sessione->id,
                'started_at' => $start->format('d/m/Y H:i'),
                'ended_at' => $end?->format('d/m/Y H:i'),
                'minutes' => $minutes,
                'is_open' => $end === null,
            ];

            $this->timerSessioniForm[$sessione->id] = [
                'started_at' => $start->format('Y-m-d\TH:i'),
                'ended_at' => $end ? $end->format('Y-m-d\TH:i') : null,
            ];

            $this->timerTotaleMinuti += (int) $minutes;
            if ($end === null) {
                $this->timerAttivo = true;
            }
        }

        $this->durataEffettiva = $this->calcolaDurataEffettivaTotaleIntervento();
    }

    private function calcolaDurataEffettivaTotaleIntervento(): int
    {
        $rows = InterventoTecnico::where('intervento_id', $this->intervento->id)
            ->get(['id', 'started_at', 'ended_at']);

        if ($rows->isEmpty()) {
            return (int) ($this->durataEffettiva ?? 0);
        }

        $totaleMinuti = 0;
        $hasTimerData = false;

        foreach ($rows as $row) {
            if ($this->timerSessioniEnabled) {
                $sessioni = InterventoTecnicoSessione::where('intervento_tecnico_id', $row->id)
                    ->orderBy('started_at')
                    ->get(['started_at', 'ended_at']);

                if ($sessioni->isNotEmpty()) {
                    $hasTimerData = true;
                }

                foreach ($sessioni as $sessione) {
                    if (!$sessione->started_at) {
                        continue;
                    }
                    $start = Carbon::parse($sessione->started_at);
                    $end = $sessione->ended_at ? Carbon::parse($sessione->ended_at) : now();
                    if ($end->lt($start)) {
                        continue;
                    }
                    $totaleMinuti += $start->diffInMinutes($end);
                }

                if ($sessioni->isEmpty() && $row->started_at) {
                    $hasTimerData = true;
                    $start = Carbon::parse($row->started_at);
                    $end = $row->ended_at ? Carbon::parse($row->ended_at) : now();
                    if ($end->gte($start)) {
                        $totaleMinuti += $start->diffInMinutes($end);
                    }
                }
                continue;
            }

            if (!$row->started_at) {
                continue;
            }

            $hasTimerData = true;
            $start = Carbon::parse($row->started_at);
            $end = $row->ended_at ? Carbon::parse($row->ended_at) : now();
            if ($end->lt($start)) {
                continue;
            }
            $totaleMinuti += $start->diffInMinutes($end);
        }

        if (!$hasTimerData) {
            return (int) ($this->durataEffettiva ?? 0);
        }

        return (int) $totaleMinuti;
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
            ->attivi()
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

    public function updatedPagamentoMetodo($value): void
    {
        $this->pagamentoMetodo = $this->normalizePagamentoMetodo($value);
        $this->persistPagamentoIntervento();
    }

    public function updatedPagamentoImporto($value): void
    {
        $normalized = $this->normalizePagamentoImporto($value);
        $this->pagamentoImporto = $normalized !== null ? number_format($normalized, 2, '.', '') : null;
        $this->persistPagamentoIntervento();
    }

    public function salvaNoteInterventoGenerali(): void
    {
        $this->intervento->note = $this->noteInterventoGenerali;
        $this->intervento->save();
        $this->messaggioSuccesso = 'Note intervento salvate.';
    }

    public function salvaNoteClienteAnagrafica(): void
    {
        $cliente = $this->intervento->cliente;
        if (!$cliente) {
            $this->messaggioErrore = 'Cliente non trovato.';
            return;
        }

        $cliente->note = $this->noteClienteAnagrafica;
        $cliente->save();

        $this->intervento->setRelation('cliente', $cliente->fresh());
        $this->messaggioSuccesso = 'Note anagrafica cliente salvate.';
    }

    public function toggleAnomalia(int $piId, int $anomaliaId, $checked): void
    {
        $checked = filter_var($checked, FILTER_VALIDATE_BOOL);
        if (!isset($this->input[$piId])) {
            return;
        }

        $selected = $this->normalizeAnomalieIds($this->input[$piId]['anomalie'] ?? []);
        $selectedMap = array_fill_keys($selected, true);

        if ($checked) {
            $selectedMap[$anomaliaId] = true;
        } else {
            unset($selectedMap[$anomaliaId]);
        }

        $selected = array_keys($selectedMap);
        sort($selected);

        $riparate = $this->input[$piId]['anomalie_riparate'] ?? [];
        if (!$checked) {
            unset($riparate[$anomaliaId]);
        }
        $riparate = $this->normalizeAnomalieRiparate($selected, $riparate);

        $this->input[$piId]['anomalie'] = $selected;
        $this->input[$piId]['anomalie_riparate'] = $riparate;

        $pi = $this->intervento->presidiIntervento->firstWhere('id', $piId) ?? PresidioIntervento::find($piId);
        if (!$pi) {
            return;
        }

        $this->syncAnomaliePresidioIntervento($pi, $selected, $riparate);
    }

    public function toggleAnomaliaRiparata(int $piId, int $anomaliaId, $checked): void
    {
        $checked = filter_var($checked, FILTER_VALIDATE_BOOL);
        if (!isset($this->input[$piId])) {
            return;
        }

        $selected = $this->normalizeAnomalieIds($this->input[$piId]['anomalie'] ?? []);
        if (!in_array($anomaliaId, $selected, true)) {
            $selected[] = $anomaliaId;
            sort($selected);
        }

        $riparate = $this->input[$piId]['anomalie_riparate'] ?? [];
        $riparate[$anomaliaId] = $checked;
        $riparate = $this->normalizeAnomalieRiparate($selected, $riparate);

        $this->input[$piId]['anomalie'] = $selected;
        $this->input[$piId]['anomalie_riparate'] = $riparate;

        $pi = $this->intervento->presidiIntervento->firstWhere('id', $piId) ?? PresidioIntervento::find($piId);
        if (!$pi) {
            return;
        }

        $this->syncAnomaliePresidioIntervento($pi, $selected, $riparate);
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

        if ($field === 'nuova_marca_serbatoio') {
            $this->input[$piId]['nuova_marca_serbatoio'] = $this->normalizeMarca((string) $value);
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
    
        if ($pi->presidio) {
            $pi->presidio->eliminaLogicamente();
        }

        unset($this->input[$id]);
        $pi->delete();
    
        $this->messaggioSuccesso = 'Presidio rimosso dall’intervento (eliminazione logica registrata).';
        $this->intervento->load(...$this->interventoRelations());
    }
    public function getAnomalieProperty()
    {
        $query = Anomalia::query()
            ->where('attiva', true)
            ->select(['id', 'categoria', 'etichetta']);

        if (Schema::hasColumn('anomalie', 'prezzo')) {
            $query->addSelect('prezzo');
        }
        if (Schema::hasColumn('anomalie', 'usa_prezzi_tipo_estintore')) {
            $query->addSelect('usa_prezzi_tipo_estintore');
        }
        if (Schema::hasColumn('anomalie', 'usa_prezzi_tipo_presidio')) {
            $query->addSelect('usa_prezzi_tipo_presidio');
        }
        if (Schema::hasTable('anomalia_prezzi_tipo_estintore')) {
            $query->with('prezziTipoEstintore:anomalia_id,tipo_estintore_id,prezzo');
        }
        if (Schema::hasTable('anomalia_prezzi_tipo_presidio')) {
            $query->with('prezziTipoPresidio:anomalia_id,tipo_presidio_id,prezzo');
        }

        return $query
            ->orderBy('categoria')
            ->orderBy('etichetta')
            ->get()
            ->groupBy('categoria');
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

        $ordineTrovato = (bool) ($this->ordinePreventivo['found'] ?? false);
        $chiusuraSoloTotaleSenzaOrdine = $this->richiedePagamentoManutentore && !$ordineTrovato;

        if ($this->richiedePagamentoManutentore) {
            $metodo = $this->normalizePagamentoMetodo($this->pagamentoMetodo);
            $importo = $this->normalizePagamentoImporto($this->pagamentoImporto);

            if ($chiusuraSoloTotaleSenzaOrdine) {
                if ($importo === null) {
                    $this->messaggioErrore = 'Pagamento obbligatorio: inserisci il totale incassato.';
                    return;
                }
            } else {
                if (!$metodo || $importo === null) {
                    $this->messaggioErrore = 'Pagamento obbligatorio: seleziona metodo (POS/ASSEGNO/CONTANTI) e importo incassato.';
                    return;
                }
            }

            $this->pagamentoMetodo = $metodo;
            $this->pagamentoImporto = number_format($importo, 2, '.', '');
        }

        $this->persistPagamentoIntervento();

        if ($this->interventoCompletabile) {
            $riepilogoOrdine = $this->riepilogoOrdine;
            $extraPresidi = $riepilogoOrdine['extra_presidi'] ?? [];
            if (($extraPresidi['has_pending_manual_prices'] ?? false) === true && !$chiusuraSoloTotaleSenzaOrdine) {
                $codes = collect($extraPresidi['pending_manual_prices'] ?? [])
                    ->pluck('codice_articolo')
                    ->filter()
                    ->implode(', ');
                $this->messaggioErrore = 'Manca il prezzo per presidi extra: ' . ($codes !== '' ? $codes : 'completa i codici mancanti');
                return;
            }

            foreach ($this->intervento->presidiIntervento as $pi) {
                $this->salvaPresidio($pi->id);
            }

            if ($this->timerSessioniEnabled) {
                $tecniciRows = InterventoTecnico::where('intervento_id', $this->intervento->id)->get(['id', 'started_at', 'ended_at']);
                $ids = $tecniciRows->pluck('id')->all();

                if (!empty($ids)) {
                    InterventoTecnicoSessione::whereIn('intervento_tecnico_id', $ids)
                        ->whereNull('ended_at')
                        ->update(['ended_at' => now()]);
                }

                foreach ($tecniciRows as $itRow) {
                    $this->sincronizzaPivotTimer($itRow);
                }
            } else {
                InterventoTecnico::where('intervento_id', $this->intervento->id)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => now()]);
            }

            // Se tutti i presidi sono verificati, completa l'intervento con durata calcolata dai timer
            $durataEffettiva = $this->calcolaDurataEffettivaTotaleIntervento();
            $this->durataEffettiva = $durataEffettiva;
            $payloadUpdate = [
                'stato' => 'Completato',
                'durata_effettiva' => $durataEffettiva,
                'pagamento_metodo' => $this->intervento->pagamento_metodo,
                'pagamento_importo' => $this->intervento->pagamento_importo,
            ];
            if (Schema::hasColumn('interventi', 'closed_by_user_id')) {
                $payloadUpdate['closed_by_user_id'] = auth()->id();
            }
            $this->intervento->update($payloadUpdate);
            $this->accodaMailRapportinoInterno();
            $this->messaggioSuccesso ='Intervento evaso correttamente. Apertura rapportino in corso...';

            $clientePdfUrl = route('rapportino.pdf', ['id' => $this->intervento->id, 'kind' => 'cliente']);
            $clientePdfDownloadUrl = route('rapportino.pdf', ['id' => $this->intervento->id, 'kind' => 'cliente', 'download' => 1]);
            $clienteMailtoUrl = $this->buildClienteMailtoUrl();

            $this->dispatch(
                'intervento-completato',
                pdfUrl: $clientePdfUrl,
                pdfDownloadUrl: $clientePdfDownloadUrl,
                clienteMailtoUrl: $clienteMailtoUrl,
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
            ->attivi()
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

        $nuovaMarca = $this->normalizeMarca($dati['nuova_marca_serbatoio'] ?? null) ?? $vecchio->marca_serbatoio;

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

        $vecchio->sostituito_con_presidio_id = $nuovo->id;
        $vecchio->eliminaLogicamente();

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

            $nuovaMarca = $this->normalizeMarca($dati['nuova_marca_serbatoio'] ?? null) ?? $vecchio->marca_serbatoio;

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

            $vecchio->sostituito_con_presidio_id = $nuovo->id;
            $vecchio->eliminaLogicamente();

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
        $isMb = $this->normalizeMarca($this->input[$id]['nuova_marca_serbatoio'] ?? null) === 'MB';
        $this->input[$id]['nuova_marca_serbatoio'] = $isMb ? null : 'MB';
        $this->aggiornaPreviewSostituzione($id);
    }

    public function setMarcaMbNuovo(): void
    {
        $isMb = $this->normalizeMarca($this->nuovoPresidio['marca_serbatoio'] ?? null) === 'MB';
        $this->nuovoPresidio['marca_serbatoio'] = $isMb ? null : 'MB';
        $this->aggiornaPreviewNuovo();
    }

    public function aggiornaPreviewSostituzione(int $id): void
    {
        $dati = $this->input[$id] ?? [];
        $marca = $dati['nuova_marca_serbatoio'] ?? null;
        $this->previewSostituzione[$id] = $this->calcolaPreviewScadenze(
            $dati['nuovo_tipo_estintore_id'] ?? null,
            $dati['nuova_data_serbatoio'] ?? null,
            $dati['nuova_data_ultima_revisione'] ?? null,
            $marca
        ) ?? [];
    }

    public function aggiornaPreviewNuovo(): void
    {
        $dati = $this->nuovoPresidio ?? [];
        $marca = $dati['marca_serbatoio'] ?? null;
        $this->previewNuovo = $this->calcolaPreviewScadenze(
            $dati['tipo_estintore_id'] ?? null,
            $dati['data_serbatoio'] ?? null,
            $dati['data_ultima_revisione'] ?? null,
            $marca
        ) ?? [];
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

    private function normalizePagamentoMetodo($metodo): ?string
    {
        $metodo = mb_strtoupper(trim((string) $metodo));
        if ($metodo === '') {
            return null;
        }

        return in_array($metodo, ['POS', 'ASSEGNO', 'CONTANTI'], true) ? $metodo : null;
    }

    private function normalizePagamentoImporto($importo): ?float
    {
        if ($importo === null) {
            return null;
        }

        $value = trim((string) $importo);
        if ($value === '') {
            return null;
        }

        $value = str_replace(',', '.', $value);
        if (!is_numeric($value)) {
            return null;
        }

        $num = round((float) $value, 2);
        return $num > 0 ? $num : null;
    }

    private function persistPagamentoIntervento(): void
    {
        if (!$this->richiedePagamentoManutentore) {
            $this->intervento->pagamento_metodo = null;
            $this->intervento->pagamento_importo = null;
            $this->intervento->save();
            return;
        }

        $this->intervento->pagamento_metodo = $this->normalizePagamentoMetodo($this->pagamentoMetodo);
        $this->intervento->pagamento_importo = $this->normalizePagamentoImporto($this->pagamentoImporto);
        $this->intervento->save();
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

    public function ricaricaFormaPagamentoBusiness(): void
    {
        $ok = $this->refreshFormaPagamentoFromBusiness(true);
        $this->hydrateFormaPagamentoFromCliente();

        if ($ok) {
            $this->messaggioSuccesso = 'Forma pagamento aggiornata da Business.';
            return;
        }

        if (!$this->messaggioErrore) {
            $this->messaggioErrore = 'Impossibile aggiornare la forma pagamento da Business.';
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
        $extraPresidi = $svc->buildExtraPresidiSummary(
            $confronto,
            $this->prezziExtraManuali
        );
        $anomalie = $svc->buildAnomalieSummaryFromInput(
            $this->input,
            $this->anomalyLabelMap(),
            $this->presidioContextByPiId()
        );
        $riepilogoEconomico = $svc->buildEconomicSummary(
            (float) data_get($this->ordinePreventivo, 'header.totale_documento', 0),
            $extraPresidi,
            $anomalie
        );

        return [
            'righe_intervento' => $righeIntervento['rows'] ?? [],
            'presidi_senza_codice' => $righeIntervento['missing_mapping'] ?? [],
            'confronto' => $confronto,
            'extra_presidi' => $extraPresidi,
            'prezzi_extra_manuali' => $this->prezziExtraManuali,
            'riepilogo_economico' => $riepilogoEconomico,
            'anomalie' => $anomalie,
        ];
    }

    public function setPrezzoExtra(string $codiceArticolo, $value): void
    {
        $code = $this->normalizeCodiceArticolo($codiceArticolo);
        if ($code === null) {
            return;
        }

        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            unset($this->prezziExtraManuali[$code]);
            $this->persistPrezziExtraManuali();
            $this->messaggioSuccesso = "Prezzo extra rimosso per {$code}.";
            return;
        }

        $parsed = $this->normalizePrezzoExtra($value);
        if ($parsed === null) {
            unset($this->prezziExtraManuali[$code]);
            $this->persistPrezziExtraManuali();
            $this->messaggioErrore = "Prezzo non valido per codice {$code}.";
            return;
        }

        $this->prezziExtraManuali[$code] = $parsed;
        $this->persistPrezziExtraManuali();
        $this->messaggioSuccesso = "Prezzo extra salvato per {$code}.";
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
        if ($this->anomalyMapCache !== null) {
            return $this->anomalyMapCache;
        }

        $this->anomalyMapCache = collect($this->anomalie)
            ->flatten(1)
            ->mapWithKeys(function (Anomalia $anomalia) {
                $prezziTipoEstintore = collect($anomalia->prezziTipoEstintore ?? [])
                    ->mapWithKeys(fn ($row) => [
                        (int) $row->tipo_estintore_id => (float) $row->prezzo,
                    ])
                    ->toArray();

                $prezziTipoPresidio = collect($anomalia->prezziTipoPresidio ?? [])
                    ->mapWithKeys(fn ($row) => [
                        (int) $row->tipo_presidio_id => (float) $row->prezzo,
                    ])
                    ->toArray();

                return [
                    (int) $anomalia->id => [
                        'etichetta' => (string) $anomalia->etichetta,
                        'prezzo' => (float) ($anomalia->prezzo ?? 0),
                        'usa_prezzi_tipo_estintore' => (bool) ($anomalia->usa_prezzi_tipo_estintore ?? false),
                        'usa_prezzi_tipo_presidio' => (bool) ($anomalia->usa_prezzi_tipo_presidio ?? false),
                        'prezzi_tipo_estintore' => $prezziTipoEstintore,
                        'prezzi_tipo_presidio' => $prezziTipoPresidio,
                    ],
                ];
            })
            ->toArray();

        return $this->anomalyMapCache;
    }

    public function prezzoAnomaliaPerPresidio(int $piId, int $anomaliaId): float
    {
        $meta = $this->anomalyLabelMap()[$anomaliaId] ?? null;
        if (!is_array($meta)) {
            return 0.0;
        }

        $prezzo = max(0, (float) ($meta['prezzo'] ?? 0));
        $pi = $this->intervento->presidiIntervento->firstWhere('id', $piId);
        $presidio = $pi?->presidio;

        if (!$presidio) {
            return round($prezzo, 2);
        }

        $categoria = (string) ($presidio->categoria ?? '');
        if ($categoria === 'Estintore' && (bool) ($meta['usa_prezzi_tipo_estintore'] ?? false)) {
            $tipoId = (int) ($presidio->tipo_estintore_id ?? 0);
            $map = is_array($meta['prezzi_tipo_estintore'] ?? null) ? $meta['prezzi_tipo_estintore'] : [];
            if ($tipoId > 0 && array_key_exists($tipoId, $map)) {
                return round(max(0, (float) $map[$tipoId]), 2);
            }
        }

        if (in_array($categoria, ['Idrante', 'Porta'], true) && (bool) ($meta['usa_prezzi_tipo_presidio'] ?? false)) {
            $tipoId = $categoria === 'Idrante'
                ? (int) ($presidio->idrante_tipo_id ?? 0)
                : (int) ($presidio->porta_tipo_id ?? 0);
            $map = is_array($meta['prezzi_tipo_presidio'] ?? null) ? $meta['prezzi_tipo_presidio'] : [];
            if ($tipoId > 0 && array_key_exists($tipoId, $map)) {
                return round(max(0, (float) $map[$tipoId]), 2);
            }
        }

        return round($prezzo, 2);
    }

    private function presidioContextByPiId(): array
    {
        $context = [];
        foreach ($this->intervento->presidiIntervento as $pi) {
            $presidio = $pi->presidio;
            if (!$presidio) {
                continue;
            }

            $context[(int) $pi->id] = [
                'categoria' => (string) ($presidio->categoria ?? ''),
                'tipo_estintore_id' => (int) ($presidio->tipo_estintore_id ?? 0),
                'idrante_tipo_id' => (int) ($presidio->idrante_tipo_id ?? 0),
                'porta_tipo_id' => (int) ($presidio->porta_tipo_id ?? 0),
            ];
        }

        return $context;
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

    public function syncOfflineDraft($payload = []): array
    {
        $payload = is_array($payload) ? $payload : [];
        $inputRows = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $updated = 0;

        foreach ($inputRows as $piIdRaw => $rowRaw) {
            $piId = (int) $piIdRaw;
            if ($piId <= 0 || !is_array($rowRaw)) {
                continue;
            }

            $pi = $this->intervento->presidiIntervento->firstWhere('id', $piId) ?? PresidioIntervento::find($piId);
            if (!$pi || (int) $pi->intervento_id !== (int) $this->intervento->id) {
                continue;
            }

            $row = $rowRaw;
            $this->input[$piId] = array_merge($this->input[$piId] ?? [], $row);

            if (array_key_exists('ubicazione', $row) && $pi->presidio) {
                $nuovaUbicazione = trim((string) $row['ubicazione']);
                if ($nuovaUbicazione !== '' && $pi->presidio->ubicazione !== $nuovaUbicazione) {
                    $pi->presidio->update(['ubicazione' => $nuovaUbicazione]);
                }
            }

            if (array_key_exists('esito', $row)) {
                $esito = (string) $row['esito'];
                if (in_array($esito, ['verificato', 'non_verificato', 'sostituito'], true)) {
                    $pi->esito = $esito;
                }
            }

            if (array_key_exists('note', $row)) {
                $pi->note = is_scalar($row['note']) ? (string) $row['note'] : null;
            }

            if (array_key_exists('usa_ritiro', $row)) {
                $pi->usa_ritiro = filter_var($row['usa_ritiro'], FILTER_VALIDATE_BOOL);
            }

            if (array_key_exists('anomalie', $row) || array_key_exists('anomalie_riparate', $row)) {
                $selectedIds = $this->normalizeAnomalieIds($row['anomalie'] ?? []);
                $riparateMap = $this->normalizeAnomalieRiparate($selectedIds, $row['anomalie_riparate'] ?? []);
                $this->syncAnomaliePresidioIntervento($pi, $selectedIds, $riparateMap);
            }

            $pi->save();
            $updated++;
        }

        if (array_key_exists('durataEffettiva', $payload) && is_numeric($payload['durataEffettiva'])) {
            $this->durataEffettiva = max(0, (int) $payload['durataEffettiva']);
            $this->intervento->durata_effettiva = $this->durataEffettiva;
            $this->intervento->save();
        }

        if (array_key_exists('pagamentoMetodo', $payload) || array_key_exists('pagamentoImporto', $payload)) {
            if (array_key_exists('pagamentoMetodo', $payload)) {
                $this->pagamentoMetodo = $this->normalizePagamentoMetodo($payload['pagamentoMetodo'] ?? null);
            }
            if (array_key_exists('pagamentoImporto', $payload)) {
                $importo = $this->normalizePagamentoImporto($payload['pagamentoImporto'] ?? null);
                $this->pagamentoImporto = $importo !== null ? number_format($importo, 2, '.', '') : null;
            }
            $this->persistPagamentoIntervento();
        }

        if (array_key_exists('noteInterventoGenerali', $payload)) {
            $this->noteInterventoGenerali = is_scalar($payload['noteInterventoGenerali'])
                ? (string) $payload['noteInterventoGenerali']
                : null;
            $this->intervento->note = $this->noteInterventoGenerali;
            $this->intervento->save();
        }

        if (array_key_exists('noteClienteAnagrafica', $payload) && $this->intervento->cliente) {
            $this->noteClienteAnagrafica = is_scalar($payload['noteClienteAnagrafica'])
                ? (string) $payload['noteClienteAnagrafica']
                : null;
            $this->intervento->cliente->note = $this->noteClienteAnagrafica;
            $this->intervento->cliente->save();
            $this->intervento->setRelation('cliente', $this->intervento->cliente->fresh());
        }

        if (array_key_exists('prezziExtraManuali', $payload) && is_array($payload['prezziExtraManuali'])) {
            $newMap = [];
            foreach ($payload['prezziExtraManuali'] as $code => $price) {
                $normalizedCode = $this->normalizeCodiceArticolo($code);
                $normalizedPrice = $this->normalizePrezzoExtra($price);
                if ($normalizedCode === null || $normalizedPrice === null) {
                    continue;
                }
                $newMap[$normalizedCode] = $normalizedPrice;
            }
            $this->prezziExtraManuali = $newMap;
            $this->persistPrezziExtraManuali();
        }

        if ($updated > 0) {
            $this->intervento->load(...$this->interventoRelations());
            $this->messaggioSuccesso = 'Modifiche offline sincronizzate con successo.';
        }

        return [
            'ok' => true,
            'updated' => $updated,
        ];
    }

    private function accodaMailRapportinoInterno(): void
    {
        $destinatario = trim((string) config('interventi.internal_report_email', 'debora@antincendiolughese.com'));
        if ($destinatario === '') {
            return;
        }

        $interventoId = (int) $this->intervento->id;

        $esiste = MailQueueItem::query()
            ->where('intervento_id', $interventoId)
            ->where('tipo', 'rapportino_interno')
            ->whereIn('status', ['queued', 'processing', 'sent'])
            ->exists();

        if ($esiste) {
            return;
        }

        $delay = max(0, (int) config('interventi.internal_report_delay_minutes', 10));

        $item = MailQueueItem::create([
            'intervento_id' => $interventoId,
            'tipo' => 'rapportino_interno',
            'to_email' => $destinatario,
            'subject' => sprintf(
                'Rapportino interno intervento #%d - %s',
                $interventoId,
                (string) ($this->intervento->cliente?->nome ?? 'Cliente')
            ),
            'body' => 'In allegato il rapportino interno intervento completo di riepiloghi.',
            'payload' => [
                'pdf_kind' => 'interno',
            ],
            'send_after' => now()->addMinutes($delay),
            'status' => 'queued',
            'attempts' => 0,
        ]);

        // Se il worker queue e' attivo, questa dispatch garantisce l'invio senza dipendere dal scheduler cron.
        if (config('queue.default') !== 'sync') {
            ProcessMailQueueItemJob::dispatch((int) $item->id)
                ->delay(now()->addMinutes($delay))
                ->onQueue('default');
        }
    }

    private function buildClienteMailtoUrl(): ?string
    {
        $emailCliente = trim((string) ($this->intervento->cliente?->email ?? ''));
        if ($emailCliente === '') {
            return null;
        }

        $data = $this->intervento->data_intervento
            ? Carbon::parse($this->intervento->data_intervento)->format('d/m/Y')
            : date('d/m/Y');

        $subject = 'Rapportino intervento ' . ($this->intervento->cliente?->nome ?? '');
        $body = "Buongiorno,\n\ninviamo il rapportino dell'intervento del {$data} in allegato PDF.\n\nCordiali saluti.";

        return 'mailto:' . $emailCliente
            . '?subject=' . rawurlencode($subject)
            . '&body=' . rawurlencode($body);
    }

    private function loadPrezziExtraManualiFromPayload(): array
    {
        $payload = $this->intervento->fatturazione_payload;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($payload)) {
            $payload = [];
        }

        $raw = $payload['prezzi_extra'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $code => $price) {
            $normalizedCode = $this->normalizeCodiceArticolo($code);
            $normalizedPrice = $this->normalizePrezzoExtra($price);
            if ($normalizedCode === null || $normalizedPrice === null) {
                continue;
            }
            $out[$normalizedCode] = $normalizedPrice;
        }

        return $out;
    }

    private function hydrateFormaPagamentoFromCliente(): void
    {
        $cliente = $this->intervento->cliente;

        $this->richiedePagamentoManutentore = (bool) ($cliente?->richiede_pagamento_manutentore ?? false);
        $this->formaPagamentoDescrizione = trim((string) ($cliente?->forma_pagamento_descrizione ?? ''));
        if ($this->formaPagamentoDescrizione === '') {
            $this->formaPagamentoDescrizione = null;
        }

        if ($this->formaPagamentoDescrizione === null && $this->richiedePagamentoManutentore) {
            $this->formaPagamentoDescrizione = 'ALLA CONSEGNA';
        }
    }

    private function refreshFormaPagamentoFromBusiness(bool $notifyError): bool
    {
        $cliente = $this->intervento->cliente;
        if (!$cliente) {
            return false;
        }

        if (!Schema::hasColumn('clienti', 'forma_pagamento_codice')
            || !Schema::hasColumn('clienti', 'forma_pagamento_descrizione')
            || !Schema::hasColumn('clienti', 'richiede_pagamento_manutentore')
        ) {
            return false;
        }

        $svc = app(BusinessFormaPagamentoService::class);
        $result = $svc->leggiPerConto((string) ($cliente->codice_esterno ?? ''));
        if (!($result['found'] ?? false)) {
            if ($notifyError && !empty($result['error'])) {
                $this->messaggioErrore = 'Business pagamento: ' . $result['error'];
            }
            return false;
        }

        $newCode = $result['forma_pagamento_codice'] ?? null;
        $newDesc = $result['forma_pagamento_descrizione'] ?? null;
        $newRequires = (bool) ($result['richiede_pagamento_manutentore'] ?? false);

        $updates = [
            'forma_pagamento_codice' => $newCode,
            'forma_pagamento_descrizione' => $newDesc,
            'richiede_pagamento_manutentore' => $newRequires,
        ];

        $cliente->fill($updates);
        if ($cliente->isDirty(array_keys($updates))) {
            $cliente->save();
            $this->intervento->setRelation('cliente', $cliente->fresh());
        }

        return true;
    }

    private function persistPrezziExtraManuali(): void
    {
        $payload = $this->intervento->fatturazione_payload;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($payload)) {
            $payload = [];
        }

        $payload['prezzi_extra'] = $this->prezziExtraManuali;
        $this->intervento->fatturazione_payload = $payload;
        $this->intervento->save();
    }

    private function normalizeCodiceArticolo($value): ?string
    {
        $code = mb_strtoupper(trim((string) $value));
        return $code === '' ? null : $code;
    }

    private function normalizePrezzoExtra($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/[^0-9,.\-]/', '', $raw);
        $raw = str_replace(',', '.', (string) $raw);
        if (substr_count((string) $raw, '.') > 1) {
            $parts = explode('.', (string) $raw);
            $decimal = array_pop($parts);
            $raw = implode('', $parts) . '.' . $decimal;
        }

        if (!is_numeric($raw)) {
            return null;
        }

        $n = round((float) $raw, 4);
        return $n >= 0 ? $n : null;
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
