<?php

namespace App\Livewire\Report;

use Livewire\Component;
use App\Models\Intervento;
use App\Models\Presidio;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ElencoChiamateEstintoriExport;

class ElencoChiamateEstintori extends Component
{
    public $zona = '';
    public $dati = [];
    public $mese;
    public $anno;

    public function mount()
    {
        $oggi = now();
        $this->mese = $oggi->format('m');
        $this->anno = $oggi->format('Y');
        $this->caricaDati();
    }

    public function updatedMese() { $this->caricaDati(); }
    public function updatedAnno() { $this->caricaDati(); }
    public function updatedZona() { $this->caricaDati(); }

    public function caricaDati()
    {
        $this->dati = [];

        $dataInizio = Carbon::createFromDate($this->anno, $this->mese, 1)->startOfMonth();
        $dataFine = Carbon::createFromDate($this->anno, $this->mese, 1)->endOfMonth();

        $interventi = Intervento::with(['presidi.tipoEstintore', 'cliente'])
            ->whereBetween('data_intervento', [$dataInizio, $dataFine])
            ->whereHas('presidi', fn($q) => $q->whereNotNull('presidio_id')) // filtro sicurezza
            ->when($this->zona, fn($q) =>
                $q->whereHas('cliente', fn($q2) =>
                    $q2->where('zona', $this->zona)))
            ->get();

        $raggruppati = [];

        foreach ($interventi as $intervento) {
            $data = Carbon::parse($intervento->data_intervento)->format('Y-m-d');
            $zona = $intervento->cliente->zona ?? 'N.D.';
            $cliente = $intervento->cliente->nome ?? 'N.D.';
        
            $gruppati = [];
        
            foreach ($intervento->presidi as $presidio) {
                $tipo = $presidio->tipoEstintore->descrizione ?? 'N.D.';
        
                $revisiona = $this->scadenza($presidio->data_revisione);
                $collauda  = $this->scadenza($presidio->data_collaudo);
                $finevita  = $this->scadenza($presidio->data_fine_vita);
        
                if ($revisiona || $collauda || $finevita) {
                    $chiave = implode('|', [$data, $zona, $cliente, $tipo]);
        
                    if (!isset($gruppati[$chiave])) {
                        $gruppati[$chiave] = ['revisione' => 0, 'collaudo' => 0, 'fine_vita' => 0];
                    }
        
                    if ($revisiona) $gruppati[$chiave]['revisione']++;
                    if ($collauda)  $gruppati[$chiave]['collaudo']++;
                    if ($finevita)  $gruppati[$chiave]['fine_vita']++;
                }
            }
        
            foreach ($gruppati as $chiave => $valori) {
                [$d, $z, $c, $t] = explode('|', $chiave);
                $this->dati[] = [
                    'data' => $d,
                    'zona' => $z,
                    'cliente' => $c,
                    'tipo_estintore' => $t,
                    'revisione' => $valori['revisione'],
                    'collaudo' => $valori['collaudo'],
                    'fine_vita' => $valori['fine_vita'],
                    'totale' => array_sum($valori),
                ];
            }
        }
        

        foreach ($raggruppati as $chiave => $quantita) {
            [$data, $zona, $cliente, $tipo] = explode('|', $chiave);
            $this->dati[] = compact('data', 'zona', 'cliente', 'tipo', 'quantita');
        }
    }

    protected function verificaRitiroObbligato(Presidio $presidio): bool
    {
        $meseAnno = Carbon::createFromDate($this->anno, $this->mese, 1);

        return collect([
            $presidio->data_revisione,
            $presidio->data_collaudo,
            $presidio->data_fine_vita,
            $presidio->data_sostituzione,
        ])->filter()->contains(fn($d) =>
            Carbon::parse($d)->isSameMonth($meseAnno)
        );
    }

    
    public function esportaExcel()
    {
        return Excel::download(
            new ElencoChiamateEstintoriExport($this->dati, $this->mese, $this->anno),
            "elenco_chiamate_estintori_{$this->anno}_{$this->mese}.xlsx"
        );
    }
    protected function scadenza($data)
    {
        return $data ? Carbon::parse($data)->isSameMonth(Carbon::createFromDate($this->anno, $this->mese, 1)) : false;
    }

    public function render()
    {
        return view('livewire.report.elenco-chiamate-estintori');
    }
}
