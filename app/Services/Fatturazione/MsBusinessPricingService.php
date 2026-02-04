<?php

namespace App\Services\Fatturazione;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MsBusinessPricingService
{
    private const CONN = 'sqlsrv';
    private const T_LISTINI = 'listini';
    private const T_ANAGRA  = 'anagra';

    public function getPrezzo(string $conto, string $codArt, Carbon $dataRif): ?float
    {
        $r = DB::connection(self::CONN)->table(self::T_LISTINI)
            ->where('lc_conto', $conto)
            ->where('lc_codart', $codArt)
            ->whereDate('lc_datagg', '<=', $dataRif)
            ->whereDate('lc_datscad', '>=', $dataRif)
            ->orderBy('lc_datagg','desc')
            ->first();

        if ($r && isset($r->lc_prezzo)) return (float)$r->lc_prezzo;

        $a = DB::connection(self::CONN)->table(self::T_ANAGRA)
            ->select('an_listino')->where('an_conto', $conto)->first();

        if (!$a || !isset($a->an_listino)) return null;

        $r2 = DB::connection(self::CONN)->table(self::T_LISTINI)
            ->where('lc_listino', $a->an_listino)
            ->where('lc_codart', $codArt)
            ->whereDate('lc_datagg', '<=', $dataRif)
            ->whereDate('lc_datscad', '>=', $dataRif)
            ->orderBy('lc_datagg','desc')
            ->first();

        return ($r2 && isset($r2->lc_prezzo)) ? (float)$r2->lc_prezzo : null;
    }
}
