<?php

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
    private const T_TESTATA = 'testord';  // ORDINI
    private const T_RIGHE   = 'movord';   // RIGHE ORDINI

    // Colonne chiave tabtpbf
    private const COL_TPBF_IN_ANAGRA = 'an_codtpbf';
    private const COL_TPBF_PK        = 'tb_codtpbf';
    private const COL_TPBF_CAUSALE   = 'tb_tcaumag';

    public function __construct(
        private MsBusinessNumeroService $numeri,
        private BillingPreviewService   $preview
    ) {}

    /**
     * Crea un ORDINE R per CLIENTE e MESE/ANNO (righe aggregate per SIGLA)
     * e poi lancia la stored di Business.
     *
     * @return array {tipork, serie, anno, numero}
     */
    public function creaFatturaPerCliente(Cliente $cliente, int $mese, int $anno, ?Carbon $dataDocumento = null): array
    {
        // 1) Anteprima (blocca se prezzi mancanti)
        $p = $this->preview->buildPreview($cliente, $mese, $anno);
        if ($p['blocking_missing_price']) {
            throw new \RuntimeException('Prezzo mancante: completa i listini prima di creare il documento.');
        }
        if (empty($p['righe'])) {
            throw new \RuntimeException('Nessuna riga da fatturare per il mese selezionato.');
        }

        // 2) Lettura ANAGRA in Business (conto / TPBF / IVA)
        $conto = (string) $cliente->codice_esterno;

        $an = DB::connection(self::CONN)
            ->table(self::T_ANAGRA)
            ->select('an_conto', 'an_codtpbf', 'an_codese')
            ->where('an_conto', $conto)
            ->first();

        if (! $an) {
            throw new \RuntimeException("Conto {$conto} non trovato in ANAGRA.");
        }

        // Normalizzazione TPBF: se 0 o NULL → 1
        $codTpbfRaw = $an->{self::COL_TPBF_IN_ANAGRA} ?? null;
        $codTpbf    = (int) $codTpbfRaw;
        if ($codTpbf === 0) {
            $codTpbf = 1;
        }

        $ivaTestata = $an->an_codese; // può essere null

        // Lookup tabtpbf (con fallback a 1)
        $tpbf = DB::connection(self::CONN)
            ->table(self::T_TABTPBF)
            ->select(self::COL_TPBF_CAUSALE, self::COL_TPBF_PK)
            ->where(self::COL_TPBF_PK, $codTpbf)
            ->first();

        if (! $tpbf && $codTpbf !== 1) {
            $tpbf = DB::connection(self::CONN)
                ->table(self::T_TABTPBF)
                ->select(self::COL_TPBF_CAUSALE, self::COL_TPBF_PK)
                ->where(self::COL_TPBF_PK, 1)
                ->first();
            if ($tpbf) {
                $codTpbf = 1;
            }
        }

        if (! $tpbf) {
            throw new \RuntimeException(
                "tabtpbf non trovato. Cercato codice {$codTpbfRaw} (normalizzato a {$codTpbf}) e fallback a 1."
            );
        }

        $causaleMag = $tpbf->{self::COL_TPBF_CAUSALE};

        // 3) Numero documento ORDINE (tipork R, serie P)
        $refs = $this->numeri->nextNumero('R', 'P', $anno); // to_tipork='R', to_serie='P'
        $tm_tipork = $refs['tipork']; // 'R'
        $tm_serie  = $refs['serie'];  // 'P'
        $tm_anno   = $refs['anno'];   // es. 2025
        $tm_numdoc = $refs['numero'];

        $dataDoc        = $dataDocumento ?? now();
        $tm_datdoc      = $dataDoc->toDateString();     // 'YYYY-MM-DD'
        $tm_datdocYmd   = $dataDoc->format('Ymd');      // 'YYYYMMDD'
        $dtDataForSp = $dataDoc->format('Ymd');
        $tm_datdocSql   = DB::raw("CONVERT(datetime, '{$tm_datdocYmd}', 112)");

        // 4) Insert testata + righe ORDINE (MSSQL)
        DB::connection(self::CONN)->transaction(function () use (
            $p,
            $conto,
            $ivaTestata,
            $codTpbf,
            $causaleMag,
            $tm_tipork,
            $tm_serie,
            $tm_anno,
            $tm_numdoc,
            $tm_datdocSql
        ) {
            // TESTATA ORDINE (testord)
            DB::connection(self::CONN)->table(self::T_TESTATA)->insert([
                'td_tipork'  => $tm_tipork,
                'td_anno'    => $tm_anno,
                'td_serie'   => $tm_serie,
                'td_numord'  => $tm_numdoc,
                'td_datord'  => $tm_datdocSql,
                'td_conto'   => $conto,
                'td_contodest'   => $conto,
                'td_contfatt'   => $conto,
                'td_tipobf' => $codTpbf,
                'td_caustra' => $causaleMag,
                'td_codese'  => $ivaTestata ?? 0,
            ]);

            // RIGHE ORDINE (movord)
            $riga = 10;
            foreach ($p['righe'] as $r) {
                $codiva     = $ivaTestata ?? 1022;  // default 1022 se assente
                $quantita   = $r['qty'];
                $prezzoUnit = $r['unit_price'];
                $valore     = $quantita * $prezzoUnit;

                DB::connection(self::CONN)->table(self::T_RIGHE)->insert([
                    'mo_tipork' => $tm_tipork,
                    'mo_anno'   => $tm_anno,
                    'mo_serie'  => $tm_serie,
                    'mo_numord' => $tm_numdoc,
                    'mo_riga'   => $riga,
                    'mo_codart' => $r['sigla'],
                    'mo_descr'  => $r['descrizione'],
                    'mo_unmis'  => 'NR',      // cambia in 'PZ' o altro se necessario
                    'mo_quant'  => $quantita,
                    'mo_prezzo' => $prezzoUnit,
                    'mo_valore' => $valore,
                    'mo_scont1' => 0,
                    'mo_scont2' => 0,
                    'mo_scont3' => 0,
                    'mo_codiva' => $codiva,
                ]);

                $riga += 10;
            }
        });

        // 4bis) LANCIO STORED PROCEDURE Business (MSSQL)
        try {
            DB::connection(self::CONN)->statement(
                "EXEC [dbo].[bussp_bsorgsor9_faggiorn2]
                    @tipodoc   = ?,
                    @anno      = ?,
                    @serie     = ?,
                    @numdoc    = ?,
                    @codditt   = ?,
                    @dtData    = ?,
                    @stropnome = ?",
                [
                    $tm_tipork,        // 'R'
                    $tm_anno,
                    $tm_serie,
                    $tm_numdoc,
                    'ANTINCENDIO',
                    $dtDataForSp,        // 'YYYY-MM-DD' – il driver lo converte
                    'admin',
                ]
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Errore durante l'esecuzione della stored bussp_bsorgsor9_faggiorn2: " . $e->getMessage(),
                0,
                $e
            );
        }

        // 5) Marca interventi come fatturati (MySQL)
        $usati = Intervento::query()
            ->where('cliente_id', $cliente->id)
            ->where('stato', 'completato')
            ->where('fatturato', false)
            ->whereMonth('data_intervento', $mese)
            ->whereYear('data_intervento', $anno)
            ->pluck('id');

        DB::transaction(function () use ($usati, $refs, $p) {
            Intervento::whereIn('id', $usati)->update([
                'fatturato'            => true,
                'fatturato_at'         => now(),
                'fattura_ref'          => "{$refs['tipork']}/{$refs['serie']}/{$refs['anno']}/{$refs['numero']}",
                'fattura_ref_data'     => json_encode($refs),
                'fatturazione_payload' => json_encode($p),
            ]);
        });

        return $refs;
    }
}
