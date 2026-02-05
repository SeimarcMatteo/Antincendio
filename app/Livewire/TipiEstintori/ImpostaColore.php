<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\TipoEstintore;
use App\Models\Colore;

class ImpostaColore extends Component
{
    public array $coloreSelezionato = [];

    public function mount(): void
    {
        $this->coloreSelezionato = TipoEstintore::pluck('colore_id', 'id')->toArray();
    }

    public function updatedColoreSelezionato($value, $key): void
    {
        $this->salva((int) $key, $value);
    }

    public function salva(int $idTipo, $idColore = null): void
    {
        $tipo = TipoEstintore::findOrFail($idTipo);
        $tipo->colore_id = $idColore ?: null;
        $tipo->save();

        $this->coloreSelezionato[$idTipo] = $tipo->colore_id;
        $this->dispatch('toast', type: 'success', message: 'Colore salvato.');
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
