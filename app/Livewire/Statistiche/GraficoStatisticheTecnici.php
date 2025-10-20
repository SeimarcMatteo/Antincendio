<?php

namespace App\Livewire\Statistiche;

use Livewire\Attributes\On;
use Livewire\Component;

use App\Models\InterventoTecnico;
use Illuminate\Support\Facades\DB;

class GraficoStatisticheTecnici extends Component
{
    public $datiTecnici;
    protected $listeners = ['refreshChartData' => 'caricaDati'];

    public function mount()
    {
        $this->caricaDati();
    }

  
    public function caricaDati()
    {
        $this->datiTecnici = InterventoTecnico::join('interventi', 'interventi.id', '=', 'intervento_tecnico.intervento_id')
            ->join('users', 'users.id', '=', 'intervento_tecnico.user_id')
            ->where('interventi.stato', 'Completato')
            ->whereBetween('interventi.data_intervento', [
                session('statistiche_dataDa', now()->startOfMonth()->format('Y-m-d')),
                session('statistiche_dataA', now()->endOfMonth()->format('Y-m-d')),
            ])
            ->selectRaw('users.name, COUNT(*) as totale_interventi, SUM(interventi.durata_effettiva) as durata_totale')
            ->groupBy('users.name')
            ->get();

        // Invia evento JS al browser
        $this->dispatch('refreshChart');

    }


    public function render()
    {
        return view('livewire.statistiche.grafico-statistiche-tecnici', [
            'datiTecnici' => $this->datiTecnici,
        ]);
    }
}
