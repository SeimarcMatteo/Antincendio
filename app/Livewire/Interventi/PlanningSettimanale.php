<?php

namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\User;
use App\Models\Intervento;
use App\Models\InterventoTecnico;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class PlanningSettimanale extends Component
{
    public string $inizioSettimana;
    public $giorn;
    public string $vista = 'settimanale';
    public string $meseRiferimento;
    public array $azioniTecnico = [];
    public ?string $bulkData = null;
    public ?string $bulkZona = null;
    public $bulkTecnicoDa = null;
    public $bulkTecnicoA = null;
    protected $listeners = ['intervento-pianificato' => '$refresh'];

    public function mount()
    {
        $this->inizioSettimana = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $this->meseRiferimento = now()->format('Y-m');
        $this->bulkData = now()->format('Y-m-d');
    }

    public function getKeySettimanaProperty()
    {
        return 'settimana-' . $this->inizioSettimana;
    }

    public function giorniSettimana()
    {
        $period = CarbonPeriod::create($this->inizioSettimana, '1 day', 6);

        return collect($period)->map(function ($day) {
            $carbon = Carbon::parse($day);
            $festivo = $this->isFestivo($carbon);
            return [
                'data' => $carbon,
                'festivo' => $festivo,
            ];
        });
    }

    public function isFestivo($data)
    {
        $dataCarbon = Carbon::parse($data);
    
        $festiviFissi = [
            '01-01', '01-06', '04-25', '05-01', '06-02',
            '08-15', '11-01', '12-08', '12-25', '12-26'
        ];
    
        $pasqua = Carbon::createFromTimestamp(easter_date($dataCarbon->year));
        $lunedìPasqua = $pasqua->copy()->addDay();
    
        return in_array($dataCarbon->format('m-d'), $festiviFissi)
            || $dataCarbon->isSameDay($pasqua)
            || $dataCarbon->isSameDay($lunedìPasqua);
    }
    
    public function annullaIntervento(int $id, ?int $tecnicoId = null): void
    {
        $intervento = Intervento::find($id);

        if (!$intervento || $intervento->stato !== 'Pianificato') {
            $this->dispatch('toast', type: 'warning', message: 'Intervento non eliminabile in quanto COMPLETATO!');
            return;
        }

        if ($tecnicoId !== null) {
            $pivot = InterventoTecnico::where('intervento_id', $id)
                ->where('user_id', $tecnicoId)
                ->first();

            if (!$pivot) {
                $this->dispatch('toast', type: 'error', message: 'Pianificazione tecnico non trovata.');
                return;
            }

            $pivot->delete();

            $hasTecnici = InterventoTecnico::where('intervento_id', $id)->exists();
            if (!$hasTecnici) {
                $intervento->presidiIntervento()->delete();
                $intervento->delete();
                $this->dispatch('toast', type: 'info', message: 'Ultimo tecnico rimosso: intervento annullato.');
            } else {
                $this->dispatch('toast', type: 'info', message: 'Pianificazione annullata per il tecnico selezionato.');
            }

            $this->clearActionSelections();
            $this->dispatch('intervento-pianificato');
            return;
        }

        $intervento->tecnici()->detach();
        $intervento->presidiIntervento()->delete();
        $intervento->delete();

        $this->clearActionSelections();
        $this->dispatch('intervento-pianificato');
        $this->dispatch('toast', type: 'info', message: 'Intervento eliminato.');
    }
    
    public function setVista(string $vista): void
    {
        $vista = in_array($vista, ['settimanale', 'mensile'], true) ? $vista : 'settimanale';
        $this->vista = $vista;
    }

    public function settimanaPrecedente()
    {
        $this->inizioSettimana = Carbon::parse($this->inizioSettimana)->subWeek()->format('Y-m-d');
        $this->syncMeseRiferimentoDaSettimana();
        $this->dispatch('setMeseAnno', 
            mese: Carbon::parse($this->inizioSettimana)->month, 
            anno: Carbon::parse($this->inizioSettimana)->year
        );
    }

    public function settimanaSuccessiva()
    {
        $this->inizioSettimana = Carbon::parse($this->inizioSettimana)->addWeek()->format('Y-m-d');
        $this->syncMeseRiferimentoDaSettimana();
        $this->dispatch('setMeseAnno', 
            mese: Carbon::parse($this->inizioSettimana)->month, 
            anno: Carbon::parse($this->inizioSettimana)->year
        );
    }

    public function mesePrecedente(): void
    {
        $this->meseRiferimento = Carbon::createFromFormat('Y-m', $this->meseRiferimento)
            ->subMonthNoOverflow()
            ->format('Y-m');
    }

    public function meseSuccessivo(): void
    {
        $this->meseRiferimento = Carbon::createFromFormat('Y-m', $this->meseRiferimento)
            ->addMonthNoOverflow()
            ->format('Y-m');
    }

    public function render()
    {
        $this->giorn= $this->giorniSettimana();
        $gior = $this->giorn;

        $from = $gior->first()['data']->toDateString();
        $to = $gior->last()['data']->toDateString();

        $tecnici = User::whereHas('ruoli', function ($query) {
            $query->where('nome', 'Tecnico');
        })
            ->select(['id', 'name'])
            ->with([
                'ruoli:id,nome',
                'interventi' => function ($q) use ($from, $to) {
                    $q->select([
                        'interventi.id',
                        'interventi.cliente_id',
                        'interventi.sede_id',
                        'interventi.data_intervento',
                        'interventi.durata_minuti',
                        'interventi.zona',
                        'interventi.note',
                        'interventi.stato',
                    ])
                        ->whereBetween('data_intervento', [$from, $to])
                        ->orderByRaw('intervento_tecnico.scheduled_start_at IS NULL')
                        ->orderBy('intervento_tecnico.scheduled_start_at')
                        ->orderBy('interventi.id');
                },
                'interventi.cliente:id,nome',
                'interventi.sede:id,nome',
            ])
            ->get();

        $bulkDataRef = $this->normalizeBulkData();
        $tecniciDisponibili = $this->tecniciDisponibili();
        $zoneGiorno = $this->zonePerData($bulkDataRef);
        $calendarioMensile = $this->calendarioMensile();

        return view('livewire.interventi.planning-settimanale', [
            'giorni' => $this->giorn,
            'tecnici' => $tecnici,
            'tecniciDisponibili' => $tecniciDisponibili,
            'zoneGiorno' => $zoneGiorno,
            'calendarioMensile' => $calendarioMensile,
            'meseLabel' => Carbon::createFromFormat('Y-m', $this->meseRiferimento)->translatedFormat('F Y'),
            'bulkDataRef' => $bulkDataRef,
        ])->layout('layouts.app'); // ✅ se usi il classico layout Laravel Breeze
    ;
    }

    public function formatMinutes($minutes): string
    {
        $minutes = max(0, (int) $minutes);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours <= 0) {
            return $mins . ' min';
        }
        if ($mins === 0) {
            return $hours . ' h';
        }
        return $hours . ' h ' . $mins . ' min';
    }

    public function aggiornaOrarioTecnico(int $interventoId, int $tecnicoId, ?string $orario): void
    {
        $pivot = InterventoTecnico::where('intervento_id', $interventoId)
            ->where('user_id', $tecnicoId)
            ->first();

        if (!$pivot) {
            $this->dispatch('toast', type: 'error', message: 'Associazione tecnico/intervento non trovata.');
            return;
        }

        if (blank($orario)) {
            $pivot->scheduled_start_at = null;
            $pivot->scheduled_end_at = null;
            $pivot->save();
            $this->dispatch('toast', type: 'info', message: 'Orario rimosso.');
            return;
        }

        if (!preg_match('/^\d{2}:\d{2}$/', (string) $orario)) {
            $this->dispatch('toast', type: 'error', message: 'Formato orario non valido.');
            return;
        }

        $intervento = Intervento::find($interventoId);
        if (!$intervento) {
            $this->dispatch('toast', type: 'error', message: 'Intervento non trovato.');
            return;
        }

        $startAt = Carbon::parse($intervento->data_intervento . ' ' . $orario);
        $durata = max(0, (int) ($intervento->durata_minuti ?? 0));

        $pivot->scheduled_start_at = $startAt;
        $pivot->scheduled_end_at = $startAt->copy()->addMinutes($durata);
        $pivot->save();

        $this->dispatch('toast', type: 'success', message: 'Orario tecnico aggiornato.');
    }

    public function spostaInterventoTecnico(int $interventoId, int $tecnicoDaId): void
    {
        $target = (int) ($this->azioniTecnico[$this->azioneKey($interventoId, $tecnicoDaId)] ?? 0);
        if ($target <= 0) {
            $this->dispatch('toast', type: 'warning', message: 'Seleziona il tecnico di destinazione.');
            return;
        }

        if ($target === $tecnicoDaId) {
            $this->dispatch('toast', type: 'warning', message: 'Il tecnico di destinazione coincide con quello corrente.');
            return;
        }

        $intervento = Intervento::find($interventoId);
        if (!$intervento || $intervento->stato !== 'Pianificato') {
            $this->dispatch('toast', type: 'error', message: 'Intervento non spostabile (non pianificato).');
            return;
        }

        $pivotDa = InterventoTecnico::where('intervento_id', $interventoId)
            ->where('user_id', $tecnicoDaId)
            ->first();
        if (!$pivotDa) {
            $this->dispatch('toast', type: 'error', message: 'Associazione tecnico/intervento di partenza non trovata.');
            return;
        }

        $giaAssegnato = InterventoTecnico::where('intervento_id', $interventoId)
            ->where('user_id', $target)
            ->exists();
        if ($giaAssegnato) {
            $this->dispatch('toast', type: 'warning', message: 'Il tecnico di destinazione è già assegnato a questo intervento.');
            return;
        }

        DB::transaction(function () use ($pivotDa, $interventoId, $target) {
            InterventoTecnico::create([
                'intervento_id' => $interventoId,
                'user_id' => $target,
                'started_at' => $pivotDa->started_at,
                'ended_at' => $pivotDa->ended_at,
                'scheduled_start_at' => $pivotDa->scheduled_start_at,
                'scheduled_end_at' => $pivotDa->scheduled_end_at,
            ]);

            $pivotDa->delete();
        });

        $this->azioniTecnico[$this->azioneKey($interventoId, $tecnicoDaId)] = null;
        $this->clearActionSelections();
        $this->dispatch('intervento-pianificato');
        $this->dispatch('toast', type: 'success', message: 'Intervento spostato al nuovo tecnico.');
    }

    public function aggiungiTecnicoIntervento(int $interventoId, int $tecnicoBaseId): void
    {
        $target = (int) ($this->azioniTecnico[$this->azioneKey($interventoId, $tecnicoBaseId)] ?? 0);
        if ($target <= 0) {
            $this->dispatch('toast', type: 'warning', message: 'Seleziona il tecnico da aggiungere.');
            return;
        }

        if ($target === $tecnicoBaseId) {
            $this->dispatch('toast', type: 'warning', message: 'Il tecnico selezionato è già quello corrente.');
            return;
        }

        $intervento = Intervento::find($interventoId);
        if (!$intervento || $intervento->stato !== 'Pianificato') {
            $this->dispatch('toast', type: 'error', message: 'Intervento non modificabile (non pianificato).');
            return;
        }

        $giaAssegnato = InterventoTecnico::where('intervento_id', $interventoId)
            ->where('user_id', $target)
            ->exists();
        if ($giaAssegnato) {
            $this->dispatch('toast', type: 'warning', message: 'Il tecnico è già pianificato su questo intervento.');
            return;
        }

        $pivotBase = InterventoTecnico::where('intervento_id', $interventoId)
            ->where('user_id', $tecnicoBaseId)
            ->first();
        if (!$pivotBase) {
            $this->dispatch('toast', type: 'error', message: 'Associazione tecnico base non trovata.');
            return;
        }

        $start = $pivotBase->scheduled_start_at;
        $end = $pivotBase->scheduled_end_at;
        if ($start && !$end) {
            $durata = max(0, (int) ($intervento->durata_minuti ?? 0));
            $end = Carbon::parse($start)->copy()->addMinutes($durata);
        }

        InterventoTecnico::create([
            'intervento_id' => $interventoId,
            'user_id' => $target,
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
            'started_at' => null,
            'ended_at' => null,
        ]);

        $this->azioniTecnico[$this->azioneKey($interventoId, $tecnicoBaseId)] = null;
        $this->clearActionSelections();
        $this->dispatch('intervento-pianificato');
        $this->dispatch('toast', type: 'success', message: 'Tecnico aggiunto alla pianificazione.');
    }

    public function spostaZonaGiorno(): void
    {
        $zona = trim((string) $this->bulkZona);
        $tecnicoDa = (int) ($this->bulkTecnicoDa ?? 0);
        $tecnicoA = (int) ($this->bulkTecnicoA ?? 0);
        $dataRef = $this->normalizeBulkData();

        if ($zona === '' || $tecnicoDa <= 0 || $tecnicoA <= 0) {
            $this->dispatch('toast', type: 'warning', message: 'Compila giorno, zona, tecnico origine e tecnico destinazione.');
            return;
        }
        if ($tecnicoDa === $tecnicoA) {
            $this->dispatch('toast', type: 'warning', message: 'Tecnico origine e destinazione devono essere diversi.');
            return;
        }

        $interventiIds = Intervento::query()
            ->whereDate('data_intervento', $dataRef)
            ->where('stato', 'Pianificato')
            ->where('zona', $zona)
            ->whereHas('tecnici', fn($q) => $q->where('users.id', $tecnicoDa))
            ->pluck('id');

        if ($interventiIds->isEmpty()) {
            $this->dispatch('toast', type: 'info', message: 'Nessun intervento pianificato da spostare nel giorno selezionato.');
            return;
        }

        $spostati = 0;
        $saltati = 0;

        DB::transaction(function () use ($interventiIds, $tecnicoDa, $tecnicoA, &$spostati, &$saltati) {
            foreach ($interventiIds as $interventoId) {
                $pivotDa = InterventoTecnico::where('intervento_id', $interventoId)
                    ->where('user_id', $tecnicoDa)
                    ->first();

                if (!$pivotDa) {
                    $saltati++;
                    continue;
                }

                $giaAssegnato = InterventoTecnico::where('intervento_id', $interventoId)
                    ->where('user_id', $tecnicoA)
                    ->exists();

                if ($giaAssegnato) {
                    $saltati++;
                    continue;
                }

                InterventoTecnico::create([
                    'intervento_id' => $interventoId,
                    'user_id' => $tecnicoA,
                    'started_at' => $pivotDa->started_at,
                    'ended_at' => $pivotDa->ended_at,
                    'scheduled_start_at' => $pivotDa->scheduled_start_at,
                    'scheduled_end_at' => $pivotDa->scheduled_end_at,
                ]);

                $pivotDa->delete();
                $spostati++;
            }
        });

        $giornoLabel = Carbon::parse($dataRef)->format('d/m/Y');
        if ($spostati > 0) {
            $this->clearActionSelections();
            $this->dispatch('intervento-pianificato');
            $this->dispatch('toast', type: 'success', message: "Spostamento {$giornoLabel}: {$spostati} interventi spostati, {$saltati} saltati.");
            return;
        }

        $this->dispatch('toast', type: 'info', message: "Nessun intervento spostato nel {$giornoLabel} ({$saltati} già assegnati al tecnico destinazione).");
    }

    public function spostaZonaSettimana(): void
    {
        $this->spostaZonaGiorno();
    }

    public function updatedBulkData(): void
    {
        $this->bulkZona = null;
    }

    private function tecniciDisponibili()
    {
        return User::query()
            ->whereHas('ruoli', fn($q) => $q->where('nome', 'Tecnico'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function zonePerData(string $date): array
    {
        return Intervento::query()
            ->whereDate('data_intervento', $date)
            ->whereNotNull('zona')
            ->where('zona', '!=', '')
            ->distinct()
            ->orderBy('zona')
            ->pluck('zona')
            ->values()
            ->all();
    }

    private function calendarioMensile(): array
    {
        $monthStart = Carbon::createFromFormat('Y-m', $this->meseRiferimento)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $rows = InterventoTecnico::query()
            ->join('interventi', 'intervento_tecnico.intervento_id', '=', 'interventi.id')
            ->join('users', 'intervento_tecnico.user_id', '=', 'users.id')
            ->whereBetween('interventi.data_intervento', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->select([
                'interventi.id as intervento_id',
                'interventi.data_intervento',
                'interventi.zona',
                'interventi.stato',
                'users.id as tecnico_id',
                'users.name as tecnico_nome',
            ])
            ->orderBy('interventi.data_intervento')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $dateKey = Carbon::parse($row->data_intervento)->toDateString();
            $zona = trim((string) ($row->zona ?? ''));
            $zona = $zona !== '' ? $zona : 'Senza zona';

            if (!isset($map[$dateKey])) {
                $map[$dateKey] = [];
            }
            if (!isset($map[$dateKey][$zona])) {
                $map[$dateKey][$zona] = [
                    'zona' => $zona,
                    'tecnici' => [],
                    'pianificati' => 0,
                    'completati' => 0,
                    'interventi' => [],
                ];
            }

            $map[$dateKey][$zona]['tecnici'][$row->tecnico_id] = $row->tecnico_nome;
            $map[$dateKey][$zona]['interventi'][$row->intervento_id] = true;

            if ((string) $row->stato === 'Completato') {
                $map[$dateKey][$zona]['completati']++;
            } else {
                $map[$dateKey][$zona]['pianificati']++;
            }
        }

        foreach ($map as $dateKey => $zoneRows) {
            foreach ($zoneRows as $zona => $payload) {
                $tecnici = array_values($payload['tecnici']);
                sort($tecnici);
                $map[$dateKey][$zona]['tecnici'] = $tecnici;
                $map[$dateKey][$zona]['tot_interventi'] = count($payload['interventi']);
            }
            ksort($map[$dateKey]);
            $map[$dateKey] = array_values($map[$dateKey]);
        }

        $weeks = [];
        $week = [];
        foreach (CarbonPeriod::create($gridStart, '1 day', $gridEnd) as $date) {
            $dateCarbon = Carbon::parse($date);
            $dateKey = $dateCarbon->toDateString();
            $week[] = [
                'data' => $dateCarbon,
                'in_month' => $dateCarbon->month === $monthStart->month,
                'rows' => $map[$dateKey] ?? [],
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    private function syncMeseRiferimentoDaSettimana(): void
    {
        $this->meseRiferimento = Carbon::parse($this->inizioSettimana)->format('Y-m');
    }

    private function azioneKey(int $interventoId, int $tecnicoId): string
    {
        return $interventoId . ':' . $tecnicoId;
    }

    private function clearActionSelections(): void
    {
        $this->azioniTecnico = [];
    }

    private function normalizeBulkData(): string
    {
        try {
            return Carbon::parse((string) $this->bulkData)->toDateString();
        } catch (\Throwable $e) {
            return Carbon::parse($this->inizioSettimana)->toDateString();
        }
    }
}
