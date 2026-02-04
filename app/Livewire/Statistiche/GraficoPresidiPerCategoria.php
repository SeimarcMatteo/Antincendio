<?php


namespace App\Livewire\Statistiche;

use Livewire\Attributes\On;
use Livewire\Component;

use App\Models\InterventoTecnico;
use Illuminate\Support\Facades\DB;

class GraficoPresidiPerCategoria extends Component
{

    public $dati;
    protected $listeners = ['refreshChartData' => 'caricaDati'];

    public function mount()
    {
        $this->caricaDati();
    }

  
    public function caricaDati()
    {
        $this->dati = DB::table('presidi_intervento')
            ->join('presidi', 'presidi.id', '=', 'presidi_intervento.presidio_id')
            ->join('interventi', 'interventi.id', '=', 'presidi_intervento.intervento_id')
            ->selectRaw('presidi.categoria, COUNT(*) as totale')
            ->where('interventi.stato', 'Completato')
            ->whereBetween('interventi.data_intervento', [
                session('statistiche_dataDa'), session('statistiche_dataA')
            ])
            ->groupBy('presidi.categoria')
            ->get();




        // Invia evento JS al browser
        $this->dispatch('refreshChart');

    }
    public function render()
    {
        return view('livewire.statistiche.grafico-presidi-per-categoria',[
            'dati' => $this->dati,
        ]);
    }
}
