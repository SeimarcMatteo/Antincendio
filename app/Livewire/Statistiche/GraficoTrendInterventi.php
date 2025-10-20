<?php


namespace App\Livewire\Statistiche;

use Livewire\Attributes\On;
use Livewire\Component;

use App\Models\InterventoTecnico;
use Illuminate\Support\Facades\DB;

class GraficoTrendInterventi extends Component
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
            ->selectRaw("DATE_FORMAT(data_intervento, '%Y-%m') as mese, COUNT(*) as totale")
            ->where('stato', 'Completato')
            ->whereBetween('data_intervento', [
                session('statistiche_dataDa'), session('statistiche_dataA')
            ])
            ->groupBy('mese')
            ->orderBy('mese')
            ->get();





        // Invia evento JS al browser
        $this->dispatch('refreshChart');

    }
    
    public function render()
    {
        return view('livewire.statistiche.grafico-trend-interventi',[
            'dati' => $this->dati,
        ]);
    }
}
