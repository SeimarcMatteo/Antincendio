<?php

namespace App\Livewire\Impostazioni;

use App\Models\TipoEstintore;
use App\Models\TipoPresidio;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class CodiciArticolo extends Component
{
    public array $codiciEstintori = [];
    public array $codiciEstintoriFull = [];
    public array $codiciPresidi = [];
    public bool $hasCodiceArticoloFull = false;
    public bool $hasCodiceArticoloPresidi = false;

    public function mount(): void
    {
        $this->hasCodiceArticoloFull = Schema::hasColumn('tipi_estintori', 'codice_articolo_fatturazione_full');
        $this->hasCodiceArticoloPresidi = Schema::hasColumn('tipi_presidio', 'codice_articolo_fatturazione');
        $this->loadData();
    }

    public function salvaCodiceEstintore(int $idTipo): void
    {
        $tipo = TipoEstintore::find($idTipo);
        if (!$tipo) {
            $this->dispatch('toast', type: 'error', message: 'Tipologia estintore non trovata.');
            return;
        }

        $tipo->codice_articolo_fatturazione = $this->normalizeCode($this->codiciEstintori[$idTipo] ?? null);
        if ($this->hasCodiceArticoloFull) {
            $tipo->codice_articolo_fatturazione_full = $this->normalizeCode($this->codiciEstintoriFull[$idTipo] ?? null);
        }
        $tipo->save();

        $this->codiciEstintori[$idTipo] = (string) ($tipo->codice_articolo_fatturazione ?? '');
        if ($this->hasCodiceArticoloFull) {
            $this->codiciEstintoriFull[$idTipo] = (string) ($tipo->codice_articolo_fatturazione_full ?? '');
        }

        $this->dispatch('toast', type: 'success', message: 'Codici articolo estintore salvati.');
    }

    public function salvaCodicePresidio(int $idTipo): void
    {
        if (!$this->hasCodiceArticoloPresidi) {
            $this->dispatch('toast', type: 'error', message: 'Colonna codice articolo non presente su tipi_presidio.');
            return;
        }

        $tipo = TipoPresidio::find($idTipo);
        if (!$tipo) {
            $this->dispatch('toast', type: 'error', message: 'Tipologia presidio non trovata.');
            return;
        }

        $tipo->codice_articolo_fatturazione = $this->normalizeCode($this->codiciPresidi[$idTipo] ?? null);
        $tipo->save();

        $this->codiciPresidi[$idTipo] = (string) ($tipo->codice_articolo_fatturazione ?? '');
        $this->dispatch('toast', type: 'success', message: 'Codice articolo presidio salvato.');
    }

    public function salvaTuttiEstintori(): void
    {
        $ids = collect(array_keys($this->codiciEstintori))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $tipi = TipoEstintore::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $tipo = $tipi->get($id);
            if (!$tipo) {
                continue;
            }

            $tipo->codice_articolo_fatturazione = $this->normalizeCode($this->codiciEstintori[$id] ?? null);
            if ($this->hasCodiceArticoloFull) {
                $tipo->codice_articolo_fatturazione_full = $this->normalizeCode($this->codiciEstintoriFull[$id] ?? null);
            }
            $tipo->save();

            $this->codiciEstintori[$id] = (string) ($tipo->codice_articolo_fatturazione ?? '');
            if ($this->hasCodiceArticoloFull) {
                $this->codiciEstintoriFull[$id] = (string) ($tipo->codice_articolo_fatturazione_full ?? '');
            }
        }

        $this->dispatch('toast', type: 'success', message: 'Tutti i codici articolo estintori sono stati salvati.');
    }

    public function salvaTuttiPresidi(): void
    {
        if (!$this->hasCodiceArticoloPresidi) {
            $this->dispatch('toast', type: 'error', message: 'Colonna codice articolo non presente su tipi_presidio.');
            return;
        }

        $ids = collect(array_keys($this->codiciPresidi))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        $tipi = TipoPresidio::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $tipo = $tipi->get($id);
            if (!$tipo) {
                continue;
            }

            $tipo->codice_articolo_fatturazione = $this->normalizeCode($this->codiciPresidi[$id] ?? null);
            $tipo->save();
            $this->codiciPresidi[$id] = (string) ($tipo->codice_articolo_fatturazione ?? '');
        }

        $this->dispatch('toast', type: 'success', message: 'Tutti i codici articolo presidi sono stati salvati.');
    }

    public function render()
    {
        $queryEstintori = TipoEstintore::query()
            ->select(['id', 'sigla', 'descrizione', 'codice_articolo_fatturazione'])
            ->orderBy('descrizione')
            ->orderBy('sigla');
        if ($this->hasCodiceArticoloFull) {
            $queryEstintori->addSelect('codice_articolo_fatturazione_full');
        }
        $tipiEstintori = $queryEstintori->get();

        $queryPresidi = TipoPresidio::query()
            ->select(['id', 'categoria', 'nome'])
            ->orderBy('categoria')
            ->orderBy('nome');
        if ($this->hasCodiceArticoloPresidi) {
            $queryPresidi->addSelect('codice_articolo_fatturazione');
        }
        $tipiPresidio = $queryPresidi->get();

        return view('livewire.impostazioni.codici-articolo', [
            'tipiEstintori' => $tipiEstintori,
            'tipiPresidio' => $tipiPresidio,
        ]);
    }

    private function loadData(): void
    {
        $query = TipoEstintore::query()->select(['id', 'codice_articolo_fatturazione']);
        if ($this->hasCodiceArticoloFull) {
            $query->addSelect('codice_articolo_fatturazione_full');
        }

        $tipiEstintori = $query->get();
        $this->codiciEstintori = $tipiEstintori->mapWithKeys(fn (TipoEstintore $tipo) => [
            (int) $tipo->id => (string) ($tipo->codice_articolo_fatturazione ?? ''),
        ])->toArray();

        if ($this->hasCodiceArticoloFull) {
            $this->codiciEstintoriFull = $tipiEstintori->mapWithKeys(fn (TipoEstintore $tipo) => [
                (int) $tipo->id => (string) ($tipo->codice_articolo_fatturazione_full ?? ''),
            ])->toArray();
        }

        if ($this->hasCodiceArticoloPresidi) {
            $this->codiciPresidi = TipoPresidio::query()
                ->select(['id', 'codice_articolo_fatturazione'])
                ->get()
                ->mapWithKeys(fn (TipoPresidio $tipo) => [
                    (int) $tipo->id => (string) ($tipo->codice_articolo_fatturazione ?? ''),
                ])->toArray();
        } else {
            $this->codiciPresidi = [];
        }
    }

    private function normalizeCode($value): ?string
    {
        $code = trim((string) $value);
        return $code === '' ? null : mb_strtoupper($code);
    }
}
