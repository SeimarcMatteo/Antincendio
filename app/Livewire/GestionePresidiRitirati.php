<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PresidioRitirato;
use App\Models\Cliente;
use Livewire\WithPagination;

class GestionePresidiRitirati extends Component
{
    use WithPagination;

    public $categoriaFiltro = '';
    public $clienteFiltro = '';
    public $statoFiltro = '';

    protected $updatesQueryString = ['categoriaFiltro', 'clienteFiltro', 'statoFiltro'];

    public function aggiornaStato($id, $stato)
    {
        $presidio = PresidioRitirato::findOrFail($id);
        $presidio->stato = $stato;
        $presidio->save();

        session()->flash('message', 'Stato aggiornato correttamente.');
    }

    public function render()
    {
        $query = PresidioRitirato::with('presidio', 'presidio.tipoEstintore', 'cliente');

        if ($this->categoriaFiltro) {
            $query->whereHas('presidio', fn($q) => $q->where('categoria', $this->categoriaFiltro));
        }

        if ($this->clienteFiltro) {
            $query->where('cliente_id', $this->clienteFiltro);
        }

        if ($this->statoFiltro) {
            $query->where('stato', $this->statoFiltro);
        }

        return view('livewire.gestione-presidi-ritirati', [
            'presidi' => $query->orderByDesc('created_at')->paginate(15),
            'categorie' => PresidioRitirato::with('presidio')->get()->pluck('presidio.categoria')->unique()->filter()->sort(),
            'clienti' => Cliente::orderBy('nome')->get(),
        ]);
    }
}
