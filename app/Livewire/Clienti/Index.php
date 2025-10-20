<?php

namespace App\Livewire\Clienti;

use App\Models\Cliente;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $searchReady = false;
    public $perPage;
    public $perPageOptions = [5, 10, 20, 50, 100];

    public function mount()
    {
        $this->perPage = session('clienti_per_page', 10);
    }

    public function aggiornaRicerca()
    {
        $this->searchReady = true;
        $this->resetPage();
    }

    public function updatedPerPage($value)
    {
        session(['clienti_per_page' => $value]);
        $this->resetPage();
    }

    public function normalize($string)
    {
        return Str::of($string)
            ->lower()
            ->replaceMatches('/[^a-z0-9]/', '')
            ->value();
    }

  

    public function render()
    {
        if ($this->searchReady && strlen($this->search) >= 3) {
            $search = $this->normalize($this->search);

            $query = Cliente::all()->filter(function ($cliente) use ($search) {
                $concatenated = collect([
                    $cliente->nome,
                    $cliente->p_iva,
                    $cliente->email,
                    $cliente->telefono,
                    $cliente->indirizzo,
                    $cliente->cap,
                    $cliente->citta,
                    $cliente->provincia,
                ])->implode(' ');

                return str_contains($this->normalize($concatenated), $search);
            });

            $paginator = new LengthAwarePaginator(
                $query->forPage($this->getPage(), $this->perPage),
                $query->count(),
                $this->perPage,
                $this->getPage(),
                ['path' => request()->url(), 'query' => request()->query()]
            );
        } else {
            $paginator = Cliente::orderBy('nome')
                ->paginate($this->perPage);
        }

        return view('livewire.clienti.index', [
            'clienti' => $paginator,
        ])->layout('layouts.app');
    }
}
