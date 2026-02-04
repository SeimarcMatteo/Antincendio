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
        $query = Cliente::query();

        if ($this->searchReady && strlen(trim($this->search)) >= 3) {
            $raw  = trim($this->search);
            $like = "%{$raw}%";
        
            $query->where(function ($q) use ($raw, $like) {
                $q->where('nome', 'like', $like)
                  ->orWhere('p_iva', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('telefono', 'like', $like)
                  ->orWhere('indirizzo', 'like', $like)
                  ->orWhere('cap', 'like', $like)
                  ->orWhere('citta', 'like', $like)
                  ->orWhere('provincia', 'like', $like);
        
                // --- FIX per codice_esterno (copre INT e VARCHAR) ---
                if (ctype_digit($raw)) {
                    // match più veloce quando cerchi numeri
                    $q->orWhereRaw('CAST(codice_esterno AS CHAR) LIKE ?', ["{$raw}%"]);
                }
                // match generico
                $q->orWhereRaw('CAST(codice_esterno AS CHAR) LIKE ?', [$like]);
            });
        }

        $paginator = $query->orderBy('nome')->paginate($this->perPage);

        return view('livewire.clienti.index', [
            'clienti' => $paginator,
        ])->layout('layouts.app');
    }

}
