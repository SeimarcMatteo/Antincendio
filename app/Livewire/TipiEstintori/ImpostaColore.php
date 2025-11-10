<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\{TipoEstintore, Colore};
use Illuminate\Support\Collection;

class ImpostaColore extends Component
{
    public array $selezioni = [];
    public array $originali = [];

    /** @var \Illuminate\Support\Collection */
    public Collection $colori;
    /** @var \Illuminate\Support\Collection */
    public Collection $tipi;

    public array $hexById = [];
    public array $nomeById = [];

    public function mount(): void
    {
        // prendo solo quello che serve
        $this->colori = Colore::query()
            ->orderBy('nome')
            ->get(['id','nome','hex']);

        $this->hexById  = $this->colori->pluck('hex', 'id')
            ->map(fn ($v) => $v ?: '#9CA3AF')->toArray();

        $this->nomeById = $this->colori->pluck('nome', 'id')->toArray();

        $this->tipi = TipoEstintore::query()
            ->with('colore:id,nome,hex')
            ->orderBy('tipo')->orderBy('kg')
            ->get(['id','sigla','descrizione','tipo','kg','colore_id']);

        foreach ($this->tipi as $t) {
            $this->selezioni[$t->id] = $t->colore_id;
            $this->originali[$t->id] = $t->colore_id;
        }
    }

    public function setColore(int $tipoId, ?int $coloreId): void
    {
        $coloreId = $coloreId ?: null;

        // aggiorna DB
        $tipo = TipoEstintore::find($tipoId);
        if (!$tipo) return;

        $tipo->colore_id = $coloreId;
        $tipo->save();

        // aggiorna stato UI
        $this->selezioni[$tipoId] = $coloreId;
        $this->originali[$tipoId] = $coloreId;

        // sync nella collection mostrata
        $this->tipi = $this->tipi->map(function ($x) use ($tipoId, $coloreId) {
            if ((int)$x->id === (int)$tipoId) {
                $x->colore_id = $coloreId;
                $x->setRelation('colore', $coloreId
                    ? $this->colori->firstWhere('id', $coloreId)
                    : null);
            }
            return $x;
        });

        $this->dispatch('notify', body: 'Colore aggiornato');
    }

    public function render()
    {
        return view('livewire.tipi-estintori.imposta-colore', [
            'tipi'   => $this->tipi,
            'colori' => $this->colori,
        ]);
    }
}
