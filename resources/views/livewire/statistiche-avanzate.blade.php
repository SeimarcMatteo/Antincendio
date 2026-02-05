@php
    $chart = $charts[$graficoSelezionato] ?? null;
    $summary = $summary ?? [];
@endphp

<div id="statistiche-root" class="min-h-screen bg-slate-50 text-slate-900">
    <div class="max-w-7xl mx-auto px-5 py-8 space-y-6">
        <div class="bg-gradient-to-r from-rose-600 via-red-500 to-orange-500 text-white rounded-2xl p-6 shadow-lg">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <div class="text-sm uppercase tracking-wide text-white/80">Dashboard</div>
                    <h1 class="text-2xl font-semibold">Statistiche Interventi</h1>
                    <p class="text-sm text-white/80">Periodo {{ $dataDa }} → {{ $dataA }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="preset('month')" class="px-3 py-1.5 rounded-full text-xs font-medium {{ $presetAttivo === 'month' ? 'bg-white text-slate-900' : 'bg-white/15 text-white/90' }}">Mese</button>
                    <button wire:click="preset('30d')" class="px-3 py-1.5 rounded-full text-xs font-medium {{ $presetAttivo === '30d' ? 'bg-white text-slate-900' : 'bg-white/15 text-white/90' }}">30 giorni</button>
                    <button wire:click="preset('90d')" class="px-3 py-1.5 rounded-full text-xs font-medium {{ $presetAttivo === '90d' ? 'bg-white text-slate-900' : 'bg-white/15 text-white/90' }}">90 giorni</button>
                    <button wire:click="preset('ytd')" class="px-3 py-1.5 rounded-full text-xs font-medium {{ $presetAttivo === 'ytd' ? 'bg-white text-slate-900' : 'bg-white/15 text-white/90' }}">YTD</button>
                    <button wire:click="preset('year')" class="px-3 py-1.5 rounded-full text-xs font-medium {{ $presetAttivo === 'year' ? 'bg-white text-slate-900' : 'bg-white/15 text-white/90' }}">Ultimo anno</button>
                    @if($presetAttivo === 'custom')
                        <span class="px-2 py-1 rounded-full text-[10px] uppercase tracking-wide bg-white/25 text-white/90">Personalizzato</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
                <div class="text-xs uppercase text-slate-500">Interventi</div>
                <div class="text-2xl font-semibold text-slate-800">{{ number_format($summary['interventi'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
                <div class="text-xs uppercase text-slate-500">Clienti</div>
                <div class="text-2xl font-semibold text-slate-800">{{ number_format($summary['clienti'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
                <div class="text-xs uppercase text-slate-500">Tecnici attivi</div>
                <div class="text-2xl font-semibold text-slate-800">{{ number_format($summary['tecnici'] ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
                <div class="text-xs uppercase text-slate-500">Durata media</div>
                <div class="text-2xl font-semibold text-slate-800">{{ number_format($summary['durata_media'] ?? 0, 0, ',', '.') }} min</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
                <div class="text-xs uppercase text-slate-500">Presidi verificati</div>
                <div class="text-2xl font-semibold text-slate-800">{{ number_format($summary['presidi'] ?? 0, 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="text-xs uppercase text-slate-500">Dal</label>
                        <input type="date" wire:model.defer="dataDa" class="input input-bordered w-full">
                    </div>
                    <div>
                        <label class="text-xs uppercase text-slate-500">Al</label>
                        <input type="date" wire:model.defer="dataA" class="input input-bordered w-full">
                    </div>
                    <div class="lg:col-span-2 flex items-end">
                        <button wire:click="applyFilters" class="btn btn-primary w-full">Aggiorna dati</button>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ([
                        'tecnici' => 'Tecnici',
                        'clienti' => 'Clienti',
                        'durata' => 'Durata',
                        'categoria' => 'Categorie',
                        'anomalie' => 'Anomalie',
                        'trend' => 'Trend',
                        'esiti' => 'Esiti'
                    ] as $key => $label)
                        <button wire:click="$set('graficoSelezionato','{{ $key }}')"
                            class="px-3 py-1.5 rounded-full text-xs font-medium border
                            {{ $graficoSelezionato === $key ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-400' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @php
            $chartAccent = [
                'tecnici' => 'border-red-200',
                'clienti' => 'border-orange-200',
                'durata' => 'border-violet-200',
                'categoria' => 'border-emerald-200',
                'anomalie' => 'border-amber-200',
                'trend' => 'border-sky-200',
                'esiti' => 'border-slate-200',
            ];
        @endphp
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border-2 {{ $chartAccent[$graficoSelezionato] ?? 'border-slate-200' }} p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-800">{{ $chart['title'] ?? 'Grafico' }}</h2>
                        <div class="text-xs text-slate-500">{{ $chart['subtitle'] ?? '' }}</div>
                    </div>
                    <div class="text-xs text-slate-400">Aggiornato: {{ now()->format('d/m/Y H:i') }}</div>
                </div>

                @if(empty($chart['labels'] ?? []))
                    <div class="border border-dashed border-slate-300 rounded-xl p-8 text-center text-slate-500">
                        Nessun dato disponibile per il periodo selezionato.
                    </div>
                @else
                    <div wire:ignore class="relative h-[360px]">
                        <canvas id="statistiche-canvas"></canvas>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-800">
                        @if(!empty($drilldown['rows'] ?? []))
                            Dettaglio selezione
                        @else
                            Dettaglio
                        @endif
                    </h3>
                    @if(!empty($drilldown['rows'] ?? []))
                        <div class="flex items-center gap-2">
                            <button wire:click="exportDrilldownCsv" class="text-xs text-slate-700 border border-slate-200 rounded px-2 py-1 hover:border-slate-400">
                                Esporta CSV
                            </button>
                            <button wire:click="exportDrilldownPdf" class="text-xs text-slate-700 border border-slate-200 rounded px-2 py-1 hover:border-slate-400">
                                Esporta PDF
                            </button>
                            <button wire:click="clearDrilldown" class="text-xs text-slate-600 hover:text-slate-900 border border-slate-200 rounded px-2 py-1">
                                Chiudi
                            </button>
                        </div>
                    @endif
                </div>

                @if(!empty($drilldown['rows'] ?? []))
                    <div class="text-xs text-slate-500 mb-2">{{ $drilldown['title'] ?? '' }}</div>
                    <div class="overflow-auto max-h-[360px]">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-slate-500 uppercase">
                                <tr>
                                    @foreach(($drilldown['headers'] ?? []) as $head)
                                        <th class="text-left py-2">{{ $head }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach(($drilldown['rows'] ?? []) as $row)
                                    <tr class="text-slate-700">
                                        @foreach($row as $cell)
                                            <td class="py-2 pr-2">{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-2">Mostro fino a 300 righe per performance.</div>
                @else
                    @if(empty($chart['table_rows'] ?? []))
                        <div class="text-sm text-slate-500">Nessun dato da mostrare.</div>
                    @else
                        <div class="overflow-auto max-h-[360px]">
                            <table class="w-full text-sm">
                                <thead class="text-xs text-slate-500 uppercase">
                                    <tr>
                                        @foreach(($chart['table_headers'] ?? []) as $head)
                                            <th class="text-left py-2">{{ $head }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach(($chart['table_rows'] ?? []) as $row)
                                        <tr class="text-slate-700 hover:bg-slate-50 cursor-pointer"
                                            wire:click="selectDrilldownFromTable({{ $loop->index }})">
                                            @foreach($row as $cell)
                                                <td class="py-2 pr-2">{{ $cell }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-[11px] text-slate-400 mt-2">Clicca un elemento del grafico o una riga per il dettaglio.</div>
                    @endif
                @endif
            </div>
        </div>

        <script id="statistiche-data" type="application/json">
            {!! json_encode($charts) !!}
        </script>
        <script id="statistiche-selected" type="application/json">
            {!! json_encode($graficoSelezionato) !!}
        </script>
    </div>
</div>

<script>
    function renderStatisticheChart() {
        const dataTag = document.getElementById('statistiche-data');
        const selectedTag = document.getElementById('statistiche-selected');
        const canvas = document.getElementById('statistiche-canvas');
        if (!dataTag || !selectedTag || !canvas) return;

        let charts = {};
        let selected = null;
        try {
            charts = JSON.parse(dataTag.textContent || '{}');
            selected = JSON.parse(selectedTag.textContent || 'null');
        } catch (e) {
            console.error('Statistiche JSON error', e);
            return;
        }

        const cfg = charts[selected];
        if (!cfg) return;

        const ctx = canvas.getContext('2d');
        if (window.statisticheChart) window.statisticheChart.destroy();

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: cfg.type === 'doughnut' ? 'bottom' : 'top',
                    labels: {
                        color: '#475569',
                    },
                }
            },
            onClick: (event, elements) => {
                if (!elements || !elements.length) return;
                const element = elements[0];
                const index = element.index;
                const datasetIndex = element.datasetIndex ?? 0;
                const label = (cfg.labels || [])[index];
                const key = (cfg.keys || [])[index] ?? label;
                const datasetLabel = (cfg.datasets || [])[datasetIndex]?.label ?? null;

                if (window.Livewire) {
                    window.Livewire.dispatch('statistiche:drilldown', {
                        chart: selected,
                        label,
                        key,
                        dataset: datasetLabel,
                    });
                }
            },
        };
        if (cfg.type !== 'doughnut') {
            options.scales = {
                x: { ticks: { color: '#64748B' } },
                y: { beginAtZero: true, ticks: { precision: 0, color: '#64748B' } },
            };
        }
        if (cfg.axis) {
            options.indexAxis = cfg.axis;
        }

        window.statisticheChart = new Chart(ctx, {
            type: cfg.type || 'bar',
            data: {
                labels: cfg.labels || [],
                datasets: (cfg.datasets || []).map(ds => ({
                    ...ds,
                    borderWidth: ds.borderWidth ?? 0,
                })),
            },
            options,
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderStatisticheChart();
    });

    window.addEventListener('statistiche:refresh', () => {
        setTimeout(renderStatisticheChart, 50);
    });
</script>
