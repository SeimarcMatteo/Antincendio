<?php

namespace App\Services\Interventi;

use App\Models\Anomalia;
use App\Models\Intervento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Schema;

class RapportinoInterventoService
{
    public const KIND_CLIENTE = 'cliente';
    public const KIND_INTERNO = 'interno';

    public function normalizeKind(?string $kind): string
    {
        $kind = strtolower(trim((string) $kind));
        return $kind === self::KIND_INTERNO ? self::KIND_INTERNO : self::KIND_CLIENTE;
    }

    public function buildDataByInterventoId(int $interventoId): array
    {
        $intervento = Intervento::with($this->relations())->findOrFail($interventoId);

        return $this->buildData($intervento);
    }

    public function buildData(Intervento $intervento): array
    {
        $intervento->loadMissing($this->relations());
        $tecnicoChiusura = $this->resolveTecnicoChiusura($intervento);

        $ordiniSvc = app(OrdinePreventivoService::class);

        $ordinePreventivo = $ordiniSvc->caricaOrdineApertoPerCliente((string) ($intervento->cliente?->codice_esterno ?? ''));
        $righeIntervento = $ordiniSvc->buildRigheIntervento($intervento->presidiIntervento);
        $confrontoOrdine = $ordiniSvc->buildConfronto(
            $ordinePreventivo['rows'] ?? [],
            $righeIntervento['rows'] ?? []
        );
        $prezziExtraManuali = $this->extractPrezziExtraManuali($intervento);
        $extraPresidiSummary = $ordiniSvc->buildExtraPresidiSummary(
            $confrontoOrdine,
            $prezziExtraManuali
        );
        $anomaliaQuery = Anomalia::query()->select(['id', 'etichetta']);
        if (Schema::hasColumn('anomalie', 'prezzo')) {
            $anomaliaQuery->addSelect('prezzo');
        }

        $anomalieRiepilogo = $ordiniSvc->buildAnomalieSummaryFromPresidiIntervento(
            $intervento->presidiIntervento,
            $anomaliaQuery
                ->get()
                ->mapWithKeys(fn (Anomalia $anomalia) => [
                    $anomalia->id => [
                        'etichetta' => (string) $anomalia->etichetta,
                        'prezzo' => (float) ($anomalia->prezzo ?? 0),
                    ],
                ])
                ->toArray()
        );
        $riepilogoEconomico = $ordiniSvc->buildEconomicSummary(
            (float) data_get($ordinePreventivo, 'header.totale_documento', 0),
            $extraPresidiSummary,
            $anomalieRiepilogo
        );
        $hasAnomaliaItemsTable = Schema::hasTable('presidio_intervento_anomalie');

        return [
            'intervento' => $intervento,
            'tecnicoChiusura' => $tecnicoChiusura,
            'ordinePreventivo' => $ordinePreventivo,
            'righeIntervento' => $righeIntervento,
            'confrontoOrdine' => $confrontoOrdine,
            'extraPresidiSummary' => $extraPresidiSummary,
            'riepilogoEconomico' => $riepilogoEconomico,
            'prezziExtraManuali' => $prezziExtraManuali,
            'anomalieRiepilogo' => $anomalieRiepilogo,
            'hasAnomaliaItemsTable' => $hasAnomaliaItemsTable,
            'riepilogoOrdine' => [
                'righe_intervento' => $righeIntervento['rows'] ?? [],
                'presidi_senza_codice' => $righeIntervento['missing_mapping'] ?? [],
                'confronto' => $confrontoOrdine,
                'extra_presidi' => $extraPresidiSummary,
                'riepilogo_economico' => $riepilogoEconomico,
                'prezzi_extra_manuali' => $prezziExtraManuali,
                'anomalie' => $anomalieRiepilogo,
            ],
        ];
    }

    public function buildPdf(string $kind, array $data)
    {
        $kind = $this->normalizeKind($kind);

        return Pdf::loadView($this->viewFor($kind), $data)->setPaper('a4');
    }

    public function renderPdfOutput(string $kind, array $data): string
    {
        return $this->buildPdf($kind, $data)->output();
    }

    public function filename(string $kind, Intervento $intervento): string
    {
        $kind = $this->normalizeKind($kind);
        $suffix = $kind === self::KIND_INTERNO ? 'interno' : 'cliente';
        return "rapportino_intervento_{$intervento->id}_{$suffix}.pdf";
    }

    private function viewFor(string $kind): string
    {
        return $kind === self::KIND_INTERNO
            ? 'pdf.rapportino-intervento-interno'
            : 'pdf.rapportino-intervento-cliente';
    }

    private function relations(): array
    {
        $relations = [
            'cliente',
            'sede',
            'tecnici',
            'tecnicoChiusura',
            'presidiIntervento.presidio.tipoEstintore',
            'presidiIntervento.presidio.idranteTipoRef',
            'presidiIntervento.presidio.portaTipoRef',
        ];

        if (Schema::hasTable('presidio_intervento_anomalie')) {
            $relations[] = 'presidiIntervento.anomalieItems.anomalia';
        }

        return $relations;
    }

    private function resolveTecnicoChiusura(Intervento $intervento): ?\App\Models\User
    {
        if ($intervento->relationLoaded('tecnicoChiusura') && $intervento->tecnicoChiusura) {
            return $intervento->tecnicoChiusura;
        }

        if ($intervento->relationLoaded('tecnici') && $intervento->tecnici->isNotEmpty()) {
            $ordered = $intervento->tecnici->sortByDesc(function ($tecnico) {
                $pivotEnd = data_get($tecnico, 'pivot.ended_at');
                return $pivotEnd ? strtotime((string) $pivotEnd) : 0;
            });

            return $ordered->first();
        }

        return null;
    }

    private function extractPrezziExtraManuali(Intervento $intervento): array
    {
        $payload = $intervento->fatturazione_payload;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($payload)) {
            $payload = [];
        }

        $raw = $payload['prezzi_extra'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $code => $price) {
            $code = mb_strtoupper(trim((string) $code));
            if ($code === '') {
                continue;
            }
            $value = is_numeric($price) ? round((float) $price, 4) : null;
            if ($value === null || $value < 0) {
                continue;
            }
            $out[$code] = $value;
        }

        return $out;
    }
}
