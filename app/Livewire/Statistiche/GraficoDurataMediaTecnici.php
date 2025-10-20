<?php

namespace App\Livewire\Statistiche;

use Livewire\Attributes\On;
use Livewire\Component;

use App\Models\InterventoTecnico;
use Illuminate\Support\Facades\DB;

class GraficoDurataMediaTecnici extends Component
{
    public $dati;
    protected $listeners = ['refreshChartData' => 'caricaDati'];

    public function mount()
    {
        $this->caricaDati();
    }

  
    public function caricaDati()
    {
        $this->dati = DB::table('intervento_tecnico')
            ->join('interventi', 'interventi.id', '=', 'intervento_tecnico.intervento_id')
            ->join('users', 'users.id', '=', 'intervento_tecnico.user_id')
            ->selectRaw('users.name, ROUND(AVG(interventi.durata_effettiva), 0) as media')
            ->where('interventi.stato', 'Completato')
            ->whereBetween('interventi.data_intervento', [
                session('statistiche_dataDa'), session('statistiche_dataA')
            ])
            ->groupBy('users.name')
            ->get();



        // Invia evento JS al browser
        $this->dispatch('refreshChart');

    }

    public function render()
    {
        return view('livewire.statistiche.grafico-durata-media-tecnici',[
            'dati' => $this->dati,
        ]);
    }
  
}