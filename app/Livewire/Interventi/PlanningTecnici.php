<?php

namespace App\Livewire\Interventi;

use Livewire\Component;

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
}
