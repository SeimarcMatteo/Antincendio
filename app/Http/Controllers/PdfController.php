<?php
namespace App\Http\Controllers;

use App\Models\Intervento;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfController extends Controller
{
    public function rapportino($id)
    {
        $intervento = Intervento::with(['cliente', 'sede', 'tecnici', 'presidiIntervento.presidio'])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.rapportino-intervento', compact('intervento'))->setPaper('a4');
        return $pdf->stream('rapportino_intervento_' . $intervento->id . '.pdf');
    }
}
