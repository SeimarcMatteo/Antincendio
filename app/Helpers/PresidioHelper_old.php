
/*
use Carbon\Carbon;
use App\Models\ClassificazioneEstintore;

/**
 * Calcola le date del presidio con logica unificata:
 * - periodo revisione deciso da data serbatoio vs cutoff (31/08/2024)
 * - base revisione = data_ultima_revisione se presente, altrimenti data_serbatoio
 * - collaudo/fine vita da data_serbatoio
 * - allineamento INCLUSIVO ai mesi visita: se il mese della scadenza è tra i mesi visita, usa quello,
 *   altrimenti il mese visita precedente.
 *
 * Ritorna SEMPRE stringhe 'Y-m-d' o null.
 */
/*function calcolaDatePresidio(
    ?Carbon $dataSerbatoio,
    ClassificazioneEstintore $classificazione,
    ?Carbon $dataUltimaRevisione = null,
    array $mesiVisita = [],
    ?Carbon $scadenzaPresidio = null
): array {
    // Guard-rail: almeno una data base
    if (!$dataSerbatoio && !$dataUltimaRevisione) {
        return [
            'data_revisione'    => null,
            'data_collaudo'     => null,
            'data_fine_vita'    => null,
            'data_sostituzione' => null,
        ];
    }

    $serb = $dataSerbatoio?->copy()->startOfMonth();
    $last = $dataUltimaRevisione?->copy()->startOfMonth();
    $cut  = Carbon::create(2024, 8, 31, 23, 59, 59);

    // Periodo revisione: serbatoio vs cutoff (fallback "prima" se manca)
    $anniRev = ($serb && $serb->gt($cut) && !empty($classificazione->anni_revisione_dopo))
        ? (int)$classificazione->anni_revisione_dopo
        : (int)$classificazione->anni_revisione_prima;

    // Scadenze "pure"
    $scadRev  = nextDueAfterCarbon($last ?: $serb, $anniRev);
    $scadColl = (!empty($classificazione->anni_collaudo) && $serb)
        ? nextDueAfterCarbon($serb, (int)$classificazione->anni_collaudo)
        : null;
    $fineVita = ($serb && !empty($classificazione->anni_fine_vita))
        ? $serb->copy()->addYears((int)$classificazione->anni_fine_vita)->startOfMonth()
        : null;

    // Allineamento INCLUSIVO ai mesi visita
    $revAligned  = visitaOnOrBeforeCarbon($scadRev,  $mesiVisita);
    $colAligned  = visitaOnOrBeforeCarbon($scadColl, $mesiVisita);
    $fineAligned = visitaOnOrBeforeCarbon($fineVita, $mesiVisita);

    // Sostituzione = visita inclusiva della MIN( scad_rev, scad_coll, fine_vita, scad_presidio )
    $minAssolutaYmd = minDateYmd(
        $scadRev?->format('Y-m-d'),
        $scadColl?->format('Y-m-d'),
        $fineVita?->format('Y-m-d'),
        $scadenzaPresidio?->copy()->startOfMonth()->format('Y-m-d')
    );
    $sostituzione = visitaOnOrBeforeCarbon(
        $minAssolutaYmd ? Carbon::parse($minAssolutaYmd) : null,
        $mesiVisita
    );

    return [
        'data_revisione'    => $revAligned?->format('Y-m-d'),
        'data_collaudo'     => $colAligned?->format('Y-m-d'),
        'data_fine_vita'    => $fineAligned?->format('Y-m-d'),
        'data_sostituzione' => $sostituzione?->format('Y-m-d'),
    ];
}

/* ========= Helper interni coerenti con Model/Import ========= */
/*
function nextDueAfterCarbon(?Carbon $start, ?int $periodYears, ?Carbon $today = null): ?Carbon
{
    if (!$start || !$periodYears || $periodYears <= 0) return null;
    $today = ($today ?? now())->copy()->startOfDay();

    $due = $start->copy()->startOfMonth()->addYears($periodYears);
    while ($due->lte($today)) {
        $due->addYears($periodYears);
    }
    return $due->startOfMonth();
}

/**
 * Inclusivo: se il mese della scadenza è nei mesi visita, usa quello; altrimenti il precedente.
 *//*
function visitaOnOrBeforeCarbon(?Carbon $due, array $mesiVisita): ?Carbon
{
    if (!$due) return null;
    $due = $due->copy()->startOfMonth();

    if (empty($mesiVisita)) {
        // nessuna preferenza -> tieni il mese della scadenza
        return $due;
    }

    if (in_array((int)$due->month, $mesiVisita, true)) {
        return $due;
    }

    // precedente mese visita
    sort($mesiVisita);
    $y = (int)$due->year;
    $m = (int)$due->month;
    $before = array_values(array_filter($mesiVisita, fn($vm) => $vm < $m));
    if ($before) {
        $month = end($before);
        return Carbon::create($y, $month, 1);
    }
    $month = end($mesiVisita);
    return Carbon::create($y - 1, $month, 1);
}

function minDateYmd(?string ...$dates): ?string
{
    $valid = array_filter($dates);
    if (!$valid) return null;
    return collect($valid)->min();
}
