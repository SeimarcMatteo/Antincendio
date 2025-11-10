<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\{TipoEstintore, Colore};
use Illuminate\Support\Collection;

class ImpostaColore extends Component
{
    public array $selezioni = [];
    public array $originali = [];

    /** @var Collection */
    public $colori;
    /** @var Collection */
    public $tipi;

    public array $hexById = [];
    public array $nomeById = [];

    public function mount(): void
    {
        $this->colori   = Colore::orderBy('nome')->get();
        $this->hexById  = $this->colori->pluck('hex', 'id')->toArray();
        $this->nomeById = $this->colori->pluck('nome', 'id')->toArray();

        $this->tipi = TipoEstintore::with('colore')
            ->orderBy('tipo')->orderBy('kg')->get();

        foreach ($this->tipi as $t) {
            $this->selezioni[$t->id] = $t->colore_id;
            $this->originali[$t->id] = $t->colore_id;
        }
    }

    /** Salva subito e aggiorna la UI */
    public function setColore(int $tipoId, ?int $coloreId): void
    {
        // debug: controlla nei log se viene richiamato
        logger('[ImpostaColore] setColore', ['tipoId' => $tipoId, 'coloreId' => $coloreId]);

        $coloreId = $coloreId ?: null;
        $this->selezioni[$tipoId] = $coloreId;

        if ($tipo = TipoEstintore::find($tipoId)) {
            $tipo->colore_id = $coloreId;
            $tipo->save();

            // aggiorna la collection in memoria
            $idx = $this->tipi->search(fn ($x) => (int)$x->id === (int)$tipoId);
            if ($idx !== false) {
                $t = $this->tipi[$idx];
                $t->colore_id = $coloreId;
                $t->setRelation('colore', $coloreId ? $this->colori->firstWhere('id', $coloreId) : null);
                $this->tipi[$idx] = $t;
            }
        }

        $this->originali[$tipoId] = $coloreId;
        // opzionale: piccolo feedback
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
