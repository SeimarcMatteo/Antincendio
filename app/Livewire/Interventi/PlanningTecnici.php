<?php

namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\User;
use App\Models\Intervento;

class PlanningTecnici extends Component
{
    public $dataSelezionata;
    public array $noteByIntervento = [];

    public function mount()
    {
        $this->dataSelezionata = now()->format('Y-m-d');
    }

    public function render()
    {
        $tecnici = User::role('Tecnico')
            ->with(['interventi' => function ($q) {
                $q->where('data_intervento', $this->dataSelezionata);
            }, 'interventi.cliente', 'interventi.sede'])
            ->get()
            ->map(function($tec){
                $tec->totale_minuti = $tec->interventi->sum('durata_minuti');
                return $tec;
            });

        foreach ($tecnici as $tec) {
            foreach ($tec->interventi as $int) {
                if (!array_key_exists($int->id, $this->noteByIntervento)) {
                    $this->noteByIntervento[$int->id] = $int->note;
                }
            }
        }

        return view('livewire.interventi.planning-tecnici', compact('tecnici'));
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

    public function formatMinutes($minutes): string
    {
        $minutes = max(0, (int) $minutes);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours <= 0) {
            return $mins . ' min';
        }
        if ($mins === 0) {
            return $hours . ' h';
        }
        return $hours . ' h ' . $mins . ' min';
    }
}
