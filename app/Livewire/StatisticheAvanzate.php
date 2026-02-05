<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Carbon\CarbonPeriod;

class StatisticheAvanzate extends Component
{
    public string $graficoSelezionato = 'tecnici';
    public string $dataDa;
    public string $dataA;
    public string $presetAttivo = 'month';

    public array $summary = [];
    public array $charts = [];
    public array $drilldown = [];

    protected $listeners = [
        'refreshChartData' => '$refresh',
        'statistiche:drilldown' => 'applyDrilldown',
    ]; // per forzare rerender su aggiornamento filtri

    public function mount(): void
    {
        $this->dataDa = now()->startOfMonth()->format('Y-m-d');
        $this->dataA  = now()->endOfMonth()->format('Y-m-d');
        $this->presetAttivo = 'month';
        $this->loadData();
    }

    public function updatedGraficoSelezionato(): void
    {
        $this->clearDrilldown();
        $this->dispatch('statistiche:refresh');
    }

    public function applyFilters(): void
    {
        $this->presetAttivo = 'custom';
        $this->loadData();
    }

    public function preset(string $range): void
    {
        $this->presetAttivo = $range;
        $today = Carbon::today();
        if ($range === 'month') {
            $this->dataDa = $today->copy()->startOfMonth()->format('Y-m-d');
            $this->dataA  = $today->copy()->endOfMonth()->format('Y-m-d');
        } elseif ($range === '30d') {
            $this->dataDa = $today->copy()->subDays(29)->format('Y-m-d');
            $this->dataA  = $today->format('Y-m-d');
        } elseif ($range === '90d') {
            $this->dataDa = $today->copy()->subDays(89)->format('Y-m-d');
            $this->dataA  = $today->format('Y-m-d');
        } elseif ($range === 'ytd') {
            $this->dataDa = $today->copy()->startOfYear()->format('Y-m-d');
            $this->dataA  = $today->format('Y-m-d');
        } elseif ($range === 'year') {
            $this->dataDa = $today->copy()->subYear()->addDay()->format('Y-m-d');
            $this->dataA  = $today->format('Y-m-d');
        }
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.statistiche-avanzate');
    }

