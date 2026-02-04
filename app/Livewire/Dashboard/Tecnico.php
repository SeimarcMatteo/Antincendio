<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class Tecnico extends Component
{
    public function render()
    {
        return view('livewire.dashboard.tecnico')->layout('layouts.app');
    }
}
