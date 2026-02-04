<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class ElencoChiamateEstintoriExport implements FromView
{
    public $dati, $mese, $anno;

    public function __construct(array $dati, $mese, $anno)
    {
        $this->dati = $dati;
        $this->mese = $mese;
        $this->anno = $anno;
    }

    public function view(): View
    {
        return view('exports.elenco-chiamate-estintori', [
            'dati' => $this->dati,
            'mese' => $this->mese,
            'anno' => $this->anno,
        ]);
    }
}
