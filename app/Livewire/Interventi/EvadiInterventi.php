<?php
namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\Intervento;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

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
        $user = Auth::user();
        $this->interventi = $user
            ? $user->interventi()
                ->with('cliente', 'sede')
                ->whereDate('data_intervento', $this->dataSelezionata)
                ->orderByRaw('intervento_tecnico.scheduled_start_at IS NULL')
                ->orderBy('intervento_tecnico.scheduled_start_at')
                ->orderBy('interventi.id')
                ->get()
            : collect();

        foreach ($this->interventi as $int) {
            if (!array_key_exists($int->id, $this->noteByIntervento)) {
                $this->noteByIntervento[$int->id] = $int->note;
            }
        }
    }

    public function updatedDataSelezionata(): void
    {
        $this->caricaInterventi();
    }

    public function giornoPrecedente(): void
    {
        $this->dataSelezionata = Carbon::parse($this->dataSelezionata)->subDay()->format('Y-m-d');
        $this->caricaInterventi();
    }

    public function giornoSuccessivo(): void
    {
        $this->dataSelezionata = Carbon::parse($this->dataSelezionata)->addDay()->format('Y-m-d');
        $this->caricaInterventi();
    }

    public function vaiAOggi(): void
    {
        $this->dataSelezionata = now()->format('Y-m-d');
        $this->caricaInterventi();
    }

    public function prossimoGiornoPianificato(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $nextDate = $user->interventi()
            ->where('stato', 'Pianificato')
            ->whereDate('data_intervento', '>', $this->dataSelezionata)
            ->orderBy('data_intervento')
            ->value('data_intervento');

        if (!$nextDate) {
            $this->dispatch('toast', type: 'info', message: 'Nessun intervento pianificato successivo.');
            return;
        }

        $this->dataSelezionata = Carbon::parse($nextDate)->format('Y-m-d');
        $this->caricaInterventi();
    }

    public function precedenteGiornoPianificato(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $prevDate = $user->interventi()
            ->where('stato', 'Pianificato')
            ->whereDate('data_intervento', '<', $this->dataSelezionata)
            ->orderByDesc('data_intervento')
            ->value('data_intervento');

        if (!$prevDate) {
            $this->dispatch('toast', type: 'info', message: 'Nessun intervento pianificato precedente.');
            return;
        }

        $this->dataSelezionata = Carbon::parse($prevDate)->format('Y-m-d');
        $this->caricaInterventi();
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

    public function salvaNoteIntervento(int $id): void
    {
        if (!$id) return;
        $intervento = Intervento::find($id);
        if (!$intervento) return;
        $intervento->note = $this->noteByIntervento[$id] ?? null;
        $intervento->save();
        $this->dispatch('toast', type: 'success', message: 'Note intervento salvate');
    }

    public function render()
    {
        return view('livewire.interventi.evadi-interventi')->layout('layouts.app'); ;
    }
}
