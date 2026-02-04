<?php

namespace App\Livewire\Presidi;

use App\Models\Cliente;
use App\Models\Sede;
use Livewire\Component;

class Ricerca extends Component
{
    public $clienti;
    public $sedi = [];

    public $clienteId;
    public $sedeId;

    public function mount()
    {
        $this->clienti = Cliente::orderBy('nome')->get();
    }

    public function updatedClienteId()
    {
        $this->sedi = Sede::where('cliente_id', $this->clienteId)->orderBy('nome')->get();
        $this->sedeId = null;
    }

    public function vaiAGestionePresidi()
    {
        if ($this->clienteId && $this->sedeId) {
            return redirect()->route('presidi.index', [
                'cliente' => $this->clienteId,
                'sede' => $this->sedeId,
            ]);
        }

        session()->flash('error', 'Seleziona sia il cliente che la sede.');
    }
    public function vaiAllaGestione()
{
    $this->validate([
        'cliente_id' => 'required|exists:clienti,id',
    ]);

    $cliente = Cliente::find($this->cliente_id);

    // Se non ci sono sedi, usa i dati dell'anagrafica cliente come "sede"
    if ($cliente->sedi->isEmpty()) {
        session()->flash('cliente_id', $cliente->id);
        session()->flash('sede_custom', [
            'nome' => 'Sede principale',
            'indirizzo' => $cliente->indirizzo,
            'cap' => $cliente->cap,
            'citta' => $cliente->citta,
            'provincia' => $cliente->provincia,
        ]);

        return redirect()->route('presidi.gestione', ['clienteId' => $cliente->id, 'sedeId' => 'principale']);
    }

    $this->validate([
        'sede_id' => 'required|exists:sedi,id',
    ]);

    return redirect()->route('presidi.gestione', [
        'clienteId' => $this->cliente_id,
        'sedeId' => $this->sede_id,
    ]);
}

    public function render()
    {
        return view('livewire.presidi.ricerca')->layout('layouts.app');

    }
}
