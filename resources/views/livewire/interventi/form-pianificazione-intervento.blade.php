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
                    @foreach($zoneDisponibili as $zona)
                        <option value="{{ $zona }}">{{ $zona }}</option>
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
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Colonna SINISTRA - Da pianificare --}}
        <div class="bg-white shadow p-4 rounded border">
            <h2 class="text-md font-semibold mb-2 text-gray-800">🟠 Interventi da pianificare</h2>
            <div class="max-h-[70vh] overflow-auto pr-1 space-y-2">
            @foreach ($clientiInScadenza as $cliente)
                @php
                    $clienteEvasa = $this->interventoEvasa($cliente->id, null);
                    $clienteEsistente = $this->interventoEsistente($cliente->id, null);
                    $btns = [];
                @endphp

                @if ($cliente->presidi->whereNull('sede_id')->isNotEmpty() && !$clienteEsistente && !$clienteEvasa)
                    @php
                        $btns[] = [
                            'label' => 'Sede principale',
                            'sede_id' => null,
                            'extra' => null,
                        ];
                    @endphp
                @endif

                @foreach ($cliente->sedi as $sede)
                    @php
                        $presidi = $sede->presidi;
                        $giaEvasa = $this->interventoEvasa($cliente->id, $sede->id);
                        $giaPianificato = $this->interventoEsistente($cliente->id, $sede->id);
                    @endphp
                    @if ($presidi->isNotEmpty() && !$giaEvasa && !$giaPianificato)
                        @php
                            $btns[] = [
                                'label' => $sede->nome,
                                'sede_id' => $sede->id,
                                'extra' => $sede->citta,
                            ];
                        @endphp
                    @endif
                @endforeach

                @if(count($btns))
                    <div class="border rounded-md p-3 bg-gray-50">
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-semibold text-sm text-gray-900">{{ $cliente->nome }}</div>
                            <span class="text-xs text-gray-500">{{ $cliente->zona ?? '—' }}</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($btns as $b)
                                <button
                                    wire:click="caricaDati({{ $cliente->id }}, {{ $b['sede_id'] ? $b['sede_id'] : 'null' }}, '{{ $meseSelezionato }}', '{{ $annoSelezionato }}')"
                                    class="btn btn-xs btn-secondary">
                                    📍 {{ $b['label'] }} @if($b['extra']) – {{ $b['extra'] }} @endif
                                </button>
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
                {{-- Sede principale --}}
                @if ($cliente->presidi->whereNull('sede_id')->isNotEmpty())
                    @php
                        $giaEvasa = $this->interventoEvasa($cliente->id, null);
                        $giaPianificato = $this->interventoEsistente($cliente->id, null);
                    @endphp
                    @if ($giaEvasa || $giaPianificato)
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
                    @endphp
                    @if ($presidi->isNotEmpty() && ($giaEvasa || $giaPianificato))
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
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($tecniciDisponibili as $tec)
                            <label class="inline-flex items-center">
                                <input type="checkbox" wire:model="tecnici" value="{{ $tec->id }}" class="mr-2">
                                {{ $tec->name }}
                            </label>
                        @endforeach
                    </div>
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
