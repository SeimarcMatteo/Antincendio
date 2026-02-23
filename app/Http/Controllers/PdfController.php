<?php
namespace App\Http\Controllers;

use App\Services\Interventi\RapportinoInterventoService;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    public function rapportino(Request $request, int $id, RapportinoInterventoService $rapportinoSvc)
    {
        $kind = $rapportinoSvc->normalizeKind((string) $request->query('kind', RapportinoInterventoService::KIND_CLIENTE));
        $data = $rapportinoSvc->buildDataByInterventoId($id);
        $pdf = $rapportinoSvc->buildPdf($kind, $data);
        $filename = $rapportinoSvc->filename($kind, $data['intervento']);

        $download = filter_var($request->query('download', false), FILTER_VALIDATE_BOOL);

        if ($download) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
