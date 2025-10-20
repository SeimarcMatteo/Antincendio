<?php

namespace App\Livewire\Utenti;

use App\Models\Ruolo;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;

    public $utenteId;
    public $name;
    public $email;
    public $password;
    public $ruolo_id;
    public $colore_ruolo = '#ff0000';
    public $profile_image;

    public function mount($id = null)
    {
        if ($id) {
            $utente = User::findOrFail($id);
            $this->utenteId = $utente->id;
            $this->name = $utente->name;
            $this->email = $utente->email;
            $this->colore_ruolo = $utente->colore_ruolo;
            $this->ruolo_id = $utente->ruoli()->first()?->id;
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $this->utenteId,
            'password' => $this->utenteId ? 'nullable|min:6' : 'required|min:6',
            'ruolo_id' => 'required|exists:ruoli,id',
            'colore_ruolo' => 'required|string',
            'profile_image' => 'nullable|image|max:1024',
        ]);

        $utente = User::updateOrCreate(
            ['id' => $this->utenteId],
            [
                'name' => $this->name,
                'email' => $this->email,
                'colore_ruolo' => $this->colore_ruolo,
                'password' => $this->password ? Hash::make($this->password) : User::find($this->utenteId)?->password,
            ]
        );

        if ($this->profile_image) {
            $path = $this->profile_image->store('immagini_utenti', 'public');
            $utente->update(['profile_image' => $path]);
        }

        $utente->ruoli()->sync([$this->ruolo_id]);

        return redirect()->route('utenti.index');
    }

    public function render()
    {
        return view('livewire.utenti.form', [
            'ruoli' => Ruolo::all(),
        ])->layout('layouts.app');
    }
}
