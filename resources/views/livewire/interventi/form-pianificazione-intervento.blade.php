<div>
    <div class="flex items-end gap-4">
        {{-- Mese --}}
        <div>
            <label class="text-sm font-medium">Mese</label>
            <input type="number" wire:model.defer="meseSelezionato" min="1" max="12" placeholder="Mese"
            class="input input-sm input-bordered">
        </div>

        {{-- Zona --}}
        <div>
            <label class="text-sm font-medium">Zona</label>
            <select wire:model.defer="zonaFiltro" class="select select-sm select-bordered">
                <option value="">Tutte</option>
                @foreach($zoneDisponibili as $zona)
                    <option value="{{ $zona }}">{{ $zona }}</option>
                @endforeach
            </select>
        </div>

        {{-- Tasto cerca --}}
        <div class="pt-4">
            <button wire:click="applicaFiltri" class="btn btn-sm btn-primary">
                🔍 Applica filtri
            </button>
        </div>
    </div>

 
    {{-- Legenda --}}
    <div class="mb-4 text-sm text-gray-600 flex items-center gap-4">
        <span><span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-1"></span> Evasa</span>
        <span><span class="inline-block w-3 h-3 bg-yellow-400 rounded-full mr-1"></span> Pianificata</span>
        <span><span class="inline-block w-3 h-3 bg-gray-400 rounded-full mr-1"></span> Da pianificare</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Colonna SINISTRA - Da pianificare --}}
        <div class="bg-white shadow p-4 rounded border">
            <h2 class="text-md font-semibold mb-2 text-gray-800">🟠 Interventi da pianificare</h2>
            @foreach ($clientiInScadenza as $cliente)
                @php
                    $clienteEvasa = $this->interventoEvasa($cliente->id, null);
                    $clienteEsistente = $this->interventoEsistente($cliente->id, null);
                @endphp

                {{-- Sede principale --}}
                @if ($cliente->presidi->whereNull('sede_id')->isNotEmpty() && !$clienteEsistente && !$clienteEvasa)
                <div class="mb-2">
    <button
        wire:click="caricaDati({{ $cliente->id }}, null, '{{ $meseSelezionato }}', '{{ $annoSelezionato }}')"
        class="w-full text-left border rounded px-3 py-2 hover:bg-gray-50 transition
               flex flex-col items-start"
    >
        <div class="font-bold text-sm text-gray-900">
            {{ $cliente->nome }}
        </div>
        <div class="text-sm text-gray-700">
            📍 Sede principale
        </div>
    </button>
</div>

                @endif

                {{-- Sedi secondarie --}}
                @foreach ($cliente->sedi as $sede)
                    @php
                        $presidi = $sede->presidi;
                        $giaEvasa = $this->interventoEvasa($cliente->id, $sede->id);
                        $giaPianificato = $this->interventoEsistente($cliente->id, $sede->id);
                    @endphp
                    @if ($presidi->isNotEmpty() && !$giaEvasa && !$giaPianificato)
                        <div class="mb-2">
                            <div class="font-bold text-sm">{{ $cliente->nome }}</div>
                            <button wire:click="caricaDati({{ $cliente->id }}, {{ $sede->id }}, '{{ $meseSelezionato }}', '{{ $annoSelezionato }}')"
                                class="btn btn-xs btn-secondary mt-1">
                                📍 {{ $sede->nome }} – {{ $sede->citta }}
                            </button>
                        </div>
                    @endif
                @endforeach
            @endforeach
        </div>

        {{-- Colonna DESTRA - Pianificati / Completati --}}
        <div class="bg-white shadow p-4 rounded border">
            <h2 class="text-md font-semibold mb-2 text-gray-800">🟡 Interventi già pianificati o evasi</h2>
            @foreach ($clientiConInterventiEsistenti as $cliente)
                {{-- Sede principale --}}
                @if ($cliente->presidi->whereNull('sede_id')->isNotEmpty())
                    @php
                        $giaEvasa = $this->interventoEvasa($cliente->id, null);
                        $giaPianificato = $this->interventoEsistente($cliente->id, null);
                    @endphp
                    @if ($giaEvasa || $giaPianificato)
                        <div class="mb-2">
                            <div class="font-bold text-sm">{{ $cliente->nome }}</div>
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
                        <div class="mb-2">
                            <div class="font-bold text-sm">{{ $cliente->nome }}</div>
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

    {{$clienteId}}
    {{-- Form pianificazione --}}
    @if($clienteId)
        <div class="bg-white shadow p-4 rounded border mb-6">
            <h2 class="text-lg font-bold mb-2">📅 Pianifica intervento</h2>

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

            <button wire:click="pianifica" class="btn btn-primary btn-sm">
                ✅ Conferma pianificazione
            </button>
        </div>
    @endif
</div>
