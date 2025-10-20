<?php

namespace App\Livewire;

use Livewire\Component;

class Sidebar extends Component
{
   // app/Livewire/Sidebar.php
public function render()
{
    $user = auth()->user();
    $ruolo = $user->ruoli()->first()?->nome;

    return view('livewire.sidebar', [
        'ruolo' => $ruolo,
    ]);
}

}
