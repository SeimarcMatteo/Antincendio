<?php

namespace App\Livewire;

use Livewire\Component;

class StatisticheAvanzate extends Component
{
    public $graficoSelezionato = 'clienti';

    protected $listeners = ['refreshChartData' => '$refresh']; // per forzare rerender su aggiornamento filtri

    public function render()
    {
        return view('livewire.statistiche-avanzate');
    }
}
