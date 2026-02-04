<?php
// app/Livewire/Fatturazione/GeneraFattura.php
namespace App\Livewire\Fatturazione;

use Livewire\Component;
use App\Models\Cliente;
use App\Services\Fatturazione\{BillingPreviewService, MsBusinessFatturaService};
use Carbon\Carbon;

class GeneraFattura extends Component
{
    public ?int $mese = null;
    public ?int $anno = null;
    public ?int $clienteId = null;
    public array $preview = [];
    public string $dataDocumento; // YYYY-MM-DD

    public function mount(?int $clienteId = null)
    {
        $now = now();
        $this->mese = $this->mese ?? $now->month;
        $this->anno = $this->anno ?? $now->year;
        $this->clienteId = $clienteId;
        $this->dataDocumento = $now->toDateString(); // cambia a fine mese se preferisci
    }

    public function generaPreview(BillingPreviewService $svc)
    {
        $cliente = Cliente::findOrFail($this->clienteId);
        // se annuale e il mese non coincide col mese_fatturazione, potresti bloccare
        if ($cliente->fatturazione_tipo === 'annuale'
            && $cliente->mese_fatturazione
            && (int)$cliente->mese_fatturazione !== (int)$this->mese) {
            $this->addError('mese','Il cliente è annuale: seleziona il suo mese di fatturazione.');
            return;
        }
        $this->preview = $svc->buildPreview($cliente, (int)$this->mese, (int)$this->anno);
    }
    public function creaFattura(\App\Services\Fatturazione\MsBusinessFatturaService $svc)
    {
        if (empty($this->preview)) {
            $this->addError('preview','Genera prima la preview.');
            return;
        }

        if (($this->preview['blocking_missing_price'] ?? true) === true) {
            $this->addError('preview','Prezzi mancanti: impossibile creare la fattura.');
            return;
        }

        $cliente = \App\Models\Cliente::findOrFail($this->clienteId);

        try {
            $refs = $svc->creaFatturaPerCliente(
                $cliente,
                (int)$this->mese,
                (int)$this->anno,
                \Carbon\Carbon::parse($this->dataDocumento)
            );

            session()->flash(
                'ok',
                "✅ Fattura creata correttamente: {$refs['tipork']}/{$refs['serie']}/{$refs['anno']}/{$refs['numero']}"
            );

            // azzero la preview per evitare doppio invio
            $this->preview = [];
        } catch (\Throwable $ex) {
            $this->addError('creazione', $ex->getMessage());
        }
    }

    public function render()
    {
         $clienti = Cliente::query()
            ->where(function($q){
                // include SEMPRE i semestrali; gli annuali solo se è il loro mese
                $q->where('fatturazione_tipo','semestrale')
                  ->orWhere(function($qq){
                      $qq->where('fatturazione_tipo','annuale')
                         ->whereNotNull('mese_fatturazione')
                         ->where('mese_fatturazione', (int)$this->mese);
                  });
            })
            ->whereHas('interventi', function($q){
                $q->where('stato','completato')
                  ->where('fatturato', false)
                  ->whereMonth('data_intervento', (int)$this->mese)
                  ->whereYear('data_intervento', (int)$this->anno);
            })
            ->orderBy('nome')
            ->get();
            
        return view('livewire.fatturazione.genera-fattura', [
            'clienti' => $clienti,
        ])->layout('layouts.app');
    }
}