    private function loadData(): void
    {
        [$da, $a] = $this->normalizedRange();

        $baseInterventi = DB::table('interventi')
            ->whereBetween('data_intervento', [$da, $a])
            ->where('stato', 'Completato');

        $this->summary = [
            'interventi' => (clone $baseInterventi)->count(),
            'clienti' => (clone $baseInterventi)->distinct('cliente_id')->count('cliente_id'),
            'tecnici' => DB::table('intervento_tecnico')
                ->join('interventi', 'interventi.id', '=', 'intervento_tecnico.intervento_id')
                ->whereBetween('interventi.data_intervento', [$da, $a])
                ->where('interventi.stato', 'Completato')
                ->distinct('intervento_tecnico.user_id')
                ->count('intervento_tecnico.user_id'),
            'durata_media' => (int) round((clone $baseInterventi)->avg('durata_effettiva') ?? 0),
            'presidi' => DB::table('presidi_intervento')
                ->join('interventi', 'interventi.id', '=', 'presidi_intervento.intervento_id')
                ->whereBetween('interventi.data_intervento', [$da, $a])
                ->where('interventi.stato', 'Completato')
                ->count(),
        ];

        $tecnici = DB::table('intervento_tecnico')
            ->join('interventi', 'interventi.id', '=', 'intervento_tecnico.intervento_id')
            ->join('users', 'users.id', '=', 'intervento_tecnico.user_id')
            ->whereBetween('interventi.data_intervento', [$da, $a])
            ->where('interventi.stato', 'Completato')
            ->selectRaw('users.id as key_id, users.name as label, COUNT(*) as interventi, SUM(COALESCE(interventi.durata_effettiva,0)) as minuti')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('interventi')
            ->limit(20)
            ->get();

        $clienti = DB::table('interventi')
            ->join('clienti', 'interventi.cliente_id', '=', 'clienti.id')
            ->where('interventi.stato', 'Completato')
            ->whereBetween('interventi.data_intervento', [$da, $a])
            ->selectRaw('clienti.id as key_id, clienti.nome as label, COUNT(*) as totale')
            ->groupBy('clienti.id', 'clienti.nome')
            ->orderByDesc('totale')
            ->limit(20)
            ->get();

        $durataTecnici = DB::table('intervento_tecnico')
            ->join('interventi', 'interventi.id', '=', 'intervento_tecnico.intervento_id')
            ->join('users', 'users.id', '=', 'intervento_tecnico.user_id')
            ->whereBetween('interventi.data_intervento', [$da, $a])
            ->where('interventi.stato', 'Completato')
            ->selectRaw('users.id as key_id, users.name as label, ROUND(AVG(interventi.durata_effettiva), 0) as media')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('media')
            ->limit(20)
            ->get();

        $presidiCategoria = DB::table('presidi_intervento')
            ->join('presidi', 'presidi.id', '=', 'presidi_intervento.presidio_id')
            ->join('interventi', 'interventi.id', '=', 'presidi_intervento.intervento_id')
            ->where('interventi.stato', 'Completato')
            ->whereBetween('interventi.data_intervento', [$da, $a])
            ->selectRaw('presidi.categoria as label, COUNT(*) as totale')
            ->groupBy('presidi.categoria')
            ->orderByDesc('totale')
            ->get();

        $trendRaw = $this->trendInterventi($da, $a);
        $trendLabels = $trendRaw['labels'];
        $trendValues = $trendRaw['values'];
        $trendKeys = $trendRaw['keys'];

        $esiti = DB::table('presidi_intervento')
            ->join('interventi', 'interventi.id', '=', 'presidi_intervento.intervento_id')
            ->where('interventi.stato', 'Completato')
            ->whereBetween('interventi.data_intervento', [$da, $a])
            ->selectRaw("COALESCE(presidi_intervento.esito, 'non_verificato') as label, COUNT(*) as totale")
            ->groupBy('label')
            ->orderByDesc('totale')
            ->get();

        $anomalieCategoria = DB::table('presidi_intervento')
            ->join('presidi', 'presidi.id', '=', 'presidi_intervento.presidio_id')
            ->join('interventi', 'interventi.id', '=', 'presidi_intervento.intervento_id')
            ->where('interventi.stato', 'Completato')
            ->whereBetween('interventi.data_intervento', [$da, $a])
            ->whereNotNull('presidi_intervento.anomalie')
            ->where('presidi_intervento.anomalie', '<>', '[]')
            ->selectRaw('presidi.categoria as label, COUNT(*) as totale')
            ->groupBy('presidi.categoria')
            ->orderByDesc('totale')
            ->get();

        $this->charts = [
            'tecnici' => [
                'title' => 'Interventi per tecnico',
                'subtitle' => 'Numero interventi e minuti totali',
                'type' => 'bar',
                'axis' => 'y',
                'labels' => $tecnici->pluck('label')->values(),
                'keys' => $tecnici->pluck('key_id')->values(),
                'datasets' => [
                    ['label' => 'Interventi', 'data' => $tecnici->pluck('interventi')->values(), 'backgroundColor' => '#EF4444'],
                    ['label' => 'Minuti', 'data' => $tecnici->pluck('minuti')->values(), 'backgroundColor' => '#3B82F6'],
                ],
                'table_headers' => ['Tecnico', 'Interventi', 'Minuti'],
                'table_rows' => $tecnici->map(fn($r) => [$r->label, (int)$r->interventi, (int)$r->minuti])->values(),
            ],
            'clienti' => [
                'title' => 'Interventi per cliente',
                'subtitle' => 'Top clienti nel periodo selezionato',
                'type' => 'bar',
                'axis' => 'y',
                'labels' => $clienti->pluck('label')->values(),
                'keys' => $clienti->pluck('key_id')->values(),
                'datasets' => [
                    ['label' => 'Interventi', 'data' => $clienti->pluck('totale')->values(), 'backgroundColor' => '#F97316'],
                ],
                'table_headers' => ['Cliente', 'Interventi'],
                'table_rows' => $clienti->map(fn($r) => [$r->label, (int)$r->totale])->values(),
            ],
            'durata' => [
                'title' => 'Durata media per tecnico',
                'subtitle' => 'Minuti medi per intervento',
                'type' => 'bar',
                'axis' => 'y',
                'labels' => $durataTecnici->pluck('label')->values(),
                'keys' => $durataTecnici->pluck('key_id')->values(),
                'datasets' => [
                    ['label' => 'Minuti medi', 'data' => $durataTecnici->pluck('media')->values(), 'backgroundColor' => '#8B5CF6'],
                ],
                'table_headers' => ['Tecnico', 'Minuti medi'],
                'table_rows' => $durataTecnici->map(fn($r) => [$r->label, (int)$r->media])->values(),
            ],
            'categoria' => [
                'title' => 'Presidi per categoria',
                'subtitle' => 'Conteggio presidi verificati per categoria',
                'type' => 'doughnut',
                'labels' => $presidiCategoria->pluck('label')->values(),
                'keys' => $presidiCategoria->pluck('label')->values(),
                'datasets' => [
                    [
                        'label' => 'Presidi',
                        'data' => $presidiCategoria->pluck('totale')->values(),
                        'backgroundColor' => ['#EF4444','#3B82F6','#F59E0B','#10B981','#8B5CF6','#64748B'],
                    ],
                ],
                'table_headers' => ['Categoria', 'Presidi'],
                'table_rows' => $presidiCategoria->map(fn($r) => [$r->label, (int)$r->totale])->values(),
            ],
            'anomalie' => [
                'title' => 'Anomalie rilevate',
                'subtitle' => 'Presidi con anomalie per categoria',
                'type' => 'doughnut',
                'labels' => $anomalieCategoria->pluck('label')->values(),
                'keys' => $anomalieCategoria->pluck('label')->values(),
                'datasets' => [
                    [
                        'label' => 'Anomalie',
                        'data' => $anomalieCategoria->pluck('totale')->values(),
                        'backgroundColor' => ['#F97316','#EF4444','#0EA5E9','#8B5CF6','#64748B'],
                    ],
                ],
                'table_headers' => ['Categoria', 'Anomalie'],
                'table_rows' => $anomalieCategoria->map(fn($r) => [$r->label, (int)$r->totale])->values(),
            ],
            'trend' => [
                'title' => 'Trend interventi',
                'subtitle' => 'Andamento mensile nel periodo',
                'type' => 'line',
                'labels' => $trendLabels,
                'keys' => $trendKeys,
                'datasets' => [
                    [
                        'label' => 'Interventi',
                        'data' => $trendValues,
                        'borderColor' => '#0EA5E9',
                        'backgroundColor' => 'rgba(14,165,233,0.2)',
                        'tension' => 0.3,
                        'fill' => true,
                    ],
                ],
                'table_headers' => ['Mese', 'Interventi'],
                'table_rows' => collect($trendLabels)->map(function ($label, $idx) use ($trendValues) {
                    return [$label, (int)($trendValues[$idx] ?? 0)];
                })->values(),
            ],
            'esiti' => [
                'title' => 'Esiti interventi',
                'subtitle' => 'Distribuzione degli esiti sui presidi',
                'type' => 'doughnut',
                'labels' => $esiti->pluck('label')->values(),
                'keys' => $esiti->pluck('label')->values(),
                'datasets' => [
                    [
                        'label' => 'Esiti',
                        'data' => $esiti->pluck('totale')->values(),
                        'backgroundColor' => ['#22C55E','#F97316','#EF4444','#64748B'],
                    ],
                ],
                'table_headers' => ['Esito', 'Totale'],
                'table_rows' => $esiti->map(fn($r) => [$this->formatEsitoLabel($r->label), (int)$r->totale])->values(),
            ],
        ];

        $this->clearDrilldown();
        $this->dispatch('statistiche:refresh');
    }

