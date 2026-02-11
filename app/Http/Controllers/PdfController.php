<?php
namespace App\Http\Controllers;

use App\Models\Anomalia;
use App\Models\Intervento;
use App\Services\Interventi\OrdinePreventivoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Schema;

class PdfController extends Controller
{
    public function rapportino($id, OrdinePreventivoService $ordiniSvc)
    {
        $relations = [
            'cliente',
            'sede',
            'tecnici',
            'presidiIntervento.presidio.tipoEstintore',
            'presidiIntervento.presidio.idranteTipoRef',
            'presidiIntervento.presidio.portaTipoRef',
        ];

        $hasAnomaliaItemsTable = Schema::hasTable('presidio_intervento_anomalie');

        if ($hasAnomaliaItemsTable) {
            $relations[] = 'presidiIntervento.anomalieItems.anomalia';
        }

        $intervento = Intervento::with($relations)->findOrFail($id);

        $ordinePreventivo = $ordiniSvc->caricaOrdineApertoPerCliente((string) ($intervento->cliente?->codice_esterno ?? ''));
        $righeIntervento = $ordiniSvc->buildRigheIntervento($intervento->presidiIntervento);
        $confrontoOrdine = $ordiniSvc->buildConfronto(
            $ordinePreventivo['rows'] ?? [],
            $righeIntervento['rows'] ?? []
        );
        $anomalieRiepilogo = $ordiniSvc->buildAnomalieSummaryFromPresidiIntervento(
            $intervento->presidiIntervento,
            Anomalia::pluck('etichetta', 'id')->toArray()
        );

        $pdf = Pdf::loadView('pdf.rapportino-intervento', compact(
            'intervento',
            'ordinePreventivo',
            'righeIntervento',
            'confrontoOrdine',
            'anomalieRiepilogo',
            'hasAnomaliaItemsTable'
        ))->setPaper('a4');
        return $pdf->stream('rapportino_intervento_' . $intervento->id . '.pdf');
    }
}
