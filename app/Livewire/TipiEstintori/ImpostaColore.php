<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\TipoEstintore;
use App\Models\Colore;
use Illuminate\Support\Facades\Schema;

class ImpostaColore extends Component
{
    public array $coloreSelezionato = [];
    public array $codiceArticolo = [];
    public array $codiceArticoloFull = [];
    public bool $hasCodiceArticoloFull = false;

    public function mount(): void
    {
        $this->hasCodiceArticoloFull = Schema::hasColumn('tipi_estintori', 'codice_articolo_fatturazione_full');

        $query = TipoEstintore::query()->select(['id', 'colore_id', 'codice_articolo_fatturazione']);
        if ($this->hasCodiceArticoloFull) {
            $query->addSelect('codice_articolo_fatturazione_full');
        }

        $tipi = $query->get();

        $this->coloreSelezionato = $tipi->mapWithKeys(fn (TipoEstintore $tipo) => [
            (int) $tipo->id => $tipo->colore_id,
        ])->toArray();

        $this->codiceArticolo = $tipi->mapWithKeys(fn (TipoEstintore $tipo) => [
            (int) $tipo->id => (string) ($tipo->codice_articolo_fatturazione ?? ''),
        ])->toArray();

        if ($this->hasCodiceArticoloFull) {
            $this->codiceArticoloFull = $tipi->mapWithKeys(fn (TipoEstintore $tipo) => [
                (int) $tipo->id => (string) ($tipo->codice_articolo_fatturazione_full ?? ''),
            ])->toArray();
        }
    }

    public function updatedColoreSelezionato($value, $key): void
    {
        $this->salva((int) $key, $value);
    }

    public function updatedCodiceArticolo($value, $key): void
    {
        $this->salvaCodiceArticolo((int) $key, $value, false);
    }

    public function updatedCodiceArticoloFull($value, $key): void
    {
        if (!$this->hasCodiceArticoloFull) {
            return;
        }
        $this->salvaCodiceArticolo((int) $key, $value, true);
    }

    public function salva(int $idTipo, $idColore = null): void
    {
        $tipo = TipoEstintore::findOrFail($idTipo);
        $tipo->colore_id = $idColore ?: null;
        $tipo->save();

        $this->coloreSelezionato[$idTipo] = $tipo->colore_id;
        $this->dispatch('toast', type: 'success', message: 'Colore salvato.');
    }

    public function salvaCodiceArticolo(int $idTipo, $value, bool $isFull): void
    {
        $tipo = TipoEstintore::findOrFail($idTipo);
        $clean = trim((string) $value);
        $clean = $clean === '' ? null : $clean;

        if ($isFull) {
            if (!$this->hasCodiceArticoloFull) {
                return;
            }
            $tipo->codice_articolo_fatturazione_full = $clean;
            $tipo->save();

            $this->codiceArticoloFull[$idTipo] = (string) ($tipo->codice_articolo_fatturazione_full ?? '');
            $this->dispatch('toast', type: 'success', message: 'Codice articolo FULL SERVICE salvato.');
            return;
        }

        $tipo->codice_articolo_fatturazione = $clean;
        $tipo->save();

        $this->codiceArticolo[$idTipo] = (string) ($tipo->codice_articolo_fatturazione ?? '');
        $this->dispatch('toast', type: 'success', message: 'Codice articolo NOLEGGIO salvato.');
    }

    public function render()
    {
        $colori = Colore::orderBy('nome')->get();
        $tipi = TipoEstintore::with('colore')
            ->orderBy('descrizione')
            ->get();
            
        return view('livewire.tipi-estintori.imposta-colore', [
            'colori' => $colori,
            'tipi' => $tipi,
            'hasCodiceArticoloFull' => $this->hasCodiceArticoloFull,
        ]);
    }
}
