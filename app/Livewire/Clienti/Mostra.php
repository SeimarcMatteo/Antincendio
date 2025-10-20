<?php

namespace App\Livewire\Clienti;

use App\Models\Cliente;
use Livewire\Component;

class Mostra extends Component
{
    public Cliente $cliente;
    public $modificaMesi = [];
    public $modificaMesiVisibile = [];
    public $mediaInterventiSenzaSede = null;

    public $fatturazione_tipo;
    public $mese_fatturazione;


    public function mount(Cliente $cliente)
    {
        $this->cliente = $cliente->load([
            'sedi.interventi' => fn($q) => $q->whereNotNull('durata_effettiva'),
        ]);

        $this->mediaInterventiSenzaSede = $cliente
            ->interventi()
            ->whereNull('sede_id')
            ->whereNotNull('durata_effettiva')
            ->avg('durata_effettiva');

        foreach ($this->cliente->sedi as $sede) {
            $sede->media_durata_effettiva = $sede->interventi->avg('durata_effettiva');
        }
        $this->fatturazione_tipo = $this->cliente->fatturazione_tipo;
        $this->mese_fatturazione = $this->cliente->mese_fatturazione;

        $this->modificaMesi['cliente'] = array_fill_keys(
            $this->parseMesi($this->cliente->mesi_visita),
            true
        );
        
        foreach ($this->cliente->sedi as $sede) {
            $this->modificaMesi[$sede->id] = array_fill_keys(
                $this->parseMesi($sede->mesi_visita),
                true
            );
        }
        
    }
    public function salvaFatturazione()
    {
        $this->validate([
            'fatturazione_tipo' => 'nullable|in:annuale,semestrale',
            'mese_fatturazione' => 'nullable|integer|min:1|max:12',
        ]);

        $this->cliente->update([
            'fatturazione_tipo' => $this->fatturazione_tipo,
            'mese_fatturazione' => $this->fatturazione_tipo === 'annuale' ? $this->mese_fatturazione : null,
        ]);

        $this->dispatch('toast', type: 'success', message: 'Fatturazione aggiornata con successo!');
    }

    private function parseMesi($value): array
    {
        if (is_array($value)) return $value;
    
        // Primo decode
        $decoded = json_decode($value, true);
    
        // Se ancora stringa JSON, decode una seconda volta
        if (is_string($decoded)) {
            return json_decode($decoded, true) ?? [];
        }
    
        return $decoded ?? [];
    }
    
    public function toggleMesiVisibili($chiave)
    {
        $this->modificaMesiVisibile[$chiave] = !($this->modificaMesiVisibile[$chiave] ?? false);
    }

    public function salvaMesi($sedeId = null)
    {
        $chiave = $sedeId ?? 'cliente';
        $selezionati = collect($this->modificaMesi[$chiave] ?? [])
                        ->filter(fn($v) => $v)
                        ->keys()
                        ->sort()
                        ->values()
                        ->toArray();

        $mesi = collect($selezionati)->sort()->values()->toArray();

        if ($sedeId) {
            $sede = \App\Models\Sede::find($sedeId);
            if ($sede && $sede->cliente_id === $this->cliente->id) {
                \Log::debug("Salvataggio mesi su Sede #{$sede->id}", $mesi);
                $sede->update(['mesi_visita' => $mesi]);
                \Log::debug("Valore mesi_visita dopo update: " . json_encode($sede->fresh()->mesi_visita));
            
            }
        } else {
            $this->cliente->update(['mesi_visita' => $mesi]);
        }

        $this->modificaMesiVisibile[$chiave] = false;
        $this->dispatch('toast', type: 'success',
                            message: 'Mesi Salvati con successo!');
    }

    public function vaiAiPresidi($sedeId = null)
    {
        return redirect()->route('presidi.gestione', [
            'clienteId' => $this->cliente->id,
            'sedeId' => $sedeId,
        ]);
    }

    public function render()
    {
        return view('livewire.clienti.mostra', [
            'mediaInterventiSenzaSede' => $this->mediaInterventiSenzaSede
                ? round($this->mediaInterventiSenzaSede)
                : null,
        ])->layout('layouts.app', ['title' => 'Dettaglio Cliente']);
    }
}