    public function clearDrilldown(): void
    {
        $this->drilldown = [];
    }

    public function selectDrilldownFromTable(int $index): void
    {
        $chart = $this->graficoSelezionato;
        $cfg = $this->charts[$chart] ?? null;
        if (!$cfg) {
            return;
        }

        $labels = $cfg['labels'] ?? [];
        $keys = $cfg['keys'] ?? $labels;

        if (!isset($labels[$index])) {
            return;
        }

        $this->applyDrilldown([
            'chart' => $chart,
            'label' => $labels[$index],
            'key' => $keys[$index] ?? $labels[$index],
        ]);
    }

    public function exportDrilldownCsv()
    {
        if (empty($this->drilldown['rows'] ?? [])) {
            return;
        }

        $filename = 'statistiche-dettaglio-' . now()->format('Ymd_His') . '.csv';
        $headers = $this->drilldown['headers'] ?? [];
        $rows = $this->drilldown['rows'] ?? [];
        $title = $this->drilldown['title'] ?? '';

        return response()->streamDownload(function () use ($headers, $rows, $title) {
            $handle = fopen('php://output', 'w');
            if ($title) {
                fputcsv($handle, [$title]);
                fputcsv($handle, []);
            }
            if (!empty($headers)) {
                fputcsv($handle, $headers);
            }
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename);
    }

    public function exportDrilldownPdf()
    {
        if (empty($this->drilldown['rows'] ?? [])) {
            return;
        }

        $data = [
            'title' => $this->drilldown['title'] ?? 'Dettaglio statistiche',
            'headers' => $this->drilldown['headers'] ?? [],
            'rows' => $this->drilldown['rows'] ?? [],
            'range' => [$this->dataDa, $this->dataA],
        ];

        $pdf = app('dompdf.wrapper')->loadView('pdf.statistiche-dettaglio', $data)->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'statistiche-dettaglio-' . now()->format('Ymd_His') . '.pdf');
    }

