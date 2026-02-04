<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;

class Admin extends Component
{
    public function render()
    {
        return view('livewire.dashboard.admin')
    ->layout('layouts.app'); // ✅ GIUSTO

    }
}
