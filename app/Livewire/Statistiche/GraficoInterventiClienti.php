<?php

namespace App\Livewire\Statistiche;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class GraficoInterventiClienti extends Component
{
    public $dati;

    protected $listeners = ['refreshChartData' => 'caricaDati'];

    public function mount()
    {
        $this->caricaDati();
    }

    public function caricaDati()
    {
        $this->dati = DB::table('interventi')
            ->join('clienti', 'interventi.cliente_id', '=', 'clienti.id')
            ->selectRaw('clienti.nome, COUNT(*) as totale')
            ->where('interventi.stato', 'Completato')
            ->whereBetween('interventi.data_intervento', [
                session('statistiche_dataDa', now()->startOfMonth()->format('Y-m-d')),
                session('statistiche_dataA', now()->endOfMonth()->format('Y-m-d')),
            ])
            ->groupBy('clienti.nome')
            ->orderByDesc('totale')
            ->get();

        $this->dispatch('refreshChart');
    }

    public function render()
    {
        return view('livewire.statistiche.grafico-interventi-clienti', [
            'dati' => $this->dati,
        ]);
    }
}
