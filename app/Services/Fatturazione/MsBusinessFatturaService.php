<?php
// app/Services/Fatturazione/MsBusinessFatturaService.php
namespace App\Services\Fatturazione;

use App\Models\{Cliente, Intervento};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MsBusinessFatturaService
{
    private const CONN = 'sqlsrv';
    // Tabelle Business
    private const T_ANAGRA  = 'anagra';
    private const T_TABTPBF = 'tabtpbf';
    private const T_TESTATA = 'testmag';
    private const T_RIGHE   = 'movmag';

    // Colonne chiave tabtpbf: collega tramite anagra.an_codtpbf → tabtpbf.tb_codtpbf (adatta se diverso)
    private const COL_TPBF_IN_ANAGRA = 'an_codtpbf';
    private const COL_TPBF_PK        = 'tb_codtpbf'; // << se il tuo campo si chiama diversamente, cambialo qui
    private const COL_TPBF_CAUSALE   = 'tb_tcaumag';

    public function __construct(
        private MsBusinessNumeroService $numeri,
        private BillingPreviewService   $preview
    ) {}

    /**
     * Crea 1 fattura per CLIENTE e MESE/ANNO (righe aggregate per SIGLA).
     * @return array refs {tipork,serie,anno,numero}
     */
    public function creaFatturaPerCliente(Cliente $cliente, int $mese, int $anno, ?Carbon $dataDocumento = null): array
    {
        // 1) Anteprima (blocca se prezzi mancanti)
        $p = $this->preview->buildPreview($cliente, $mese, $anno);
        if ($p['blocking_missing_price']) {
            throw new \RuntimeException('Prezzo mancante: completa i listini prima di creare la fattura.');
        }
        if (empty($p['righe'])) {
            throw new \RuntimeException('Nessuna riga da fatturare per il mese selezionato.');
        }

        // 2) Leggi Anagra (Business) per conto/iva/tpbf
    $conto = (string)$cliente->codice_esterno;

    $an = DB::connection(self::CONN)->table(self::T_ANAGRA)
        ->select('an_conto','an_codtpbf','an_codese')
        ->where('an_conto', $conto)
        ->first();

    if (!$an) {
        throw new \RuntimeException("Conto {$conto} non trovato in ANAGRA.");
    }

    // Normalizzazione TPBF: se 0 o NULL → 1
    $codTpbfRaw = $an->{self::COL_TPBF_IN_ANAGRA} ?? null;
    $codTpbf    = (int)$codTpbfRaw;
    if ($codTpbf === 0) {
        $codTpbf = 1;
    }

    $ivaTestata = $an->an_codese; // può essere null

    // Lookup tabtpbf con retry automatico su 1 se non trovato
    $tpbf = DB::connection(self::CONN)->table(self::T_TABTPBF)
        ->select(self::COL_TPBF_CAUSALE, self::COL_TPBF_PK)
        ->where(self::COL_TPBF_PK, $codTpbf)
        ->first();

    // Se non trovato, prova esplicitamente 1
    if (!$tpbf && $codTpbf !== 1) {
        $tpbf = DB::connection(self::CONN)->table(self::T_TABTPBF)
            ->select(self::COL_TPBF_CAUSALE, self::COL_TPBF_PK)
            ->where(self::COL_TPBF_PK, 1)
            ->first();
        if ($tpbf) {
            $codTpbf = 1; // allinea il valore usato in testata
        }
    }

    if (!$tpbf) {
        throw new \RuntimeException(
            "tabtpbf non trovato. Cercato codice {$codTpbfRaw} " .
            "(normalizzato a {$codTpbf}) e fallback a 1: nessun record disponibile."
        );
    }

    $causaleMag = $tpbf->{self::COL_TPBF_CAUSALE};

        // 3) Numero documento
        $refs = $this->numeri->nextNumero('A','P',$anno); // tm_tipork='A', tm_serie='P'
        $tm_tipork = $refs['tipork']; $tm_serie = $refs['serie']; $tm_anno = $refs['anno']; $tm_numdoc = $refs['numero'];
        $tm_datdoc = ($dataDocumento ?? now())->toDateString();

        // 4) Insert testata + righe (MSSQL)
        DB::connection(self::CONN)->transaction(function () use (
            $p,$conto,$ivaTestata,$codTpbf,$causaleMag,$tm_tipork,$tm_serie,$tm_anno,$tm_numdoc,$tm_datdoc
        ) {
            // TESTATA
            DB::connection(self::CONN)->table(self::T_TESTATA)->insert([
                'tm_tipork' => $tm_tipork,
                'tm_anno'   => $tm_anno,
                'tm_serie'  => $tm_serie,
                'tm_numdoc' => $tm_numdoc,
                'tm_datdoc' => $tm_datdoc,
                'tm_conto'  => $conto,
                'tm_conto2' => $conto,
                'tm_tipobf' => $codTpbf,          // anagra.an_codtpbf
                'tm_magaz'  => 1,
                'tm_causale'=> $causaleMag,       // tabtpbf.tb_tcaumag
                'tm_codese' => $ivaTestata,       // IVA testata come richiesto
            ]);

            // RIGHE (10, 20, 30…)
            $riga = 10;
            foreach ($p['righe'] as $r) {
                $codiva = $ivaTestata ?? 1022;  // default 1022 se assente
                DB::connection(self::CONN)->table(self::T_RIGHE)->insert([
                    'mm_tipork' => $tm_tipork,
                    'mm_anno'   => $tm_anno,
                    'mm_serie'  => $tm_serie,
                    'mm_numdoc' => $tm_numdoc,
                    'mm_riga'   => $riga,
                    'mm_codart' => $r['sigla'],
                    'mm_descr'  => $r['descrizione'],
                    'mm_quant'  => $r['qty'],
                    'mm_prezzo' => $r['unit_price'],
                    'mm_causale'=> $causaleMag,
                    'mm_magaz'  => 1,
                    'mm_codiva' => $codiva,
                ]);
                $riga += 10;
            }
        });

        // 5) Marca interventi come fatturati (MySQL)
        $usati = Intervento::query()
            ->where('cliente_id',$cliente->id)
            ->where('stato','completato')
            ->where('fatturato',false)
            ->whereMonth('data_intervento',$mese)
            ->whereYear('data_intervento',$anno)
            ->pluck('id');

        // NB: transazione separata su MySQL
        DB::transaction(function () use ($usati,$refs,$p) {
            Intervento::whereIn('id',$usati)->update([
                'fatturato' => true,
                'fatturato_at' => now(),
                'fattura_ref' => "{$refs['tipork']}/{$refs['serie']}/{$refs['anno']}/{$refs['numero']}",
                'fattura_ref_data' => json_encode($refs),
                'fatturazione_payload' => json_encode($p),
            ]);
        });

        return $refs;
    }
}
