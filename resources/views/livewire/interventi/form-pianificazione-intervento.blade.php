<div class="space-y-6">
    {{-- Barra filtri --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-4">
        <div class="flex flex-col md:flex-row md:items-end gap-4">
            <div>
                <label class="text-sm font-medium">Mese</label>
                <select wire:model.defer="meseSelezionato" class="select select-sm select-bordered">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">{{ Date::create()->month($m)->format('F') }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="text-sm font-medium">Zona</label>
                <select wire:model.defer="zonaFiltro" class="select select-sm select-bordered min-w-[180px]">
                    <option value="">Tutte</option>
                    @foreach($zoneConStato as $zonaRow)
                        <option value="{{ $zonaRow['value'] }}">{{ $zonaRow['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <button wire:click="applicaFiltri" class="btn btn-sm btn-primary">
                🔍 Applica filtri
            </button>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-600">
            <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100">
                Da pianificare: {{ count($clientiInScadenza) }}
            </span>
            <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100">
                Pianificati/Evasi: {{ count($clientiConInterventiEsistenti) }}
            </span>
            <span class="inline-flex items-center px-2 py-0.5 rounded bg-green-100 text-green-700">Evasa</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded bg-yellow-100 text-yellow-700">Pianificata</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-700">Da pianificare</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-100 text-indigo-700">* Zona pianificata completamente</span>
        </div>

        @php
            $totMinCorr = (int)($totaliZonaSelezionata['minuti_corrente'] ?? 0);
            $totMinSei = (int)($totaliZonaSelezionata['minuti_mese_sei'] ?? 0);
            $meseCorrLabel = Date::create()->month((int)$meseSelezionato)->format('F');
            $meseSeiLabel = Date::create()->month((int)$meseSei)->format('F');
        @endphp
        <div class="mt-3 rounded border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-900">
            <div class="font-semibold mb-1">
                Totale zona {{ $zonaFiltro !== '' ? 'selezionata' : '(tutte le zone visibili)' }}:
                {{ (int)($totaliZonaSelezionata['interventi'] ?? 0) }} interventi da pianificare
            </div>
            <div>
                Tempo {{ $meseCorrLabel }}:
                <span class="font-semibold">{{ intdiv($totMinCorr, 60) }} h {{ $totMinCorr % 60 }} min</span>
                · Tempo {{ $meseSeiLabel }} (+6 mesi):
                <span class="font-semibold">{{ intdiv($totMinSei, 60) }} h {{ $totMinSei % 60 }} min</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Colonna SINISTRA - Da pianificare --}}
        <div class="bg-white shadow p-4 rounded border">
            <h2 class="text-md font-semibold mb-2 text-gray-800">🟠 Interventi da pianificare</h2>
            <div class="max-h-[70vh] overflow-auto pr-1 space-y-2">
            @foreach ($clientiInScadenza as $cliente)
                @php
                    $btns = [];
                    $zonaFiltroNorm = mb_strtoupper(trim((string) $zonaFiltro));
                    $meseCorr = (int) $meseSelezionato;
                    $meseFraSei = (int) $meseSei;
                    $calcMinuti = function ($clienteObj, $sedeObj, int $meseRef) {
                        $minutiSede = $sedeObj?->minutiPerMese($meseRef);
                        if (!empty($minutiSede) && (int) $minutiSede > 0) {
                            return (int) $minutiSede;
                        }
                        $minutiCliente = $clienteObj->minutiPerMese($meseRef);
                        if (!empty($minutiCliente) && (int) $minutiCliente > 0) {
                            return (int) $minutiCliente;
                        }
                        return (int) ($sedeObj?->minuti_intervento ?? $clienteObj->minuti_intervento ?? 60);
                    };
                @endphp

                @if ($cliente->presidi->whereNull('sede_id')->isNotEmpty() && !$this->interventoEsistente($cliente->id, null))
                    @php
                        $zonaEntry = trim((string) ($cliente->zona ?? ''));
                        $zonaMatch = $zonaFiltroNorm === '' || mb_strtoupper($zonaEntry) === $zonaFiltroNorm;
                        if ($zonaMatch) {
                            $btns[] = [
                                'label' => 'Sede principale',
                                'sede_id' => null,
                                'extra' => null,
                                'zona' => $zonaEntry,
                                'minuti_corrente' => $calcMinuti($cliente, null, $meseCorr),
                                'minuti_mese_sei' => $calcMinuti($cliente, null, $meseFraSei),
                            ];
                        }
                    @endphp
                @endif

                @foreach ($cliente->sedi as $sede)
                    @php
                        $presidi = $sede->presidi;
                        $giaPianificato = $this->interventoEsistente($cliente->id, $sede->id);
                    @endphp
                    @if ($presidi->isNotEmpty() && !$giaPianificato)
                        @php
                            $zonaEntry = trim((string) ($sede->zona ?? $cliente->zona ?? ''));
                            $zonaMatch = $zonaFiltroNorm === '' || mb_strtoupper($zonaEntry) === $zonaFiltroNorm;
                            if ($zonaMatch) {
                                $btns[] = [
                                    'label' => $sede->nome,
                                    'sede_id' => $sede->id,
                                    'extra' => $sede->citta,
                                    'zona' => $zonaEntry,
                                    'minuti_corrente' => $calcMinuti($cliente, $sede, $meseCorr),
                                    'minuti_mese_sei' => $calcMinuti($cliente, $sede, $meseFraSei),
                                ];
                            }
                        @endphp
                    @endif
                @endforeach

                @if(count($btns))
                    <div class="border rounded-md p-3 bg-gray-50">
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-semibold text-sm text-gray-900">{{ $cliente->nome }}</div>
                            <span class="text-xs text-gray-500">{{ $cliente->zona ?? '—' }}</span>
                        </div>
                        @if(!empty($cliente->note))
                            <div class="mb-2 rounded border border-blue-200 bg-blue-50 px-2 py-1 text-xs text-blue-900 whitespace-pre-wrap">
                                <span class="font-semibold">Note anagrafica:</span> {{ $cliente->note }}
                            </div>
                        @endif
                        <div class="grid grid-cols-1 gap-2">
                            @foreach($btns as $b)
                                <div class="rounded border bg-white p-2">
                                    <button
                                        wire:click="caricaDati({{ $cliente->id }}, {{ $b['sede_id'] ? $b['sede_id'] : 'null' }}, '{{ $meseSelezionato }}', '{{ $annoSelezionato }}')"
                                        class="btn btn-xs btn-secondary w-full justify-start">
                                        📍 {{ $b['label'] }} @if($b['extra']) – {{ $b['extra'] }} @endif
                                    </button>
                                    <div class="mt-1 text-[11px] text-gray-600">
                                        Zona: <span class="font-semibold">{{ $b['zona'] !== '' ? $b['zona'] : '—' }}</span>
                                        · {{ $meseCorrLabel }}:
                                        <span class="font-semibold">{{ intdiv((int)$b['minuti_corrente'], 60) }} h {{ (int)$b['minuti_corrente'] % 60 }} min</span>
                                        · {{ $meseSeiLabel }} (+6):
                                        <span class="font-semibold">{{ intdiv((int)$b['minuti_mese_sei'], 60) }} h {{ (int)$b['minuti_mese_sei'] % 60 }} min</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
            </div>
        </div>

        {{-- Colonna DESTRA - Pianificati / Completati --}}
        <div class="bg-white shadow p-4 rounded border">
            <h2 class="text-md font-semibold mb-2 text-gray-800">🟡 Interventi già pianificati o evasi</h2>
            <div class="max-h-[70vh] overflow-auto pr-1 space-y-2">
            @foreach ($clientiConInterventiEsistenti as $cliente)
                @php
                    $zonaFiltroNorm = mb_strtoupper(trim((string) $zonaFiltro));
                @endphp
                {{-- Sede principale --}}
                @if ($cliente->presidi->whereNull('sede_id')->isNotEmpty())
                    @php
                        $giaEvasa = $this->interventoEvasa($cliente->id, null);
                        $giaPianificato = $this->interventoEsistente($cliente->id, null);
                        $zonaPrincipale = trim((string) ($cliente->zona ?? ''));
                        $zonaMatch = $zonaFiltroNorm === '' || mb_strtoupper($zonaPrincipale) === $zonaFiltroNorm;
                    @endphp
                    @if (($giaEvasa || $giaPianificato) && $zonaMatch)
                        <div class="border rounded-md p-3 bg-gray-50 mb-2">
                            <div class="font-semibold text-sm text-gray-900">{{ $cliente->nome }}</div>
                            <div class="text-xs mt-1">
                                📍 Sede principale:
                                <span class="text-{{ $giaEvasa ? 'green' : 'yellow' }}-600 font-semibold">
                                    {{ $giaEvasa ? 'Completata' : 'Pianificata' }}
                                </span>
                            </div>
                        </div>
                    @endif
                @endif

                {{-- Sedi secondarie --}}
                @foreach ($cliente->sedi as $sede)
                    @php
                        $presidi = $sede->presidi;
                        $giaEvasa = $this->interventoEvasa($cliente->id, $sede->id);
                        $giaPianificato = $this->interventoEsistente($cliente->id, $sede->id);
                        $zonaSede = trim((string) ($sede->zona ?? $cliente->zona ?? ''));
                        $zonaMatch = $zonaFiltroNorm === '' || mb_strtoupper($zonaSede) === $zonaFiltroNorm;
                    @endphp
                    @if ($presidi->isNotEmpty() && ($giaEvasa || $giaPianificato) && $zonaMatch)
                        <div class="border rounded-md p-3 bg-gray-50 mb-2">
                            <div class="font-semibold text-sm text-gray-900">{{ $cliente->nome }}</div>
                            <div class="text-xs mt-1">
                                📍 {{ $sede->nome }} – {{ $sede->citta }}:
                                <span class="text-{{ $giaEvasa ? 'green' : 'yellow' }}-600 font-semibold">
                                    {{ $giaEvasa ? 'Completata' : 'Pianificata' }}
                                </span>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endforeach
            </div>
        </div>

        {{-- Colonna FORM --}}
        <div class="bg-white shadow p-4 rounded border">
            <h2 class="text-md font-semibold mb-2 text-gray-800">📅 Pianifica intervento</h2>
            @if($clienteId)
                <p class="text-sm text-gray-700 mb-1">
                    Cliente ID: {{ $clienteId }} |
                    Sede: {{ $sedeId ? 'ID '.$sedeId : 'Sede principale' }}
                </p>

                <div class="mb-2">
                    <label class="block text-sm mb-1">Data intervento</label>
                    <input type="date" wire:model="dataIntervento" class="input input-bordered w-full max-w-xs">
                </div>

                <div class="mb-3">
                    <label class="block text-sm mb-1">Tecnici da assegnare ({{ count($tecniciDisponibili) }})</label>
                    <div class="space-y-2">
                        @foreach($tecniciDisponibili as $tec)
                            @php
                                $tecnicoSelezionato = in_array((int) $tec->id, array_map('intval', (array) $tecnici), true);
                            @endphp
                            <div class="border rounded p-2 {{ $tecnicoSelezionato ? 'bg-red-50 border-red-200' : 'bg-gray-50' }}">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 items-end">
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" wire:model.live="tecnici" value="{{ $tec->id }}" class="mr-2">
                                        <span class="font-medium text-sm">{{ $tec->name }}</span>
                                    </label>
                                    <div>
                                        <label class="block text-xs text-gray-600">Orario appuntamento</label>
                                        <input type="time"
                                               wire:model.defer="tecniciOrari.{{ $tec->id }}.inizio"
                                               @disabled(!$tecnicoSelezionato)
                                               class="input input-bordered input-sm w-full {{ $tecnicoSelezionato ? '' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}">
                                        @error("tecniciOrari.$tec->id.inizio") <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="text-xs text-gray-500 mt-2">
                        L'orario viene richiesto qui in pianificazione per ogni tecnico selezionato.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="block text-sm mb-1">Note intervento</label>
                    <textarea wire:model.defer="noteIntervento" class="input input-bordered w-full" rows="3" placeholder="Note per i tecnici (es. orari apertura)"></textarea>
                </div>

                <button wire:click="pianifica" class="btn btn-primary btn-sm w-full">
                    ✅ Conferma pianificazione
                </button>
            @else
                <div class="text-sm text-gray-500">
                    Seleziona un cliente dalla colonna “Da pianificare” per iniziare.
                </div>
            @endif
        </div>
    </div>
</div>
