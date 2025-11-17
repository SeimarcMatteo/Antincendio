<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\{TipoEstintore, Colore};
use Illuminate\Support\Collection;
class ImpostaColore extends Component
{
    // NIENTE Collection pubbliche
    public array $selezioni = [];
    public array $originali = [];

    public function setColore(int $tipoId, int $coloreId): void
    {
        logger('setColore()', ['tipoId'=>$tipoId,'coloreId'=>$coloreId]);
        TipoEstintore::whereKey($tipoId)->update(['colore_id' => $coloreId]);
        $this->dispatch('toast', message: 'Colore aggiornato', type: 'success');
    }

    public function clearColore(int $tipoId): void
    {
         logger('clearColore()', ['tipoId'=>$tipoId]);
        TipoEstintore::whereKey($tipoId)->update(['colore_id' => null]);
        $this->dispatch('toast', message: 'Colore rimosso', type: 'success');
    }

    public function render()
    {

        $colori = Colore::orderBy('nome')->get(['id','nome','hex']);
        $tipi   = TipoEstintore::with('colore:id,nome,hex')
                    ->orderBy('tipo')->orderBy('kg')
                    ->get(['id','sigla','descrizione','tipo','kg','colore_id']);

        return view('livewire.tipi-estintori.imposta-colore', compact('tipi','colori'));
    }
}