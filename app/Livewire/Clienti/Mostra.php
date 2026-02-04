<?php

namespace App\Livewire\Clienti;

use App\Models\Cliente;
use App\Models\Sede;
use Livewire\Component;
use Illuminate\Support\Str;

class Mostra extends Component
{
    public Cliente $cliente;
    public $modificaMesi = [];
    public $modificaMesiVisibile = [];
    public $mediaInterventiSenzaSede = null;

    public $fatturazione_tipo;
    public $mese_fatturazione;
    public bool $noteEdit = false;
    public ?string $note = null;

    // ---- ZONA
    public string $zonaInput = '';
    public array $zoneSuggestions = [];

    public function mount(Cliente $cliente)
    {
        $this->note = $this->cliente->note; // valore iniziale
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
        $this->mese_fatturazione  = $this->cliente->mese_fatturazione;

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

        // ---- ZONA: valore iniziale e suggerimenti unione Cliente+Sedi
        $this->zonaInput = (string) ($this->cliente->zona ?? '');
        $this->refreshZoneSuggestions();
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

        $decoded = json_decode($value, true);
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
            $sede = Sede::find($sedeId);
            if ($sede && $sede->cliente_id === $this->cliente->id) {
                \Log::debug("Salvataggio mesi su Sede #{$sede->id}", $mesi);
                $sede->update(['mesi_visita' => $mesi]);
                \Log::debug("Valore mesi_visita dopo update: " . json_encode($sede->fresh()->mesi_visita));
            }
        } else {
            $this->cliente->update(['mesi_visita' => $mesi]);
        }

        $this->modificaMesiVisibile[$chiave] = false;
        $this->dispatch('toast', type: 'success', message: 'Mesi Salvati con successo!');
    }

    public function vaiAiPresidi($sedeId = null)
    {
        return redirect()->route('presidi.gestione', [
            'clienteId' => $this->cliente->id,
            'sedeId'    => $sedeId,
        ]);
    }

    // ---- ZONA: salva su Cliente + Sedi e ricalcola suggerimenti
    public function salvaZona(): void
    {
        $this->validate([
            'zonaInput' => 'nullable|string|max:100',
        ]);

        $zona = Str::of($this->zonaInput ?? '')
            ->squish()
            ->value();
        $zona = $zona === '' ? null : $zona;

        // salva su cliente
        $this->cliente->update(['zona' => $zona]);

        // salva anche su tutte le SEDI del cliente
        if ($zona) {
            // default: compila SOLO le sedi con zona vuota/null
            Sede::where('cliente_id', $this->cliente->id)
                ->where(fn($q) => $q->whereNull('zona')->orWhere('zona', ''))
                ->update(['zona' => $zona]);

            // Se vuoi forzare TUTTE le sedi del cliente a questa zona, sostituisci il blocco sopra con:
            // Sede::where('cliente_id', $this->cliente->id)->update(['zona' => $zona]);
        }

        $this->refreshZoneSuggestions();

        $this->dispatch('toast', type: 'success', message: 'Zona aggiornata su cliente e sedi.');
    }

    private function refreshZoneSuggestions(): void
    {
        $clienteZones = Cliente::query()
            ->whereNotNull('zona')
            ->where('zona', '!=', '')
            ->pluck('zona');

        $sedeZones = Sede::query()
            ->whereNotNull('zona')
            ->where('zona', '!=', '')
            ->pluck('zona');

        $this->zoneSuggestions = $clienteZones
            ->merge($sedeZones)
            ->map(fn($z) => trim(preg_replace('/\s+/', ' ', (string)$z)))
            ->filter()
            ->unique(fn($z) => mb_strtolower($z)) // distinct case-insensitive
            ->sort()
            ->values()
            ->toArray();
    }

    public function render()
    {
        return view('livewire.clienti.mostra', [
            'mediaInterventiSenzaSede' => $this->mediaInterventiSenzaSede
                ? round($this->mediaInterventiSenzaSede)
                : null,
        ])->layout('layouts.app', ['title' => 'Dettaglio Cliente']);
    }

    public function toggleNote(): void
    {
        $this->noteEdit = !$this->noteEdit;
        if ($this->noteEdit === true) {
        $this->note = $this->cliente->note; // ricarico quello attuale
        }
    }

    public function salvaNote(): void
    {
        $this->validate(['note' => 'nullable|string|max:5000']);
        $this->cliente->update(['note' => $this->note]);
        $this->noteEdit = false;
        $this->dispatch('toast', type: 'success', message: 'Note salvate.');
    }

}
