<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\TipoEstintore;
use App\Models\Colore;

class ImpostaColore extends Component
{
    public $tipi;              // 👈 questa è la variabile usata nella Blade
    public $colori;

    public $selectedTipoId = null;
    public $selectedColoreId = null;

    public function mount()
    {
        $this->tipi   = TipoEstintore::with('colore')->orderBy('descrizione')->get();
        $this->colori = Colore::orderBy('nome')->get();

        if ($this->tipi->isNotEmpty()) {
            $this->selectedTipoId   = $this->tipi->first()->id;
            $this->selectedColoreId = $this->tipi->first()->colore_id;
        }
    }

    public function selectTipo($id)
    {
        $this->selectedTipoId = $id;

        $tipo = $this->tipi->firstWhere('id', $id);
        $this->selectedColoreId = $tipo?->colore_id;
    }

    public function updatedSelectedColoreId()
    {
        $this->salvaColore();
    }

    public function salvaColore()
    {
        if (!$this->selectedTipoId) {
            return;
        }

        $tipo = TipoEstintore::find($this->selectedTipoId);
        if (!$tipo) {
            return;
        }

        $tipo->colore_id = $this->selectedColoreId ?: null;
        $tipo->save();

        // ricarico la lista con le relazioni aggiornate
        $this->tipi = TipoEstintore::with('colore')->orderBy('descrizione')->get();

        session()->flash('message', 'Colore aggiornato correttamente.');
    }

    public function render()
    {
        return view('livewire.tipi-estintori.imposta-colore', [
            // 👇 così siamo sicuri che la Blade abbia queste variabili
            'tipi'    => $this->tipi,
            'colori'  => $this->colori,
        ]);
    }
}
