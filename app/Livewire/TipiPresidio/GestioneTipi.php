<?php

namespace App\Livewire\TipiPresidio;

use Livewire\Component;
use App\Models\TipoPresidio;

class GestioneTipi extends Component
{
    public string $categoria = 'Idrante';
    public string $nuovoNome = '';

    public function salva(): void
    {
        $nome = trim($this->nuovoNome);
        if ($nome === '') {
            $this->dispatch('toast', type: 'error', message: 'Inserisci un nome valido.');
            return;
        }

        $nome = mb_strtoupper($nome);

        TipoPresidio::firstOrCreate(
            ['categoria' => $this->categoria, 'nome' => $nome],
            []
        );

        $this->nuovoNome = '';
        $this->dispatch('toast', type: 'success', message: 'Tipologia salvata.');
    }

    public function elimina(int $id): void
    {
        TipoPresidio::where('id', $id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Tipologia rimossa.');
    }

    public function render()
    {
        $tipi = TipoPresidio::where('categoria', $this->categoria)
            ->orderBy('nome')
            ->get();

        return view('livewire.tipi-presidio.gestione-tipi', [
            'tipi' => $tipi,
        ]);
    }
}
