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

        $interventoByCode = [];
        foreach ($righeIntervento as $riga) {
            $code = $this->normalizeCode($riga['codice_articolo'] ?? null);
            if (!$code) {
                continue;
            }
            if (!isset($interventoByCode[$code])) {
                $interventoByCode[$code] = [
                    'codice_articolo' => $code,
                    'descrizione' => (string) ($riga['descrizione'] ?? ''),
                    'quantita' => 0.0,
                ];
            }
            $interventoByCode[$code]['quantita'] += (float) ($riga['quantita'] ?? 0);
            if ($interventoByCode[$code]['descrizione'] === '' && !empty($riga['descrizione'])) {
                $interventoByCode[$code]['descrizione'] = (string) $riga['descrizione'];
            }
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

    public function buildAnomalieSummaryFromInput(array $input, array $anomaliaMap): array
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

        foreach ($input as $dati) {
            $ids = collect($dati['anomalie'] ?? [])
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $riparateMap = is_array($dati['anomalie_riparate'] ?? null) ? $dati['anomalie_riparate'] : [];

            foreach ($ids as $id) {
                $riparata = filter_var($riparateMap[$id] ?? false, FILTER_VALIDATE_BOOL);
                $this->accumulaAnomalia($summary, $id, $riparata, $anomaliaMap);
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
                            $anomaliaMap
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
                $this->accumulaAnomalia($summary, (int) $id, false, $anomaliaMap);
            }
        }

        uasort(
            $summary['dettaglio'],
            fn (array $a, array $b) => strnatcasecmp((string) ($a['etichetta'] ?? ''), (string) ($b['etichetta'] ?? ''))
        );
        $summary['dettaglio'] = array_values($summary['dettaglio']);

        return $summary;
    }

    private function accumulaAnomalia(array &$summary, int $anomaliaId, bool $riparata, array $anomaliaMap): void
    {
        $meta = $this->resolveAnomaliaMeta($anomaliaId, $anomaliaMap);
        $label = $meta['etichetta'];
        $prezzo = $meta['prezzo'];

        $detailKey = (string) $anomaliaId;

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

    private function resolveAnomaliaMeta(int $anomaliaId, array $anomaliaMap): array
    {
        $raw = $anomaliaMap[$anomaliaId] ?? null;

        if (is_array($raw)) {
            $label = trim((string) ($raw['etichetta'] ?? ('Anomalia #' . $anomaliaId)));
            $prezzo = (float) ($raw['prezzo'] ?? 0);
            return [
                'etichetta' => $label,
                'prezzo' => max(0, $prezzo),
            ];
        }

        if (is_object($raw)) {
            $label = trim((string) ($raw->etichetta ?? ('Anomalia #' . $anomaliaId)));
            $prezzo = (float) ($raw->prezzo ?? 0);
            return [
                'etichetta' => $label,
                'prezzo' => max(0, $prezzo),
            ];
        }

        return [
            'etichetta' => trim((string) ($raw ?? ('Anomalia #' . $anomaliaId))),
            'prezzo' => 0.0,
        ];
    }

    private function resolveCodiceArticoloAndDescrizione($presidio, string $categoria): array
    {
        $categoria = trim($categoria);
        if ($categoria === 'Estintore') {
            $tipo = $presidio->tipoEstintore;
            $isFullService = $this->isFullServiceContratto($presidio->tipo_contratto ?? null);
            $codiceBase = $tipo?->codice_articolo_fatturazione ?: $tipo?->sigla;
            $codiceFull = $tipo?->codice_articolo_fatturazione_full;
            $codice = $isFullService && !empty($codiceFull) ? $codiceFull : $codiceBase;
            $descr = trim((string) (($tipo?->sigla ?? '') . ' ' . ($tipo?->descrizione ?? '')));
            if ($isFullService) {
                $descr = trim($descr . ' [FULL SERVICE]');
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

    private function isFullServiceContratto($tipoContratto): bool
    {
        $value = mb_strtoupper(trim((string) $tipoContratto));
        if ($value === '') {
            return false;
        }

        $normalized = preg_replace('/\s+/', ' ', $value);
        if (!is_string($normalized) || $normalized === '') {
            return false;
        }

        if (str_contains($normalized, 'FULL SERVICE')) {
            return true;
        }

        if (str_contains($normalized, 'FULLSERVICE')) {
            return true;
        }

        return (bool) preg_match('/\bFULL\b/u', $normalized);
    }
}
