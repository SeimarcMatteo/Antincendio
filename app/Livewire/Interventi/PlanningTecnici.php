<?php

namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\User;
use App\Models\InterventoTecnico;
use Illuminate\Support\Facades\Auth;

class PlanningTecnici extends Component
{
    public $dataSelezionata;

    public function mount()
    {
        $this->dataSelezionata = now()->format('Y-m-d');
    }

    public function render()
    {
        $tecnici = User::role('Tecnico')
            ->with(['interventi' => function ($q) {
                $q->where('data_intervento', $this->dataSelezionata);
            }, 'interventi.cliente', 'interventi.sede'])
            ->get()
            ->map(function($tec){
                $tec->totale_minuti = $tec->interventi->sum('durata_minuti');
                return $tec;
            });

        return view('livewire.interventi.planning-tecnici', compact('tecnici'));
    }

    public function avviaIntervento(int $interventoId, int $tecnicoId): void
    {
        if (Auth::id() !== $tecnicoId) {
            return;
        }

        InterventoTecnico::where('intervento_id', $interventoId)
            ->where('user_id', $tecnicoId)
            ->update([
                'started_at' => now(),
            ]);
    }

    public function terminaIntervento(int $interventoId, int $tecnicoId): void
    {
        if (Auth::id() !== $tecnicoId) {
            return;
        }

        InterventoTecnico::where('intervento_id', $interventoId)
            ->where('user_id', $tecnicoId)
            ->update([
                'ended_at' => now(),
            ]);
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
}
