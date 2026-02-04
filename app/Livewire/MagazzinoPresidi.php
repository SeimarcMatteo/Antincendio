<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\GiacenzaPresidio;
use App\Models\TipoEstintore;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GiacenzePresidiExport;
use Barryvdh\DomPDF\Facade\Pdf;

class MagazzinoPresidi extends Component
{
    use WithPagination;

    public $categoriaFiltroInput = '';
    public $tipoFiltroInput = '';

    public $categoriaFiltro = '';
    public $tipoFiltro = '';

    public function applicaFiltri()
    {
        $this->categoriaFiltro = $this->categoriaFiltroInput;
        $this->tipoFiltro = $this->tipoFiltroInput;
        $this->resetPage();
    }


    public function aggiornaQuantita($id, $incremento = true)
    {
        $giacenza = GiacenzaPresidio::findOrFail($id);
        $giacenza->quantita += $incremento ? 1 : -1;
        $giacenza->quantita = max(0, $giacenza->quantita);
        $giacenza->save();
    }

    public function incrementa($id){
        $this->aggiornaQuantita($id,true);
    }
    public function decrementa($id){
        $this->aggiornaQuantita($id,false);
    }
    
    public function esportaExcel()
    {
        return Excel::download(new GiacenzePresidiExport, 'giacenze_presidi.xlsx');
    }

    public function esportaPdf()
    {
        $giacenze = $this->filtra()->get();
        $pdf = Pdf::loadView('exports.giacenze-presidi-pdf', ['giacenze' => $giacenze]);
        return response()->streamDownload(fn() => print($pdf->stream()), 'giacenze_presidi.pdf');
    }

    public function filtra()
    {
        return GiacenzaPresidio::with('tipoEstintore')
            ->when($this->categoriaFiltro, fn($q) => $q->where('categoria', $this->categoriaFiltro))
            ->when($this->tipoFiltro, fn($q) => $q->where('tipo_estintore_id', $this->tipoFiltro));
    }

    public function render()
    {
        return view('livewire.magazzino-presidi', [
            'giacenze' => $this->filtra()->paginate(20),
            'categorie' => GiacenzaPresidio::select('categoria')->distinct()->pluck('categoria')->filter(),
            'tipi' => TipoEstintore::orderBy('sigla')->get(),
        ]);
    }
}
