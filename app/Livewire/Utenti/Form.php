<?php

namespace App\Livewire\Utenti;

use App\Models\Ruolo;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;

    public $utenteId;
    public $name;
    public $email;
    public $password;
    public array $ruolo_ids = [];
    public $colore_ruolo = '#ff0000';
    public $profile_image;
    public $firma_tecnico_base64;
    public bool $hasFirmaTecnicoColumn = false;

    public function mount($id = null)
    {
        $this->hasFirmaTecnicoColumn = Schema::hasColumn('users', 'firma_tecnico_base64');

        if ($id) {
            $utente = User::findOrFail($id);
            $this->utenteId = $utente->id;
            $this->name = $utente->name;
            $this->email = $utente->email;
            $this->colore_ruolo = $utente->colore_ruolo;
            if ($this->hasFirmaTecnicoColumn) {
                $this->firma_tecnico_base64 = $utente->firma_tecnico_base64;
            }
            $this->ruolo_ids = $utente->ruoli()
                ->pluck('ruoli.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $this->utenteId,
            'password' => $this->utenteId ? 'nullable|min:6' : 'required|min:6',
            'ruolo_ids' => 'required|array|min:1',
            'ruolo_ids.*' => 'required|exists:ruoli,id',
            'colore_ruolo' => 'required|string',
            'profile_image' => 'nullable|image|max:1024',
            'firma_tecnico_base64' => 'nullable|string',
        ]);

        $ruoli = collect($this->ruolo_ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $payload = [
            'name' => $this->name,
            'email' => $this->email,
            'colore_ruolo' => $this->colore_ruolo,
            'password' => $this->password ? Hash::make($this->password) : User::find($this->utenteId)?->password,
        ];
        if ($this->hasFirmaTecnicoColumn) {
            $payload['firma_tecnico_base64'] = $this->normalizeFirma($this->firma_tecnico_base64);
        }

        $utente = User::updateOrCreate(['id' => $this->utenteId], $payload);

        if ($this->profile_image) {
            $path = $this->profile_image->store('immagini_utenti', 'public');
            $utente->update(['profile_image' => $path]);
        }

        $utente->ruoli()->sync($ruoli);

        return redirect()->route('utenti.index');
    }

    public function render()
    {
        return view('livewire.utenti.form', [
            'ruoli' => Ruolo::orderBy('nome')->get(),
        ])->layout('layouts.app');
    }

    private function normalizeFirma($raw): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        return str_starts_with($value, 'data:image/') ? $value : null;
    }
}
