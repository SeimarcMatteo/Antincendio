<?php

namespace App\Livewire\Statistiche;

use Livewire\Component;

class FiltriStatisticheAvanzate extends Component
{
    public $dataDa;
    public $dataA;

    public function mount()
    {
        $this->dataDa = now()->startOfMonth()->format('Y-m-d');
        $this->dataA = now()->endOfMonth()->format('Y-m-d');
    }

    public function aggiorna()
    {
        session([
            'statistiche_dataDa' => $this->dataDa,
            'statistiche_dataA'  => $this->dataA,
        ]);
    
        $this->dispatch('refreshChartData');
    }
    

    public function render()
    {
        return view('livewire.statistiche.filtri-statistiche-avanzate');
    }
}
