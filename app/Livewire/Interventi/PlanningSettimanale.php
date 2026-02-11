<?php

namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\User;
use App\Models\Intervento;
use App\Models\InterventoTecnico;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PlanningSettimanale extends Component
{
    public $inizioSettimana;
    public $giorn;
    protected $listeners = ['intervento-pianificato' => '$refresh'];
    public function mount()
    {
        $this->inizioSettimana = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
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
    
    public function annullaIntervento($id)
    {
        $intervento = \App\Models\Intervento::find($id);
    
        if ($intervento && $intervento->stato === 'Pianificato') {
            $intervento->tecnici()->detach(); // rimuove legami con tecnici
            $intervento->presidiIntervento()->delete(); // elimina legami con presidi
            $intervento->delete(); // elimina l’intervento

            $this->dispatch('toast', type: 'info', message: 'Intervento eliminato!');
        }else{
            $this->dispatch('toast', type: 'warning', message: 'Intervento non eliminabile in quanto COMPLETATO!');
        }
        $this->render();
    }
    

    public function settimanaPrecedente()
    {
        $this->inizioSettimana = Carbon::parse($this->inizioSettimana)->subWeek()->format('Y-m-d');
        $this->dispatch('setMeseAnno', 
            mese: Carbon::parse($this->inizioSettimana)->month, 
            anno: Carbon::parse($this->inizioSettimana)->year
        );
    }

    public function settimanaSuccessiva()
    {
        $this->inizioSettimana = Carbon::parse($this->inizioSettimana)->addWeek()->format('Y-m-d');
        $this->dispatch('setMeseAnno', 
            mese: Carbon::parse($this->inizioSettimana)->month, 
            anno: Carbon::parse($this->inizioSettimana)->year
        );
    }

    public function render()
    {
        $this->giorn= $this->giorniSettimana();
$gior = $this->giorn;
        $tecnici = User::whereHas('ruoli', function ($query) {
            $query->where('nome', 'Tecnico');
        })->with(['ruoli', 'interventi' => function ($q) use ($gior) {
            $q->whereBetween('data_intervento', [
                $gior->first()['data']->toDateString(),
                $gior->last()['data']->toDateString()
            ]);
            
        }, 'interventi.cliente', 'interventi.sede'])->get();
        

        return view('livewire.interventi.planning-settimanale', [
            'giorni' => $this->giorn,
            'tecnici' => $tecnici,
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
}
