<?php

namespace App\Services\Interventi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrdinePreventivoService
{
    private const CONN = 'sqlsrv';

    public function caricaOrdineApertoPerCliente(?string $codiceEsterno): array
    {
        $conto = trim((string) $codiceEsterno);
        if ($conto === '') {
            return [
                'found' => false,
                'error' => 'Codice esterno cliente non valorizzato.',
                'header' => null,
                'rows' => [],
            ];
        }

        try {
            $headerRaw = DB::connection(self::CONN)
                ->table('testord')
                ->where('td_tipork', 'R')
                ->where('td_serie', 'P')
                ->where('td_conto', $conto)
                ->where('td_flevas', 'N')
                ->orderByDesc('td_datord')
                ->orderByDesc('td_anno')
                ->orderByDesc('td_numord')
                ->select([
                    'td_tipork',
                    'td_anno',
                    'td_serie',
                    'td_numord',
                    'td_datord',
                    'td_totdoc',
                    'td_conto',
                ])
                ->first();

            if (!$headerRaw) {
                return [
                    'found' => false,
                    'error' => "Nessun ordine aperto trovato in Business (R/P, td_flevas = N) per conto {$conto}.",
                    'header' => null,
                    'rows' => [],
                ];
            }

            $righeRaw = DB::connection(self::CONN)
                ->table('movord')
                ->where('mo_tipork', $headerRaw->td_tipork)
                ->where('mo_anno', $headerRaw->td_anno)
                ->where('mo_serie', $headerRaw->td_serie)
                ->where('mo_numord', $headerRaw->td_numord)
                ->selectRaw("UPPER(LTRIM(RTRIM(COALESCE(mo_codart, '')))) as codice_articolo")
                ->selectRaw("COALESCE(mo_descr, '') as descrizione")
                ->selectRaw('CAST(SUM(COALESCE(mo_quant, 0)) AS float) as quantita')
                ->selectRaw('CAST(COALESCE(mo_prezzo, 0) AS float) as prezzo_unitario')
                ->groupByRaw("UPPER(LTRIM(RTRIM(COALESCE(mo_codart, '')))), COALESCE(mo_descr, ''), COALESCE(mo_prezzo, 0)")
                ->orderByRaw("UPPER(LTRIM(RTRIM(COALESCE(mo_codart, ''))))")
                ->get();

            $righe = [];
            foreach ($righeRaw as $riga) {
                $codice = $this->normalizeCode($riga->codice_articolo);
                if (!$codice) {
                    continue;
                }

                $quantita = (float) $riga->quantita;
                $prezzo = (float) $riga->prezzo_unitario;
                $righe[] = [
                    'codice_articolo' => $codice,
                    'descrizione' => trim((string) ($riga->descrizione ?? '')),
                    'quantita' => $quantita,
                    'prezzo_unitario' => $prezzo,
                    'importo' => $quantita * $prezzo,
                ];
            }

            $ts = !empty($headerRaw->td_datord) ? strtotime((string) $headerRaw->td_datord) : false;

            return [
                'found' => true,
                'error' => null,
                'header' => [
                    'tipork' => (string) $headerRaw->td_tipork,
                    'serie' => (string) $headerRaw->td_serie,
                    'anno' => (int) $headerRaw->td_anno,
                    'numero' => (int) $headerRaw->td_numord,
                    'conto' => (string) $headerRaw->td_conto,
                    'data' => $ts ? date('Y-m-d', $ts) : null,
                    'totale_documento' => (float) ($headerRaw->td_totdoc ?? 0),
                ],
                'rows' => $righe,
            ];
        } catch (Throwable $e) {
            return [
                'found' => false,
                'error' => 'Errore lettura ordini da SQL Server: ' . $e->getMessage(),
                'header' => null,
                'rows' => [],
            ];
        }
    }