    public function applyDrilldown(array $payload): void
    {
        $chart = $payload['chart'] ?? null;
        $label = $payload['label'] ?? null;
        $key = $payload['key'] ?? $label;

        if (!$chart || $key === null) {
            return;
        }

        [$da, $a] = $this->normalizedRange();
        $driver = DB::getDriverName();
        $anomalyMap = DB::table('anomalie')->pluck('etichetta', 'id')->toArray();

        if (in_array($chart, ['tecnici', 'durata'], true)) {
            $rows = DB::table('interventi')
                ->join('intervento_tecnico', 'interventi.id', '=', 'intervento_tecnico.intervento_id')
                ->join('users', 'users.id', '=', 'intervento_tecnico.user_id')
                ->join('clienti', 'clienti.id', '=', 'interventi.cliente_id')
                ->leftJoin('sedi', 'sedi.id', '=', 'interventi.sede_id')
                ->leftJoin('presidi_intervento', 'presidi_intervento.intervento_id', '=', 'interventi.id')
                ->where('users.id', $key)
                ->where('interventi.stato', 'Completato')
                ->whereBetween('interventi.data_intervento', [$da, $a])
                ->selectRaw('interventi.id, interventi.data_intervento, clienti.nome as cliente, sedi.nome as sede, COALESCE(interventi.durata_effettiva, interventi.durata_minuti) as durata, COUNT(presidi_intervento.id) as presidi')
                ->groupBy('interventi.id', 'interventi.data_intervento', 'clienti.nome', 'sedi.nome', 'interventi.durata_effettiva', 'interventi.durata_minuti')
                ->orderByDesc('interventi.data_intervento')
                ->limit(300)
                ->get();

            $this->drilldown = [
                'title' => "Interventi tecnico: {$label}",
                'headers' => ['Data', 'Cliente', 'Sede', 'Presidi', 'Durata (min)'],
                'rows' => $rows->map(function ($r) {
                    return [
                        Carbon::parse($r->data_intervento)->format('d/m/Y'),
                        $r->cliente,
                        $r->sede ?? 'Principale',
                        (int) $r->presidi,
                        (int) $r->durata,
                    ];
                })->values()->all(),
            ];
            return;
        }

        if ($chart === 'clienti') {
            $tecniciExpr = $driver === 'sqlite'
                ? "GROUP_CONCAT(DISTINCT users.name, ', ') as tecnici"
                : "GROUP_CONCAT(DISTINCT users.name SEPARATOR ', ') as tecnici";

            $rows = DB::table('interventi')
                ->join('clienti', 'clienti.id', '=', 'interventi.cliente_id')
                ->leftJoin('sedi', 'sedi.id', '=', 'interventi.sede_id')
                ->leftJoin('presidi_intervento', 'presidi_intervento.intervento_id', '=', 'interventi.id')
                ->leftJoin('intervento_tecnico', 'intervento_tecnico.intervento_id', '=', 'interventi.id')
                ->leftJoin('users', 'users.id', '=', 'intervento_tecnico.user_id')
                ->where('clienti.id', $key)
                ->where('interventi.stato', 'Completato')
                ->whereBetween('interventi.data_intervento', [$da, $a])
                ->selectRaw("interventi.id, interventi.data_intervento, sedi.nome as sede, {$tecniciExpr}, COUNT(presidi_intervento.id) as presidi, COALESCE(interventi.durata_effettiva, interventi.durata_minuti) as durata")
                ->groupBy('interventi.id', 'interventi.data_intervento', 'sedi.nome', 'interventi.durata_effettiva', 'interventi.durata_minuti')
                ->orderByDesc('interventi.data_intervento')
                ->limit(300)
                ->get();

            $this->drilldown = [
                'title' => "Interventi cliente: {$label}",
                'headers' => ['Data', 'Sede', 'Tecnici', 'Presidi', 'Durata (min)'],
                'rows' => $rows->map(function ($r) {
                    return [
                        Carbon::parse($r->data_intervento)->format('d/m/Y'),
                        $r->sede ?? 'Principale',
                        $r->tecnici ?? '-',
                        (int) $r->presidi,
                        (int) $r->durata,
                    ];
                })->values()->all(),
            ];
            return;
        }

        if ($chart === 'trend') {
            try {
                $month = Carbon::createFromFormat('Y-m', $key)->startOfMonth();
            } catch (\Throwable $e) {
                return;
            }

            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $tecniciExpr = $driver === 'sqlite'
                ? "GROUP_CONCAT(DISTINCT users.name, ', ') as tecnici"
                : "GROUP_CONCAT(DISTINCT users.name SEPARATOR ', ') as tecnici";

            $rows = DB::table('interventi')
                ->join('clienti', 'clienti.id', '=', 'interventi.cliente_id')
                ->leftJoin('sedi', 'sedi.id', '=', 'interventi.sede_id')
                ->leftJoin('intervento_tecnico', 'intervento_tecnico.intervento_id', '=', 'interventi.id')
                ->leftJoin('users', 'users.id', '=', 'intervento_tecnico.user_id')
                ->where('interventi.stato', 'Completato')
                ->whereBetween('interventi.data_intervento', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->selectRaw("interventi.id, interventi.data_intervento, clienti.nome as cliente, sedi.nome as sede, {$tecniciExpr}")
                ->groupBy('interventi.id', 'interventi.data_intervento', 'clienti.nome', 'sedi.nome')
                ->orderByDesc('interventi.data_intervento')
                ->limit(300)
                ->get();

            $this->drilldown = [
                'title' => "Interventi mese: {$label}",
                'headers' => ['Data', 'Cliente', 'Sede', 'Tecnici'],
                'rows' => $rows->map(function ($r) {
                    return [
                        Carbon::parse($r->data_intervento)->format('d/m/Y'),
                        $r->cliente,
                        $r->sede ?? 'Principale',
                        $r->tecnici ?? '-',
                    ];
                })->values()->all(),
            ];
            return;
        }

        if (in_array($chart, ['categoria', 'esiti', 'anomalie'], true)) {
            $query = DB::table('presidi_intervento')
                ->join('presidi', 'presidi.id', '=', 'presidi_intervento.presidio_id')
                ->join('interventi', 'interventi.id', '=', 'presidi_intervento.intervento_id')
                ->join('clienti', 'clienti.id', '=', 'interventi.cliente_id')
                ->leftJoin('sedi', 'sedi.id', '=', 'interventi.sede_id')
                ->leftJoin('tipi_estintori', 'tipi_estintori.id', '=', 'presidi.tipo_estintore_id')
                ->where('interventi.stato', 'Completato')
                ->whereBetween('interventi.data_intervento', [$da, $a]);

            $titlePrefix = '';
            if ($chart === 'categoria') {
                $query->where('presidi.categoria', $key);
                $titlePrefix = 'Categoria';
            }
            if ($chart === 'esiti') {
                $query->where('presidi_intervento.esito', $key);
                $titlePrefix = 'Esito';
                $label = $this->formatEsitoLabel((string) $label);
            }
            if ($chart === 'anomalie') {
                $query->whereNotNull('presidi_intervento.anomalie')
                    ->where('presidi_intervento.anomalie', '<>', '[]');
                $query->where('presidi.categoria', $key);
                $titlePrefix = 'Anomalie categoria';
            }

            $rows = $query->selectRaw('interventi.data_intervento, clienti.nome as cliente, sedi.nome as sede, presidi.progressivo, presidi.categoria, tipi_estintori.sigla as tipo, presidi_intervento.esito, presidi_intervento.anomalie')
                ->orderByDesc('interventi.data_intervento')
                ->limit(300)
                ->get();

            $this->drilldown = [
                'title' => "{$titlePrefix}: {$label}",
                'headers' => ['Data', 'Cliente', 'Sede', 'Progressivo', 'Tipo', 'Esito', 'Anomalie'],
                'rows' => $rows->map(function ($r) use ($anomalyMap) {
                    $anomaliaText = '-';
                    if (!empty($r->anomalie)) {
                        $decoded = json_decode($r->anomalie, true);
                        if (is_array($decoded)) {
                            $labels = [];
                            foreach ($decoded as $id) {
                                $labels[] = $anomalyMap[$id] ?? (string) $id;
                            }
                            $anomaliaText = implode(', ', $labels);
                        }
                    }

                    return [
                        Carbon::parse($r->data_intervento)->format('d/m/Y'),
                        $r->cliente,
                        $r->sede ?? 'Principale',
                        $r->progressivo,
                        $r->tipo ?? '-',
                        $this->formatEsitoLabel($r->esito ?? 'non_verificato'),
                        $anomaliaText,
                    ];
                })->values()->all(),
            ];
            return;
        }
    }

    private function normalizedRange(): array
    {
        $da = Carbon::parse($this->dataDa)->startOfDay();
        $a  = Carbon::parse($this->dataA)->endOfDay();
        if ($da->gt($a)) {
            [$da, $a] = [$a, $da];
        }
        return [$da->format('Y-m-d'), $a->format('Y-m-d')];
    }

    private function trendInterventi(string $da, string $a): array
    {
        $driver = DB::getDriverName();
        $query = DB::table('interventi')
            ->where('stato', 'Completato')
            ->whereBetween('data_intervento', [$da, $a]);

        if ($driver === 'sqlite') {
            $query->selectRaw("strftime('%Y-%m', data_intervento) as mese, COUNT(*) as totale");
        } else {
            $query->selectRaw("DATE_FORMAT(data_intervento, '%Y-%m') as mese, COUNT(*) as totale");
        }

        $raw = $query->groupBy('mese')->orderBy('mese')->pluck('totale', 'mese');

        $start = Carbon::parse($da)->startOfMonth();
        $end = Carbon::parse($a)->startOfMonth();

        $labels = [];
        $values = [];
        $keys = [];
        foreach (CarbonPeriod::create($start, '1 month', $end) as $dt) {
            $key = $dt->format('Y-m');
            $labels[] = $dt->format('m/Y');
            $values[] = (int) ($raw[$key] ?? 0);
            $keys[] = $key;
        }

        return ['labels' => $labels, 'values' => $values, 'keys' => $keys];
    }

    private function formatEsitoLabel(?string $label): string
    {
        $value = $label ?: 'non_verificato';
        $map = [
            'verificato' => 'Verificato',
            'non_verificato' => 'Non verificato',
            'anomalie' => 'Anomalie',
            'sostituito' => 'Sostituito',
        ];
        if (isset($map[$value])) {
            return $map[$value];
        }

        return ucfirst(str_replace('_', ' ', $value));
    }
}
