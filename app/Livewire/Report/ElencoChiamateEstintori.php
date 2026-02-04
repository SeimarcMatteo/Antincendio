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
        $dataFine   = Carbon::createFromDate($this->anno, $this->mese, 1)->endOfMonth();

        // 1) prendi tutti i presidi che hanno una scadenza nel mese scelto
        $presidi = Presidio::with([
                'tipoEstintore:id,descrizione',
                'cliente:id,nome,zona',
                'sede:id,cliente_id,zona',
                'sede.cliente:id,nome',
            ])
            // filtro per zona (accetta sia zona cliente che zona sede)
            ->when($this->zona, function ($q) {
                $q->whereHas('cliente', fn($qq) => $qq->where('zona', $this->zona))
                  ->orWhereHas('sede', fn($qs) => $qs->where('zona', $this->zona));
            })
            // qualunque scadenza cada nel mese
            ->where(function ($q) use ($dataInizio, $dataFine) {
                $q->whereBetween('data_revisione', [$dataInizio, $dataFine])
                  ->orWhereBetween('data_collaudo',  [$dataInizio, $dataFine])
                  ->orWhereBetween('data_fine_vita', [$dataInizio, $dataFine]);
                // Se vuoi includere le sostituzioni pianificate, sblocca la riga sotto:
                // ->orWhereBetween('data_sostituzione', [$dataInizio, $dataFine]);
            })
            ->get();

        // 2) raggruppa per (mese selezionato, zona, cliente, tipo estintore)
        $gruppati = [];
        $chiaveData = $dataInizio->format('Y-m-d'); // manteniamo una riga per mese

        foreach ($presidi as $p) {
            $zona    = $p->cliente->zona
                        ?? optional($p->sede)->zona
                        ?? 'N.D.';
            $cliente = $p->cliente->nome
                        ?? optional(optional($p->sede)->cliente)->nome
                        ?? 'N.D.';
            $tipo    = $p->tipoEstintore->descrizione ?? 'N.D.';

            $revisiona = $this->scadenza($p->data_revisione);
            $collauda  = $this->scadenza($p->data_collaudo);
            $finevita  = $this->scadenza($p->data_fine_vita);

            if (!($revisiona || $collauda || $finevita)) {
                continue;
            }

            $chiave = implode('|', [$chiaveData, $zona, $cliente, $tipo]);

            if (!isset($gruppati[$chiave])) {
                $gruppati[$chiave] = ['revisione' => 0, 'collaudo' => 0, 'fine_vita' => 0];
            }

            if ($revisiona) $gruppati[$chiave]['revisione']++;
            if ($collauda)  $gruppati[$chiave]['collaudo']++;
            if ($finevita)  $gruppati[$chiave]['fine_vita']++;
        }

        foreach ($gruppati as $chiave => $valori) {
            [$d, $z, $c, $t] = explode('|', $chiave);
            $this->dati[] = [
                'data'           => $d,
                'zona'           => $z,
                'cliente'        => $c,
                'tipo_estintore' => $t,
                'revisione'      => $valori['revisione'],
                'collaudo'       => $valori['collaudo'],
                'fine_vita'      => $valori['fine_vita'],
                'totale'         => array_sum($valori),
            ];
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
