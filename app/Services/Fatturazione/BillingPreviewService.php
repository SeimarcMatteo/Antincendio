<?php
// app/Services/Fatturazione/BillingPreviewService.php
namespace App\Services\Fatturazione;

use App\Models\{Cliente, Intervento};
use Carbon\Carbon;

class BillingPreviewService
{
    public function __construct(private MsBusinessPricingService $pricing) {}

    /**
     * Ritorna:
     * [
     *   'cliente' => Cliente,
     *   'righe' => [ ['sigla','descrizione','qty','unit_price','total','missing_price'] ... ],
     *   'totale' => float,
     *   'blocking_missing_price' => bool
     * ]
     */
    public function buildPreview(Cliente $cliente, int $mese, int $anno): array
    {
        $moltiplicatore = ($cliente->fatturazione_tipo === 'annuale') ? 2 : 1;

        $interventi = Intervento::query()
            ->where('cliente_id', $cliente->id)
            ->where('stato', 'completato')
            ->where('fatturato', false)
            ->whereMonth('data_intervento', $mese)
            ->whereYear('data_intervento', $anno)
            ->with(['presidiIntervento.presidio.tipoEstintore'])
            ->get();

        // key = sigla
        $bySigla = [];
        $anyMissing = false;

        foreach ($interventi as $int) {
            $dataRif = Carbon::parse($int->data_intervento);

            foreach ($int->presidiIntervento as $pi) {
                $presidio = $pi->presidio;
                $tipo     = $presidio?->tipoEstintore;
                $sigla    = $tipo?->sigla;
                if (!$sigla) continue;

                if (!isset($bySigla[$sigla])) {
                    $descr = 'CANONE MANUT.';
                    if (!empty($tipo?->descrizione)) $descr .= ' '.$tipo->descrizione;

                    $bySigla[$sigla] = [
                        'sigla'         => $sigla,
                        'descrizione'   => $descr,
                        'qty'           => 0,
                        'unit_price'    => 0.0,       // verrà valorizzato
                        'total'         => 0.0,
                        'missing_price' => false,
                        '_seen_prices'  => [],        // per gestire prezzi diversi nello stesso mese
                    ];
                }

                $bySigla[$sigla]['qty'] += (1 * $moltiplicatore);

                // prezzo per la data dell'intervento (validità inclusiva)
                $unit = $this->pricing->getPrezzo((string)$cliente->codice_esterno, (string)$sigla, $dataRif);

                if ($unit === null) {
                    $bySigla[$sigla]['missing_price'] = true;
                    $anyMissing = true;
                } else {
                    $bySigla[$sigla]['unit_price'] = $unit; // ultimo visto (vedi Domanda A)
                    $bySigla[$sigla]['_seen_prices'][(string)$unit] = true;
                }
            }
        }

        // calcolo totale
        $totale = 0.0;
        foreach ($bySigla as &$r) {
            $r['total'] = $r['qty'] * (float)$r['unit_price'];
            unset($r['_seen_prices']); // non serve in UI
            $totale += $r['total'];
        }

        return [
            'cliente' => $cliente,
            'righe' => array_values($bySigla),
            'totale' => $totale,
            'blocking_missing_price' => $anyMissing,
        ];
    }
}
