<?php

namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\Cliente;
use App\Models\Intervento;
use Carbon\Carbon;

class ClientiDaPianificare extends Component
{
    public $meseSelezionato;
    public $annoSelezionato;

    public function mount()
    {
        $this->meseSelezionato = now()->format('m');
        $this->annoSelezionato = now()->year;
    }

    public function getClientiDaPianificareProperty()
    {
        return Cliente::with(['sedi', 'presidi'])
            ->whereHas('presidi') // clienti che hanno presidi
            ->whereJsonContains('mesi_visita', $this->meseSelezionato) // mese attivo
            ->get()
            ->filter(function ($cliente) {
                return $cliente->sedi->filter(function ($sede) use ($cliente) {
                    return !$this->interventoEsistente($cliente->id, $sede->id);
                })->isNotEmpty() || !$this->interventoEsistente($cliente->id, null);
            });
    }

    public function interventoEsistente($clienteId, $sedeId = null)
    {
        return Intervento::where('cliente_id', $clienteId)
            ->where(function ($q) use ($sedeId) {
                if ($sedeId === null) {
                    $q->whereNull('sede_id');
                } else {
                    $q->where('sede_id', $sedeId);
                }
            })
            ->whereMonth('data_intervento', $this->meseSelezionato)
            ->whereYear('data_intervento', $this->annoSelezionato)
            ->exists();
    }

    public function pianifica($clienteId, $sedeId)
    {
        $this->dispatch('apri-pianificazione', [
            'cliente_id' => $clienteId,
            'sede_id' => $sedeId,
            'mese' => $this->meseSelezionato,
            'anno' => $this->annoSelezionato,
        ]);
    }

    public function render()
    {
        return view('livewire.interventi.clienti-da-pianificare', [
            'clienti' => $this->clientiDaPianificare,
        ]);
    }
}
