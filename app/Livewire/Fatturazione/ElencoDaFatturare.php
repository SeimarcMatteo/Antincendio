<?php
// app/Livewire/Fatturazione/ElencoDaFatturare.php
namespace App\Livewire\Fatturazione;

use Livewire\Component;
use App\Models\Cliente;
use App\Models\Intervento;
use App\Services\Fatturazione\{BillingPreviewService, MsBusinessFatturaService};
use Carbon\Carbon;

class ElencoDaFatturare extends Component
{
    public ?int $mese = null;
    public ?int $anno = null;
    public string $dataDocumento; // data doc default = oggi

    /** @var array<int, array>  key: cliente_id, value: preview payload */
    public array $previews = [];
    public float $totaleGenerale = 0.0;

    public function mount()
    {
        $now = now();
        $this->mese = $this->mese ?? $now->month;
        $this->anno = $this->anno ?? $now->year;
        $this->dataDocumento = $now->toDateString();
    }

    public function genera(BillingPreviewService $svc)
    {
        $this->previews = [];
        $this->totaleGenerale = 0.0;

        // 1) prendi solo i clienti che hanno interventi da fatturare nel mese/anno
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

        // 2) genera preview per ciascun cliente
        foreach ($clienti as $c) {
            $pv = $svc->buildPreview($c, (int)$this->mese, (int)$this->anno);
            // salta chi non ha righe (per sicurezza)
            if (empty($pv['righe'])) continue;

            $this->previews[$c->id] = $pv;
            $this->totaleGenerale += (float)$pv['totale'];
        }
    }

    public function creaFatturaCliente(int $clienteId, MsBusinessFatturaService $svc)
    {
        if (!isset($this->previews[$clienteId])) {
            $this->addError('creazione', 'Preview non presente per il cliente selezionato.');
            return;
        }
        $pv = $this->previews[$clienteId];
        if (($pv['blocking_missing_price'] ?? true) === true) {
            $this->addError('creazione', "Prezzi mancanti nel cliente #{$clienteId}: impossibile creare la fattura.");
            return;
        }

        $cliente = Cliente::findOrFail($clienteId);

        try {
            $refs = $svc->creaFatturaPerCliente(
                $cliente, (int)$this->mese, (int)$this->anno, Carbon::parse($this->dataDocumento)
            );
            session()->flash('ok', "Fattura creata per {$cliente->nome}: {$refs['tipork']}/{$refs['serie']}/{$refs['anno']}/{$refs['numero']}");
            // rimuovi dalla lista (ora non ha più nulla da fatturare per questo mese)
            unset($this->previews[$clienteId]);
            $this->ricalcolaTotaleGenerale();
        } catch (\Throwable $ex) {
            $this->addError('creazione', $ex->getMessage());
        }
    }

    public function creaTutte(MsBusinessFatturaService $svc)
    {
        // crea fatture solo per i clienti “OK” (nessun prezzo mancante)
        foreach ($this->previews as $clienteId => $pv) {
            if (($pv['blocking_missing_price'] ?? true) === true) continue;

            $cliente = Cliente::find($clienteId);
            if (!$cliente) continue;

            try {
                $svc->creaFatturaPerCliente(
                    $cliente, (int)$this->mese, (int)$this->anno, Carbon::parse($this->dataDocumento)
                );
                unset($this->previews[$clienteId]);
            } catch (\Throwable $ex) {
                // continua con gli altri, ma segnala in sessione
                session()->flash('err_'.$clienteId, "Errore {$cliente->nome}: ".$ex->getMessage());
            }
        }
        $this->ricalcolaTotaleGenerale();
        session()->flash('ok', 'Fatture create (dove possibile).');
    }

    private function ricalcolaTotaleGenerale(): void
    {
        $this->totaleGenerale = 0.0;
        foreach ($this->previews as $pv) {
            $this->totaleGenerale += (float)($pv['totale'] ?? 0);
        }
    }

    public function render()
    {
        return view('livewire.fatturazione.elenco-da-fatturare')->layout('layouts.app');;
    }
}