    public function buildRigheIntervento(iterable $presidiIntervento): array
    {
        $rows = [];
        $missingMapping = [];

        foreach ($presidiIntervento as $pi) {
            $presidio = $pi->presidio ?? null;
            if (!$presidio) {
                continue;
            }

            $categoria = (string) ($presidio->categoria ?? 'Estintore');
            [$codiceArticolo, $descrizioneTipo] = $this->resolveCodiceArticoloAndDescrizione($presidio, $categoria);

            if (!$codiceArticolo) {
                $missingMapping[] = [
                    'categoria' => $categoria,
                    'progressivo' => (string) ($presidio->progressivo ?? '-'),
                    'tipo' => $descrizioneTipo ?: 'Tipo non definito',
                ];
                continue;
            }

            if (!isset($rows[$codiceArticolo])) {
                $rows[$codiceArticolo] = [
                    'codice_articolo' => $codiceArticolo,
                    'descrizione' => $descrizioneTipo,
                    'quantita' => 0.0,
                ];
            }

            $rows[$codiceArticolo]['quantita'] += 1.0;
        }

        ksort($rows);

        return [
            'rows' => array_values($rows),
            'missing_mapping' => $missingMapping,
        ];
    }

    public function buildConfronto(array $righeOrdine, array $righeIntervento): array
    {
        $ordineByCode = [];
        foreach ($righeOrdine as $riga) {
            $code = $this->normalizeCode($riga['codice_articolo'] ?? null);
            if (!$code) {
                continue;
            }
            if (!isset($ordineByCode[$code])) {
                $ordineByCode[$code] = [
                    'codice_articolo' => $code,
                    'descrizione' => (string) ($riga['descrizione'] ?? ''),
                    'quantita' => 0.0,
                    'importo' => 0.0,
                ];
            }

            $qty = (float) ($riga['quantita'] ?? 0);
            $imp = (float) ($riga['importo'] ?? ((float) ($riga['prezzo_unitario'] ?? 0) * $qty));
            $ordineByCode[$code]['quantita'] += $qty;
            $ordineByCode[$code]['importo'] += $imp;
            if ($ordineByCode[$code]['descrizione'] === '' && !empty($riga['descrizione'])) {
                $ordineByCode[$code]['descrizione'] = (string) $riga['descrizione'];
            }
        }

        $ordineCodes = array_keys($ordineByCode);
        $remainingOrdineByCode = [];
        foreach ($ordineByCode as $code => $row) {
            $remainingOrdineByCode[$code] = max(0, (float) ($row['quantita'] ?? 0));
        }

        $interventoByCode = [];
        foreach ($righeIntervento as $riga) {
            $code = $this->normalizeCode($riga['codice_articolo'] ?? null);
            if (!$code) {
                continue;
            }

            $qty = (float) ($riga['quantita'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $descrizione = (string) ($riga['descrizione'] ?? '');

            // Supporto wildcard nei codici articolo (es: N*P006) per match su righe ordine.
            if ($this->isWildcardPattern($code) && !empty($ordineCodes)) {
                $matchedCodes = [];
                foreach ($ordineCodes as $ordineCode) {
                    if ($this->codeMatchesPattern($code, $ordineCode)) {
                        $matchedCodes[] = $ordineCode;
                    }
                }

                if (!empty($matchedCodes)) {
                    sort($matchedCodes, SORT_NATURAL);
                    $remainingQty = $qty;

                    // Prima allinea la quantita ai codici presenti in ordine.
                    foreach ($matchedCodes as $matchedCode) {
                        if ($remainingQty <= 0.0001) {
                            break;
                        }

                        $allocatable = max(0, (float) ($remainingOrdineByCode[$matchedCode] ?? 0));
                        if ($allocatable <= 0.0001) {
                            continue;
                        }

                        $allocated = min($remainingQty, $allocatable);
                        $this->addInterventoRow(
                            $interventoByCode,
                            $matchedCode,
                            $allocated,
                            $descrizione !== '' ? $descrizione : (string) ($ordineByCode[$matchedCode]['descrizione'] ?? '')
                        );

                        $remainingQty -= $allocated;
                        $remainingOrdineByCode[$matchedCode] = round($allocatable - $allocated, 4);
                    }

                    // Eventuale extra oltre ordine: lo attribuiamo al primo codice matchato.
                    if ($remainingQty > 0.0001) {
                        $targetCode = $matchedCodes[0];
                        $this->addInterventoRow(
                            $interventoByCode,
                            $targetCode,
                            $remainingQty,
                            $descrizione !== '' ? $descrizione : (string) ($ordineByCode[$targetCode]['descrizione'] ?? '')
                        );
                    }

                    continue;
                }
            }

            $this->addInterventoRow($interventoByCode, $code, $qty, $descrizione);
        }

        $allCodes = array_unique(array_merge(array_keys($ordineByCode), array_keys($interventoByCode)));
        sort($allCodes);

        $soloOrdine = [];
        $soloIntervento = [];
        $qtyDiff = [];

        foreach ($allCodes as $code) {
            $qOrd = (float) ($ordineByCode[$code]['quantita'] ?? 0);
            $qInt = (float) ($interventoByCode[$code]['quantita'] ?? 0);
            $delta = $qInt - $qOrd;

            if ($qOrd > 0 && $qInt <= 0) {
                $soloOrdine[] = [
                    'codice_articolo' => $code,
                    'descrizione' => $ordineByCode[$code]['descrizione'] ?? '',
                    'quantita_ordine' => $qOrd,
                ];
                continue;
            }

            if ($qInt > 0 && $qOrd <= 0) {
                $soloIntervento[] = [
                    'codice_articolo' => $code,
                    'descrizione' => $interventoByCode[$code]['descrizione'] ?? '',
                    'quantita_intervento' => $qInt,
                ];
                continue;
            }

            if (abs($delta) > 0.0001) {
                $qtyDiff[] = [
                    'codice_articolo' => $code,
                    'descrizione' => $interventoByCode[$code]['descrizione'] ?: ($ordineByCode[$code]['descrizione'] ?? ''),
                    'quantita_ordine' => $qOrd,
                    'quantita_intervento' => $qInt,
                    'delta' => $delta,
                ];
            }
        }

        return [
            'solo_ordine' => $soloOrdine,
            'solo_intervento' => $soloIntervento,
            'differenze_quantita' => $qtyDiff,
            'ok' => empty($soloOrdine) && empty($soloIntervento) && empty($qtyDiff),
            'totali' => [
                'ordine_quantita' => array_sum(array_column($ordineByCode, 'quantita')),
                'intervento_quantita' => array_sum(array_column($interventoByCode, 'quantita')),
            ],
            'ordine_by_code' => array_values($ordineByCode),
            'intervento_by_code' => array_values($interventoByCode),
        ];
    }

    private function addInterventoRow(array &$rows, string $code, float $qty, string $descrizione = ''): void
    {
        if ($qty <= 0.0001) {
            return;
        }

        if (!isset($rows[$code])) {
            $rows[$code] = [
                'codice_articolo' => $code,
                'descrizione' => $descrizione,
                'quantita' => 0.0,
            ];
        }

        $rows[$code]['quantita'] += $qty;
        if ($rows[$code]['descrizione'] === '' && $descrizione !== '') {
            $rows[$code]['descrizione'] = $descrizione;
        }
    }

    public function buildExtraPresidiSummary(array $confronto, array $manualPricesByCode = []): array
    {
        $ordineByCode = [];
        foreach (($confronto['ordine_by_code'] ?? []) as $row) {
            $code = $this->normalizeCode($row['codice_articolo'] ?? null);
            if (!$code) {
                continue;
            }
            $ordineByCode[$code] = [
                'codice_articolo' => $code,
                'descrizione' => (string) ($row['descrizione'] ?? ''),
                'quantita' => (float) ($row['quantita'] ?? 0),
                'importo' => (float) ($row['importo'] ?? 0),
            ];
        }

        $interventoByCode = [];
        foreach (($confronto['intervento_by_code'] ?? []) as $row) {
            $code = $this->normalizeCode($row['codice_articolo'] ?? null);
            if (!$code) {
                continue;
            }
            $interventoByCode[$code] = [
                'codice_articolo' => $code,
                'descrizione' => (string) ($row['descrizione'] ?? ''),
                'quantita' => (float) ($row['quantita'] ?? 0),
            ];
        }

        $manualByCode = $this->normalizeManualPriceMap($manualPricesByCode);
        $codes = array_unique(array_merge(array_keys($ordineByCode), array_keys($interventoByCode)));
        sort($codes);

        $rows = [];
        $pending = [];
        $totaleExtra = 0.0;

        foreach ($codes as $code) {
            $qOrd = (float) ($ordineByCode[$code]['quantita'] ?? 0);
            $qInt = (float) ($interventoByCode[$code]['quantita'] ?? 0);
            $delta = round($qInt - $qOrd, 4);

            if ($delta <= 0) {
                continue;
            }

            $descrizione = trim((string) (
                $interventoByCode[$code]['descrizione'] ?? ($ordineByCode[$code]['descrizione'] ?? '')
            ));

            $prezzoUnitario = null;
            $prezzoSource = null;
            $manualRequired = false;

            if ($qOrd > 0.0001) {
                $importoOrdine = (float) ($ordineByCode[$code]['importo'] ?? 0);
                $prezzoUnitario = round($importoOrdine / $qOrd, 4);
                $prezzoSource = 'ordine';
            } else {
                $manualRequired = true;
                $prezzoUnitario = $manualByCode[$code] ?? null;
                $prezzoSource = $prezzoUnitario !== null ? 'manuale' : 'manuale_richiesto';
            }

            $importoExtra = $prezzoUnitario !== null
                ? round($delta * $prezzoUnitario, 2)
                : null;

            if ($importoExtra === null) {
                $pending[] = [
                    'codice_articolo' => $code,
                    'descrizione' => $descrizione,
                    'quantita_extra' => $delta,
                ];
            } else {
                $totaleExtra += $importoExtra;
            }

            $rows[] = [
                'codice_articolo' => $code,
                'descrizione' => $descrizione,
                'quantita_ordine' => $qOrd,
                'quantita_intervento' => $qInt,
                'quantita_extra' => $delta,
                'prezzo_unitario' => $prezzoUnitario,
                'prezzo_source' => $prezzoSource,
                'manual_required' => $manualRequired,
                'importo_extra' => $importoExtra,
            ];
        }

        return [
            'rows' => $rows,
            'pending_manual_prices' => $pending,
            'has_pending_manual_prices' => !empty($pending),
            'totale_extra' => round($totaleExtra, 2),
        ];
    }

    public function buildEconomicSummary(float $totaleOrdineBusiness, array $extraPresidiSummary, array $anomalieSummary): array
    {
        $totaleOrdineBusiness = round(max(0, $totaleOrdineBusiness), 2);
        $extraPresidi = round(max(0, (float) ($extraPresidiSummary['totale_extra'] ?? 0)), 2);
        $extraAnomalieRiparate = round(max(0, (float) ($anomalieSummary['importo_riparate'] ?? 0)), 2);

        return [
            'totale_ordine_business' => $totaleOrdineBusiness,
            'extra_presidi' => $extraPresidi,
            'extra_anomalie_riparate' => $extraAnomalieRiparate,
            'totale_aggiornato' => round($totaleOrdineBusiness + $extraPresidi + $extraAnomalieRiparate, 2),
        ];
    }

    public function buildAnomalieSummaryFromInput(array $input, array $anomaliaMap, array $presidioByPiId = []): array
    {
        $summary = [
            'totale' => 0,
            'riparate' => 0,
            'preventivo' => 0,
            'importo_riparate' => 0.0,
            'importo_preventivo' => 0.0,
            'importo_totale' => 0.0,
            'dettaglio' => [],
        ];

        foreach ($input as $piIdRaw => $dati) {
            $piId = is_numeric($piIdRaw) ? (int) $piIdRaw : null;
            $presidioContext = ($piId !== null && isset($presidioByPiId[$piId])) ? $presidioByPiId[$piId] : null;

            $ids = collect($dati['anomalie'] ?? [])
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $riparateMap = is_array($dati['anomalie_riparate'] ?? null) ? $dati['anomalie_riparate'] : [];

            foreach ($ids as $id) {
                $riparata = filter_var($riparateMap[$id] ?? false, FILTER_VALIDATE_BOOL);
                $this->accumulaAnomalia($summary, $id, $riparata, $anomaliaMap, $presidioContext);
            }
        }

        uasort(
            $summary['dettaglio'],
            fn (array $a, array $b) => strnatcasecmp((string) ($a['etichetta'] ?? ''), (string) ($b['etichetta'] ?? ''))
        );
        $summary['dettaglio'] = array_values($summary['dettaglio']);

        return $summary;
    }

    public function buildAnomalieSummaryFromPresidiIntervento(iterable $presidiIntervento, array $anomaliaMap): array
    {
        $summary = [
            'totale' => 0,
            'riparate' => 0,
            'preventivo' => 0,
            'importo_riparate' => 0.0,
            'importo_preventivo' => 0.0,
            'importo_totale' => 0.0,
            'dettaglio' => [],
        ];

        $hasAnomaliaItemsTable = Schema::hasTable('presidio_intervento_anomalie');

        foreach ($presidiIntervento as $pi) {
            if ($hasAnomaliaItemsTable) {
                $items = $pi->relationLoaded('anomalieItems')
                    ? $pi->anomalieItems
                    : $pi->anomalieItems()->get(['anomalia_id', 'riparata']);

                if ($items->isNotEmpty()) {
                    foreach ($items as $item) {
                        $this->accumulaAnomalia(
                            $summary,
                            (int) $item->anomalia_id,
                            (bool) $item->riparata,
                            $anomaliaMap,
                            $pi
                        );
                    }
                    continue;
                }
            }

            $decoded = json_decode((string) ($pi->getRawOriginal('anomalie') ?? '[]'), true);
            $ids = is_array($decoded)
                ? collect($decoded)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values()->all()
                : [];
            foreach ($ids as $id) {
                $this->accumulaAnomalia($summary, (int) $id, false, $anomaliaMap, $pi);
            }
        }

        uasort(
            $summary['dettaglio'],
            fn (array $a, array $b) => strnatcasecmp((string) ($a['etichetta'] ?? ''), (string) ($b['etichetta'] ?? ''))
        );
        $summary['dettaglio'] = array_values($summary['dettaglio']);

        return $summary;
    }

    private function accumulaAnomalia(array &$summary, int $anomaliaId, bool $riparata, array $anomaliaMap, $presidioContext = null): void
    {
        $meta = $this->resolveAnomaliaMeta($anomaliaId, $anomaliaMap, $presidioContext);
        $label = $meta['etichetta'];
        $prezzo = $meta['prezzo'];
        $prezzoKey = number_format((float) $prezzo, 2, '.', '');

        $detailKey = (string) $anomaliaId . ':' . $prezzoKey;

        if (!isset($summary['dettaglio'][$detailKey])) {
            $summary['dettaglio'][$detailKey] = [
                'anomalia_id' => $anomaliaId,
                'etichetta' => $label,
                'prezzo' => $prezzo,
                'riparate' => 0,
                'preventivo' => 0,
                'totale' => 0,
                'importo_riparate' => 0.0,
                'importo_preventivo' => 0.0,
                'importo_totale' => 0.0,
            ];
        }

        $summary['totale']++;
        $summary['importo_totale'] += $prezzo;
        $summary['dettaglio'][$detailKey]['totale']++;
        $summary['dettaglio'][$detailKey]['importo_totale'] += $prezzo;

        if ($riparata) {
            $summary['riparate']++;
            $summary['importo_riparate'] += $prezzo;
            $summary['dettaglio'][$detailKey]['riparate']++;
            $summary['dettaglio'][$detailKey]['importo_riparate'] += $prezzo;
        } else {
            $summary['preventivo']++;
            $summary['importo_preventivo'] += $prezzo;
            $summary['dettaglio'][$detailKey]['preventivo']++;
            $summary['dettaglio'][$detailKey]['importo_preventivo'] += $prezzo;
        }
    }

    private function resolveAnomaliaMeta(int $anomaliaId, array $anomaliaMap, $presidioContext = null): array
    {
        $raw = $anomaliaMap[$anomaliaId] ?? null;

        $label = 'Anomalia #' . $anomaliaId;
        $prezzoBase = 0.0;
        $usaPrezziTipoEstintore = false;
        $usaPrezziTipoPresidio = false;
        $prezziTipoEstintore = [];
        $prezziTipoPresidio = [];

        if (is_array($raw)) {
            $label = trim((string) ($raw['etichetta'] ?? $label));
            $prezzoBase = max(0, (float) ($raw['prezzo'] ?? 0));
            $usaPrezziTipoEstintore = (bool) ($raw['usa_prezzi_tipo_estintore'] ?? false);
            $usaPrezziTipoPresidio = (bool) ($raw['usa_prezzi_tipo_presidio'] ?? false);
            $prezziTipoEstintore = $this->normalizePriceMap($raw['prezzi_tipo_estintore'] ?? []);
            $prezziTipoPresidio = $this->normalizePriceMap($raw['prezzi_tipo_presidio'] ?? []);
        } elseif (is_object($raw)) {
            $label = trim((string) ($raw->etichetta ?? $label));
            $prezzoBase = max(0, (float) ($raw->prezzo ?? 0));
            $usaPrezziTipoEstintore = (bool) ($raw->usa_prezzi_tipo_estintore ?? false);
            $usaPrezziTipoPresidio = (bool) ($raw->usa_prezzi_tipo_presidio ?? false);
            $prezziTipoEstintore = $this->normalizePriceMap($raw->prezzi_tipo_estintore ?? []);
            $prezziTipoPresidio = $this->normalizePriceMap($raw->prezzi_tipo_presidio ?? []);
        } elseif ($raw !== null) {
            $label = trim((string) $raw);
        }

        $prezzoEffettivo = $prezzoBase;
        $ctx = $this->extractPresidioContext($presidioContext);

        if (($ctx['categoria'] ?? null) === 'Estintore' && $usaPrezziTipoEstintore) {
            $tipoEstintoreId = (int) ($ctx['tipo_estintore_id'] ?? 0);
            if ($tipoEstintoreId > 0 && array_key_exists($tipoEstintoreId, $prezziTipoEstintore)) {
                $prezzoEffettivo = (float) $prezziTipoEstintore[$tipoEstintoreId];
            }
        }

        if (
            in_array(($ctx['categoria'] ?? null), ['Idrante', 'Porta'], true)
            && $usaPrezziTipoPresidio
        ) {
            $tipoPresidioId = (int) ($ctx['tipo_presidio_id'] ?? 0);
            if ($tipoPresidioId > 0 && array_key_exists($tipoPresidioId, $prezziTipoPresidio)) {
                $prezzoEffettivo = (float) $prezziTipoPresidio[$tipoPresidioId];
            }
        }

        return [
            'etichetta' => $label,
            'prezzo' => round(max(0, $prezzoEffettivo), 2),
        ];
    }

    private function normalizePriceMap($raw): array
    {
        $out = [];
        if (is_object($raw)) {
            $raw = (array) $raw;
        }
        if (!is_array($raw)) {
            return $out;
        }

        foreach ($raw as $id => $price) {
            $key = is_numeric($id) ? (int) $id : null;
            if (!$key || $key <= 0) {
                continue;
            }

            $normalized = $this->normalizeMoneyValue($price);
            if ($normalized === null) {
                continue;
            }
            $out[$key] = round(max(0, $normalized), 2);
        }

        return $out;
    }

    private function extractPresidioContext($context): array
    {
        if (is_object($context) && isset($context->presidio)) {
            $context = $context->presidio;
        }

        if (is_object($context)) {
            $categoria = trim((string) ($context->categoria ?? ''));
            $tipoEstintoreId = (int) ($context->tipo_estintore_id ?? 0);
            $idranteTipoId = (int) ($context->idrante_tipo_id ?? 0);
            $portaTipoId = (int) ($context->porta_tipo_id ?? 0);

            return [
                'categoria' => $categoria,
                'tipo_estintore_id' => $tipoEstintoreId > 0 ? $tipoEstintoreId : null,
                'tipo_presidio_id' => $idranteTipoId > 0 ? $idranteTipoId : ($portaTipoId > 0 ? $portaTipoId : null),
            ];
        }

        if (is_array($context)) {
            $categoria = trim((string) ($context['categoria'] ?? ''));
            $tipoEstintoreId = (int) ($context['tipo_estintore_id'] ?? 0);
            $idranteTipoId = (int) ($context['idrante_tipo_id'] ?? 0);
            $portaTipoId = (int) ($context['porta_tipo_id'] ?? 0);

            return [
                'categoria' => $categoria,
                'tipo_estintore_id' => $tipoEstintoreId > 0 ? $tipoEstintoreId : null,
                'tipo_presidio_id' => $idranteTipoId > 0 ? $idranteTipoId : ($portaTipoId > 0 ? $portaTipoId : null),
            ];
        }

        return [
            'categoria' => null,
            'tipo_estintore_id' => null,
            'tipo_presidio_id' => null,
        ];
    }

    private function resolveCodiceArticoloAndDescrizione($presidio, string $categoria): array
    {
        $categoria = trim($categoria);
        if ($categoria === 'Estintore') {
            $tipo = $presidio->tipoEstintore;
            $tipoContratto = $this->normalizeTipoContratto($presidio->tipo_contratto ?? null);
            $isFullService = $tipoContratto === 'full_service';
            $codiceBase = $tipo?->codice_articolo_fatturazione ?: $tipo?->sigla;
            $codiceFull = $tipo?->codice_articolo_fatturazione_full;
            $codice = $isFullService && !empty($codiceFull) ? $codiceFull : $codiceBase;
            $descr = trim((string) (($tipo?->sigla ?? '') . ' ' . ($tipo?->descrizione ?? '')));
            if ($isFullService) {
                $descr = trim($descr . ' [FULL SERVICE]');
            } elseif ($tipoContratto === 'noleggio') {
                $descr = trim($descr . ' [NOLEGGIO]');
            }
            return [$this->normalizeCode($codice), $descr ?: 'Estintore'];
        }

        if ($categoria === 'Idrante') {
            $tipo = $presidio->idranteTipoRef;
            $codice = $tipo?->codice_articolo_fatturazione ?: $tipo?->nome;
            $descr = trim((string) ($tipo?->nome ?? $presidio->idrante_tipo ?? 'Idrante'));
            return [$this->normalizeCode($codice), $descr];
        }

        if ($categoria === 'Porta') {
            $tipo = $presidio->portaTipoRef;
            $codice = $tipo?->codice_articolo_fatturazione ?: $tipo?->nome;
            $descr = trim((string) ($tipo?->nome ?? $presidio->porta_tipo ?? 'Porta'));
            return [$this->normalizeCode($codice), $descr];
        }

        return [null, trim((string) $categoria)];
    }

    private function normalizeCode($value): ?string
    {
        $code = mb_strtoupper(trim((string) $value));
        return $code === '' ? null : $code;
    }

    private function isWildcardPattern(string $code): bool
    {
        return str_contains($code, '*');
    }

    private function codeMatchesPattern(string $pattern, string $code): bool
    {
        if (!$this->isWildcardPattern($pattern)) {
            return $pattern === $code;
        }

        $quoted = preg_quote($pattern, '/');
        $regex = '/^' . str_replace('\*', '.*', $quoted) . '$/u';

        return (bool) preg_match($regex, $code);
    }

    private function isFullServiceContratto($tipoContratto): bool
    {
        return $this->normalizeTipoContratto($tipoContratto) === 'full_service';
    }

    private function normalizeTipoContratto($tipoContratto): string
    {
        $value = mb_strtoupper(trim((string) $tipoContratto));
        if ($value === '') {
            return 'noleggio';
        }

        $normalized = preg_replace('/\s+/', ' ', $value);
        if (!is_string($normalized) || $normalized === '') {
            return 'noleggio';
        }

        if (
            str_contains($normalized, 'FULL SERVICE')
            || str_contains($normalized, 'FULLSERVICE')
            || (bool) preg_match('/\bFULL\b/u', $normalized)
        ) {
            return 'full_service';
        }

        if (
            str_contains($normalized, 'NOLEGGIO')
            || str_contains($normalized, 'NOLEG')
            || str_contains($normalized, 'NOL')
        ) {
            return 'noleggio';
        }

        return 'noleggio';
    }

    private function normalizeManualPriceMap(array $raw): array
    {
        $out = [];
        foreach ($raw as $code => $value) {
            $normalizedCode = $this->normalizeCode($code);
            if (!$normalizedCode) {
                continue;
            }

            $price = $this->normalizeMoneyValue($value);
            if ($price === null) {
                continue;
            }

            $out[$normalizedCode] = $price;
        }

        return $out;
    }

    private function normalizeMoneyValue($raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^0-9,.\-]/', '', $value);
        $value = str_replace(',', '.', (string) $value);

        if (substr_count((string) $value, '.') > 1) {
            $parts = explode('.', (string) $value);
            $decimal = array_pop($parts);
            $value = implode('', $parts) . '.' . $decimal;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return round(max(0, (float) $value), 4);
    }
}
