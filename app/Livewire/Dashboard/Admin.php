<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Models\Cliente;
use App\Models\Presidio;

use App\Models\Intervento;
use App\Models\User;

class Admin extends Component
{
    public $modificaMesi = [];

    public function getClientiSenzaMesiProperty()
    {
        return Cliente::whereHas('presidi')
            ->where(function ($q) {
                $q->whereNull('mesi_visita')
                    ->orWhereJsonLength('mesi_visita', 0);
            })
            ->get();
    }

    public function salvaMesi($clienteId)
    {
        $selezionati = array_keys($this->modificaMesi[$clienteId] ?? []);
        $mesi = collect($selezionati)->sort()->values()->toArray();

        Cliente::where('id', $clienteId)->update([
            'mesi_visita' => $mesi,
        ]);

        session()->flash('message', 'Mesi salvati per il cliente.');
        unset($this->modificaMesi[$clienteId]);
    }
    public function getInterventiDaCompletareCount()
    {
        return \App\Models\Intervento::whereIn('stato', ['Pianificato', 'In corso'])
            ->whereMonth('data_intervento', now()->month)
            ->whereYear('data_intervento', now()->year)
            ->whereHas('presidiIntervento', fn($q) =>
                $q->where('esito', 'non_verificato')
            )
            ->count();
    }
    public function getClientiDaPianificareCount()
    {
        $meseCorrente = str_pad(now()->month, 2, '0', STR_PAD_LEFT);

        return Cliente::with(['sedi.presidi', 'presidi'])
            ->whereHas('presidi')
            ->whereJsonContains('mesi_visita', $meseCorrente)
            ->get()
            ->filter(function ($cliente) {
                return $cliente->sedi->contains(fn($sede) =>
                    !$this->interventoEsistente($sede->cliente_id, $sede->id)
                ) || (
                    $cliente->presidi->whereNull('sede_id')->isNotEmpty()
                    && !$this->interventoEsistente($cliente->id, null)
                );
            })
            ->count();
    }
    public function interventoEsistente($clienteId, $sedeId = null): bool
    {
        return Intervento::where('cliente_id', $clienteId)
            ->when($sedeId, fn($q) => $q->where('sede_id', $sedeId))
            ->when(is_null($sedeId), fn($q) => $q->whereNull('sede_id'))
            ->whereMonth('data_intervento', now()->month)
            ->whereYear('data_intervento', now()->year)
            ->exists();
    }

    public function render()
    {   
        $numPresidi = Presidio::where('attivo','1')->count();
        $numUtenti = User::count();

        $inScadenza = $this->getInterventiDaCompletareCount() + $this->getClientiDaPianificareCount();

        return view('livewire.dashboard.admin',['numPresidi'=> $numPresidi, 'numUtenti' => $numUtenti, 'inScadenza'=>$inScadenza ])->layout('layouts.app');;
    }
}
