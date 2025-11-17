<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\TipoEstintore;
use App\Models\Colore;

class ImpostaColore extends Component
{
    public function setColore(int $tipoId, int $coloreId): void
    {
        \Log::info('[ImpostaColore] setColore', compact('tipoId','coloreId'));
        TipoEstintore::whereKey($tipoId)->update(['colore_id' => $coloreId]);
        $this->dispatch('toast', message: 'Colore aggiornato', type: 'success');
    }

    public function clearColore(int $tipoId): void
    {
        \Log::info('[ImpostaColore] clearColore', compact('tipoId'));
        TipoEstintore::whereKey($tipoId)->update(['colore_id' => null]);
        $this->dispatch('toast', message: 'Colore rimosso', type: 'info');
    }

    public function render()
    {
        return view('livewire.tipi-estintori.imposta-colore', [
            'colori' => Colore::orderBy('nome')->get(['id','nome','hex']),
            'tipi'   => TipoEstintore::with('colore:id,nome,hex')
                          ->orderBy('tipo')->orderBy('kg')
                          ->get(['id','sigla','descrizione','tipo','kg','colore_id']),
        ]);
    }
}
