
<?php

use Carbon\Carbon;
use App\Models\ClassificazioneEstintore;

function calcolaDatePresidio(Carbon $dataSerbatoio, ClassificazioneEstintore $classificazione): array
{
    $dataProduzione = $dataSerbatoio;
    $cutoff = Carbon::create(2024, 8, 31);

    $dopoCutoff = $dataProduzione->greaterThan($cutoff);

    $anniRevisione = $dopoCutoff ? $classificazione->anni_revisione_dopo : $classificazione->anni_revisione_prima;
    $anniCollaudo  = $classificazione->anni_collaudo;
    $anniFineVita  = $classificazione->anni_fine_vita;

    $revisione = $dataProduzione->copy()->addYears($anniRevisione);
    $collaudo  = $anniCollaudo ? $dataProduzione->copy()->addYears($anniCollaudo) : null;
    $fineVita  = $dataProduzione->copy()->addYears($anniFineVita);

    // Calcolo sostituzione = la più vicina tra le date non nulle
    $date = collect([$revisione, $collaudo, $fineVita])->filter();
    $sostituzione = $date->sort()->first();

    return [
        'data_revisione'     => $revisione,
        'data_collaudo'      => $collaudo,
        'data_fine_vita'     => $fineVita,
        'data_sostituzione'  => $sostituzione,
    ];
}
