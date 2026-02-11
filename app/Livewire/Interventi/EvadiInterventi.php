<?php
namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\Intervento;
use Illuminate\Support\Facades\Auth;

class EvadiInterventi extends Component
{
    public $vista = 'schede';
    public $dataSelezionata;
    public $interventi = [];
    public array $noteByIntervento = [];

    public function mount()
    {
        $this->dataSelezionata = now()->format('Y-m-d');
        $this->caricaInterventi();
    }

    public function caricaInterventi()
    {
        $this->interventi = \App\Models\Intervento::with('cliente', 'sede')
            ->whereDate('data_intervento', $this->dataSelezionata)
            ->whereHas('tecnici', fn ($q) => $q->where('users.id', auth()->id()))
            ->get();

        foreach ($this->interventi as $int) {
            if (!array_key_exists($int->id, $this->noteByIntervento)) {
                $this->noteByIntervento[$int->id] = $int->note;
            }
        }
    }

    

    public function getInterventiDelGiornoProperty()
    {
        return Intervento::with('cliente', 'sede')
            ->whereDate('data_intervento', $this->dataSelezionata)
            ->whereHas('tecnici', fn ($q) => $q->where('users.id', Auth::id()))
            ->get();
    }

    public function apriIntervento($id)
    {
        return redirect()->route('interventi.evadi.dettaglio', ['intervento' => $id]);
    }

    public function updatedNoteByIntervento($value, $key): void
    {
        $id = (int) $key;
        if (!$id) return;
        $intervento = Intervento::find($id);
        if (!$intervento) return;
        $intervento->note = $value;
        $intervento->save();
    }

    public function render()
    {
        return view('livewire.interventi.evadi-interventi')->layout('layouts.app'); ;
    }
}
