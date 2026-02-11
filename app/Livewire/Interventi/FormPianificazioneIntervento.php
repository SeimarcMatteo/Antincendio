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
        // usa i "computed properties" di Livewire
        $this->clientiInScadenza = $this->getClientiInScadenzaProperty();
        $this->clientiConInterventiEsistenti = $this->getClientiConInterventiEsistentiProperty();
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

        return Cliente::with(['sedi.presidi', 'presidi'])
            ->when($this->zonaFiltro, fn($q) => $q->where('zona', $this->zonaFiltro))
            ->whereHas('presidi')
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
    
        return Cliente::with(['sedi.presidi', 'presidi'])
            ->when($this->zonaFiltro, fn($q) => $q->where('zona', $this->zonaFiltro))
            ->whereHas('presidi')
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
            ->when($sede, fn($q) => $q->where('sede_id', $sede->id))
            ->get();

        foreach ($presidi as $presidio) {
            $intervento->presidiIntervento()->create([
                'presidio_id' => $presidio->id,
                'esito' => 'non_verificato',
            ]);
        }

        $this->reset(['clienteId', 'sedeId', 'dataIntervento', 'tecnici', 'tecniciOrari', 'noteIntervento']);
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
        return Intervento::where('cliente_id', $clienteId)
            ->when($sedeId !== null, fn($q) => $q->where('sede_id', $sedeId))
            ->when($sedeId === null, fn($q) => $q->whereNull('sede_id'))
            ->whereMonth('data_intervento', $this->meseSelezionato)
            ->whereYear('data_intervento', $this->annoSelezionato)
            ->whereIn('stato', ['Pianificato', 'Completato'])
            ->where(function ($query) {
                $query->where('stato', 'Pianificato')
                    ->orWhere(function ($q) {
                        $q->where('stato', 'Completato')
                          ->whereDoesntHave('presidiIntervento', fn($sub) =>
                              $sub->where('esito', 'non_verificato')
                          );
                    });
            })
            ->exists();
    }

    public function interventoEvasa($clienteId, $sedeId = null): bool
    {
        return Intervento::where('cliente_id', $clienteId)
            ->when($sedeId !== null, fn($q) => $q->where('sede_id', $sedeId))
            ->when($sedeId === null, fn($q) => $q->whereNull('sede_id'))
            ->whereMonth('data_intervento', $this->meseSelezionato)
            ->whereYear('data_intervento', $this->annoSelezionato)
            ->where('stato', 'Completato')
            ->whereDoesntHave('presidiIntervento', fn($q) =>
                $q->where('esito', 'non_verificato')
            )
            ->exists();
    }

    public function render()
    {
        return view('livewire.interventi.form-pianificazione-intervento', [
            'clientiInScadenza' => $this->clientiInScadenza,
            'clientiConInterventiEsistenti' => $this->clientiConInterventiEsistenti,
        ]);
    }
}
