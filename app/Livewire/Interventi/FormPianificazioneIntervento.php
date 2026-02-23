<?php

namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\Cliente;
use App\Models\Sede;
use App\Models\User;
use App\Models\Intervento;
use App\Models\Presidio;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FormPianificazioneIntervento extends Component
{
    public $clienteId;
    public $sedeId;
    public $dataIntervento;
    public $tecnici = [];
    public array $tecniciOrari = [];
    public $tecniciDisponibili = [];
    public ?string $noteIntervento = null;

    public $meseSelezionato;
    public $annoSelezionato;

    public $zonaFiltro = '';
    public array $zoneDisponibili = [];

    public $clientiInScadenza = [];
    public $clientiConInterventiEsistenti = [];

    private ?array $interventiStatoCache = null;
    
    protected $listeners = ['setMeseAnno'];

    public function mount()
    {
        $oggi = now();
        $this->meseSelezionato = $oggi->month;
        $this->annoSelezionato = $oggi->year;

        $this->tecniciDisponibili = User::whereHas('ruoli', function ($q) {
            $q->where('nome', 'Tecnico');
        })->get();

        // ✅ zone da Clienti + Sedi
        $this->caricaZoneDisponibili();
    }

    /**
     * Carica le zone distinte da CLIENTI e SEDI.
     */
    protected function caricaZoneDisponibili(): void
    {
        $zoneClienti = Cliente::query()
            ->whereNotNull('zona')
            ->where('zona', '!=', '')
            ->pluck('zona');

        $zoneSedi = Sede::query()
            ->whereNotNull('zona')
            ->where('zona', '!=', '')
            ->pluck('zona');

        $this->zoneDisponibili = $zoneClienti
            ->merge($zoneSedi)
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    public function applicaFiltri()
    {
        $this->resetInterventiStatoCache();
        // usa i "computed properties" di Livewire
        $this->clientiInScadenza = $this->getClientiInScadenzaProperty();
        $this->clientiConInterventiEsistenti = $this->getClientiConInterventiEsistentiProperty();
    }

    public function updatedMeseSelezionato(): void
    {
        $this->resetInterventiStatoCache();
    }

    public function updatedAnnoSelezionato(): void
    {
        $this->resetInterventiStatoCache();
    }

    public function interventoRegistrato($clienteId, $sedeId = null): bool
    {
        return Intervento::where('cliente_id', $clienteId)
            ->when($sedeId !== null, fn($q) => $q->where('sede_id', $sedeId))
            ->when($sedeId === null, fn($q) => $q->whereNull('sede_id'))
            ->whereMonth('data_intervento', $this->meseSelezionato)
            ->whereYear('data_intervento', $this->annoSelezionato)
            ->exists();
    }

    public function setMeseAnno($mese, $anno)
    {
        $this->meseSelezionato = (int) $mese;
        $this->annoSelezionato = (int) $anno;
        $this->resetInterventiStatoCache();
        // se un domani vorrai, qui puoi ricaricare le zone in base al mese
        // $this->caricaZoneDisponibili();
    }

    /**
     * Carica i dati per la pianificazione in base a cliente, sede, mese e anno.
     */
    public function caricaDati($clienteId, $sedeId = null, $mese = null, $anno = null)
    {
        $this->clienteId = $clienteId;
        $this->sedeId = $sedeId;
        $this->tecnici = [];
        $this->tecniciOrari = [];

        // Se non arrivano mese/anno uso oggi+1
        if ($mese && $anno) {
            $oggi = now();
            $domani = Carbon::create($anno, $mese, min($oggi->day, 28))->addDay();
        } else {
            $domani = now()->addDay();
        }

        $this->dataIntervento = $domani->format('Y-m-d');
    }

    public function updatedTecnici(): void
    {
        $selezionati = collect((array) $this->tecnici)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        // Normalizza sempre gli ID per evitare mismatch string/int in UI e validazione
        $current = collect((array) $this->tecnici)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        if ($current !== $selezionati) {
            $this->tecnici = $selezionati;
        }

        // rimuovi tecnici non selezionati
        $this->tecniciOrari = array_intersect_key($this->tecniciOrari, array_flip($selezionati));

        // aggiungi struttura per nuovi selezionati
        foreach ($selezionati as $id) {
            if (!isset($this->tecniciOrari[$id])) {
                $this->tecniciOrari[$id] = [
                    'inizio' => '08:00',
                ];
            }
        }
    }


    // 🔽🔽 QUI I METODI CHE MANCAVANO 🔽🔽

    public function getClientiInScadenzaProperty(): Collection
    {
        $mese = str_pad($this->meseSelezionato, 2, '0', STR_PAD_LEFT);

        return Cliente::with([
                'sedi.presidi' => fn($q) => $q->attivi(),
                'presidi' => fn($q) => $q->attivi(),
            ])
            ->when($this->zonaFiltro, function ($q) {
                $q->where(function ($qq) {
                    $qq->where('zona', $this->zonaFiltro)
                        ->orWhereHas('sedi', fn($qs) => $qs->where('zona', $this->zonaFiltro));
                });
            })
            ->whereHas('presidi', fn($q) => $q->attivi())
            ->where(function ($q) use ($mese) {
                $meseInt = json_encode((int) $mese);   // "4"
                $meseStr = json_encode($mese);         // "04"
                $q->whereRaw("JSON_CONTAINS(mesi_visita, ?)", [$meseInt])
                  ->orWhereRaw("JSON_CONTAINS(mesi_visita, ?)", [$meseStr]);
            })
            ->get()
            ->filter(function ($cliente) {
                return $cliente->sedi->contains(function ($sede) {
                    return !$this->interventoEsistente($sede->cliente_id, $sede->id);
                }) || (
                    $cliente->presidi->whereNull('sede_id')->isNotEmpty()
                    && !$this->interventoEsistente($cliente->id, null)
                );
            });
    }

    public function getClientiConInterventiEsistentiProperty(): Collection
    {
        $mese = str_pad($this->meseSelezionato, 2, '0', STR_PAD_LEFT);
    
        return Cliente::with([
                'sedi.presidi' => fn($q) => $q->attivi(),
                'presidi' => fn($q) => $q->attivi(),
            ])
            ->when($this->zonaFiltro, function ($q) {
                $q->where(function ($qq) {
                    $qq->where('zona', $this->zonaFiltro)
                        ->orWhereHas('sedi', fn($qs) => $qs->where('zona', $this->zonaFiltro));
                });
            })
            ->whereHas('presidi', fn($q) => $q->attivi())
            ->where(function ($q) use ($mese) {
                $meseInt = json_encode((int) $mese);   // "4"
                $meseStr = json_encode($mese);         // "04"
                $q->whereRaw("JSON_CONTAINS(mesi_visita, ?)", [$meseInt])
                  ->orWhereRaw("JSON_CONTAINS(mesi_visita, ?)", [$meseStr]);
            })
            ->get()
            ->filter(function ($cliente) {
                return $cliente->sedi->contains(fn($sede) =>
                            $this->interventoEvasa($cliente->id, $sede->id)
                            || $this->interventoEsistente($cliente->id, $sede->id)
                        )
                    || (
                        $cliente->presidi->whereNull('sede_id')->isNotEmpty()
                        && (
                            $this->interventoEvasa($cliente->id, null)
                            || $this->interventoEsistente($cliente->id, null)
                        )
                    );
            });
    }

    // 🔼🔼 FINE METODI MANCANTI 🔼🔼

    public function pianifica()
    {
        $rules = [
            'clienteId' => 'required|exists:clienti,id',
            'dataIntervento' => 'required|date',
            'tecnici' => 'required|array|min:1',
        ];

        foreach ($this->tecnici as $tecId) {
            $rules["tecniciOrari.$tecId.inizio"] = 'required|date_format:H:i';
        }

        $this->validate($rules);

        $cliente = Cliente::findOrFail($this->clienteId);
        $sede = $this->sedeId ? Sede::find($this->sedeId) : null;

        $meseIntervento = (int) Carbon::parse($this->dataIntervento)->month;
        $durata = $this->resolveMinutiPianificazione($cliente, $sede, $meseIntervento);

        $intervento = Intervento::create([
            'cliente_id' => $cliente->id,
            'sede_id' => $sede?->id,
            'data_intervento' => $this->dataIntervento,
            'durata_minuti' => $durata,
            'stato' => 'Pianificato',
            'zona' => $sede->zona ?? $cliente->zona,
            'note' => $this->noteIntervento,
        ]);

        $attachData = [];
        foreach ($this->tecnici as $tecId) {
            $inizio = $this->tecniciOrari[$tecId]['inizio'] ?? null;

            $startAt = $inizio ? Carbon::parse($this->dataIntervento . ' ' . $inizio) : null;
            $endAt = $startAt ? $startAt->copy()->addMinutes($durata) : null;

            $attachData[$tecId] = [
                'scheduled_start_at' => $startAt,
                'scheduled_end_at' => $endAt,
            ];
        }

        $intervento->tecnici()->attach($attachData);

        $presidi = Presidio::where('cliente_id', $cliente->id)
            ->attivi()
            ->when($sede, fn($q) => $q->where('sede_id', $sede->id))
            ->get();

        foreach ($presidi as $presidio) {
            $intervento->presidiIntervento()->create([
                'presidio_id' => $presidio->id,
                'esito' => 'non_verificato',
            ]);
        }

        $this->reset(['clienteId', 'sedeId', 'dataIntervento', 'tecnici', 'tecniciOrari', 'noteIntervento']);
        $this->resetInterventiStatoCache();
        $this->dispatch('intervento-pianificato');
        $this->dispatch('toast', type: 'success', message: 'Intervento pianificato con successo!');
        $this->applicaFiltri();
    }

    private function resolveMinutiPianificazione(Cliente $cliente, ?Sede $sede, int $mese): int
    {
        $minutiSede = $sede?->minutiPerMese($mese);
        if (!empty($minutiSede) && (int) $minutiSede > 0) {
            return (int) $minutiSede;
        }

        $minutiCliente = $cliente->minutiPerMese($mese);
        if (!empty($minutiCliente) && (int) $minutiCliente > 0) {
            return (int) $minutiCliente;
        }

        return (int) ($sede?->minuti_intervento ?? $cliente->minuti_intervento ?? 60);
    }

    public function presidiEvasi($presidi)
    {
        return [
            'totali' => $presidi->count(),
            'evasi' => $presidi->where('esito', '!=', 'non_verificato')->count(),
        ];
    }

    public function interventoEsistente($clienteId, $sedeId = null): bool
    {
        $key = $this->interventoCacheKey((int) $clienteId, $sedeId);
        $row = $this->interventiStatoMeseMap()[$key] ?? null;
        return (bool) ($row['esistente'] ?? false);
    }

    public function interventoEvasa($clienteId, $sedeId = null): bool
    {
        $key = $this->interventoCacheKey((int) $clienteId, $sedeId);
        $row = $this->interventiStatoMeseMap()[$key] ?? null;
        return (bool) ($row['evasa'] ?? false);
    }

    public function getMeseSeiProperty(): int
    {
        return Carbon::create((int) $this->annoSelezionato, (int) $this->meseSelezionato, 1)
            ->addMonthsNoOverflow(6)
            ->month;
    }

    public function getTotaliZonaSelezionataProperty(): array
    {
        $meseCorrente = (int) $this->meseSelezionato;
        $meseSei = (int) $this->meseSei;
        $totaleCorrente = 0;
        $totaleSei = 0;
        $interventi = 0;

        foreach ($this->clientiInScadenza as $cliente) {
            if ($cliente->presidi->whereNull('sede_id')->isNotEmpty() && !$this->interventoEsistente($cliente->id, null)) {
                $zona = trim((string) ($cliente->zona ?? ''));
                if ($this->zonaMatch($zona)) {
                    $totaleCorrente += $this->minutiPerInterventoPreview($cliente, null, $meseCorrente);
                    $totaleSei += $this->minutiPerInterventoPreview($cliente, null, $meseSei);
                    $interventi++;
                }
            }

            foreach ($cliente->sedi as $sede) {
                if ($sede->presidi->isNotEmpty() && !$this->interventoEsistente($cliente->id, $sede->id)) {
                    $zona = trim((string) ($sede->zona ?? $cliente->zona ?? ''));
                    if ($this->zonaMatch($zona)) {
                        $totaleCorrente += $this->minutiPerInterventoPreview($cliente, $sede, $meseCorrente);
                        $totaleSei += $this->minutiPerInterventoPreview($cliente, $sede, $meseSei);
                        $interventi++;
                    }
                }
            }
        }

        return [
            'interventi' => $interventi,
            'minuti_corrente' => $totaleCorrente,
            'minuti_mese_sei' => $totaleSei,
        ];
    }

    public function getZoneConStatoProperty(): array
    {
        $stats = $this->collectZoneStats();
        $rows = [];

        foreach ($this->zoneDisponibili as $zona) {
            $stat = $stats[$zona] ?? ['totale' => 0, 'pianificate' => 0];
            $complete = ((int) $stat['totale'] > 0) && ((int) $stat['pianificate'] >= (int) $stat['totale']);

            $rows[] = [
                'value' => $zona,
                'label' => $zona . ($complete ? ' *' : ''),
                'complete' => $complete,
                'totale' => (int) $stat['totale'],
                'pianificate' => (int) $stat['pianificate'],
            ];
        }

        return $rows;
    }

    public function render()
    {
        return view('livewire.interventi.form-pianificazione-intervento', [
            'clientiInScadenza' => $this->clientiInScadenza,
            'clientiConInterventiEsistenti' => $this->clientiConInterventiEsistenti,
            'zoneConStato' => $this->zoneConStato,
            'totaliZonaSelezionata' => $this->totaliZonaSelezionata,
            'meseSei' => $this->meseSei,
        ]);
    }

    private function collectZoneStats(): array
    {
        $mese = str_pad((string) $this->meseSelezionato, 2, '0', STR_PAD_LEFT);
        $stats = [];
        $seen = [];

        foreach ($this->zoneDisponibili as $zona) {
            $stats[$zona] = ['totale' => 0, 'pianificate' => 0];
        }

        $clienti = Cliente::with([
                'sedi.presidi' => fn($q) => $q->attivi(),
                'presidi' => fn($q) => $q->attivi(),
            ])
            ->whereHas('presidi', fn($q) => $q->attivi())
            ->where(function ($q) use ($mese) {
                $meseInt = json_encode((int) $mese);
                $meseStr = json_encode($mese);
                $q->whereRaw("JSON_CONTAINS(mesi_visita, ?)", [$meseInt])
                    ->orWhereRaw("JSON_CONTAINS(mesi_visita, ?)", [$meseStr]);
            })
            ->get();

        foreach ($clienti as $cliente) {
            if ($cliente->presidi->whereNull('sede_id')->isNotEmpty()) {
                $zona = trim((string) ($cliente->zona ?? ''));
                if ($zona !== '') {
                    $entryKey = $this->interventoCacheKey((int) $cliente->id, null);
                    if (!isset($seen[$entryKey])) {
                        $seen[$entryKey] = true;
                        if (!isset($stats[$zona])) {
                            $stats[$zona] = ['totale' => 0, 'pianificate' => 0];
                        }
                        $stats[$zona]['totale']++;
                        if ($this->interventoEsistente($cliente->id, null)) {
                            $stats[$zona]['pianificate']++;
                        }
                    }
                }
            }

            foreach ($cliente->sedi as $sede) {
                if ($sede->presidi->isEmpty()) {
                    continue;
                }

                $zona = trim((string) ($sede->zona ?? $cliente->zona ?? ''));
                if ($zona === '') {
                    continue;
                }

                $entryKey = $this->interventoCacheKey((int) $cliente->id, (int) $sede->id);
                if (isset($seen[$entryKey])) {
                    continue;
                }

                $seen[$entryKey] = true;
                if (!isset($stats[$zona])) {
                    $stats[$zona] = ['totale' => 0, 'pianificate' => 0];
                }
                $stats[$zona]['totale']++;
                if ($this->interventoEsistente($cliente->id, $sede->id)) {
                    $stats[$zona]['pianificate']++;
                }
            }
        }

        return $stats;
    }

    private function minutiPerInterventoPreview(Cliente $cliente, ?Sede $sede, int $mese): int
    {
        $minutiSede = $sede?->minutiPerMese($mese);
        if (!empty($minutiSede) && (int) $minutiSede > 0) {
            return (int) $minutiSede;
        }

        $minutiCliente = $cliente->minutiPerMese($mese);
        if (!empty($minutiCliente) && (int) $minutiCliente > 0) {
            return (int) $minutiCliente;
        }

        return (int) ($sede?->minuti_intervento ?? $cliente->minuti_intervento ?? 60);
    }

    private function zonaMatch(?string $zona): bool
    {
        $filtro = trim((string) $this->zonaFiltro);
        if ($filtro === '') {
            return true;
        }
        return mb_strtoupper(trim((string) $zona)) === mb_strtoupper($filtro);
    }

    private function resetInterventiStatoCache(): void
    {
        $this->interventiStatoCache = null;
    }

    private function interventiStatoMeseMap(): array
    {
        if ($this->interventiStatoCache !== null) {
            return $this->interventiStatoCache;
        }

        $map = [];
        $rows = Intervento::query()
            ->whereMonth('data_intervento', (int) $this->meseSelezionato)
            ->whereYear('data_intervento', (int) $this->annoSelezionato)
            ->whereIn('stato', ['Pianificato', 'Completato'])
            ->withCount([
                'presidiIntervento as non_verificati_count' => fn($q) => $q->where('esito', 'non_verificato'),
            ])
            ->get(['cliente_id', 'sede_id', 'stato']);

        foreach ($rows as $row) {
            $key = $this->interventoCacheKey((int) $row->cliente_id, $row->sede_id !== null ? (int) $row->sede_id : null);

            if (!isset($map[$key])) {
                $map[$key] = [
                    'esistente' => false,
                    'evasa' => false,
                ];
            }

            if ((string) $row->stato === 'Pianificato') {
                $map[$key]['esistente'] = true;
                continue;
            }

            if ((string) $row->stato === 'Completato' && (int) ($row->non_verificati_count ?? 0) === 0) {
                $map[$key]['esistente'] = true;
                $map[$key]['evasa'] = true;
            }
        }

        $this->interventiStatoCache = $map;
        return $this->interventiStatoCache;
    }

    private function interventoCacheKey(int $clienteId, $sedeId = null): string
    {
        return $clienteId . '|' . ($sedeId === null ? 'null' : (string) ((int) $sedeId));
    }
}
