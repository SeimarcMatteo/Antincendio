<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\TipoEstintore;
use App\Models\Colore;

class ImpostaColore extends Component
{
   

    public function mount(): void
    {
        $this->caricaDati();
    }

    protected function caricaDati(): void
    {
        
    }

    public function salva($idTipo,$idColore)
    {
        $tipo = TipoEstintore::findOrFail($idTipo);
        $tipo->colore_id = $idColore;
        $tipo->save();
        
        session()->flash('message', 'Colori aggiornati correttamente.');
    }

    public function render()
    {
        $colori = Colore::orderBy('nome')->get();
        $tipi = TipoEstintore::with('colore')
            ->orderBy('descrizione')
            ->get();
            
        return view('livewire.tipi-estintori.imposta-colore',['colori' => $colori, 'tipi' => $tipi]);
    }
}
