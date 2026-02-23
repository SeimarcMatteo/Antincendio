<div class="space-y-6">

    {{-- Header: selezione data + vista --}}
    <div class="flex flex-col gap-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="precedenteGiornoPianificato" class="btn btn-sm btn-outline">
                ⏮ Giorno pianificato prec.
            </button>
            <button wire:click="giornoPrecedente" class="btn btn-sm btn-outline">
                ⬅️ Giorno -1
            </button>
            <input type="date" wire:model.live="dataSelezionata" class="input input-sm input-bordered" />
            <button wire:click="giornoSuccessivo" class="btn btn-sm btn-outline">
                Giorno +1 ➡️
            </button>
            <button wire:click="prossimoGiornoPianificato" class="btn btn-sm btn-outline">
                Giorno pianificato succ. ⏭
            </button>
            <button wire:click="vaiAOggi" class="btn btn-sm btn-secondary">
                Oggi
            </button>
            <button wire:click="caricaInterventi" class="btn btn-sm btn-secondary">
                🔄 Aggiorna
            </button>
        </div>
        <div class="text-xs text-gray-600">
            Data selezionata: <span class="font-semibold">{{ \Carbon\Carbon::parse($dataSelezionata)->format('d/m/Y') }}</span>
            · Interventi trovati: <span class="font-semibold">{{ $interventi->count() }}</span>
        </div>
    </div>

    {{-- Nessun intervento --}}
    @if ($interventi->isEmpty())
        <div class="text-gray-500 italic">Nessun intervento per la data selezionata.</div>

    @else
        {{-- Vista a tabella --}}
        @if ($vista === 'tabella')
            <table class="table table-sm w-full">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Sede</th>
                        <th>Orario</th>
                        <th>Data</th>
                        <th>Stato</th>
                        <th>Note</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($interventi as $intervento)
                        <tr>
                            <td>
                                <a href="{{ route('clienti.mostra', $intervento->cliente_id) }}" class="text-red-700 hover:text-red-900 underline">
                                    {{ $intervento->cliente->nome }}
                                </a>
                                <div class="text-xs text-gray-600 mt-1 space-y-0.5">
                                    <div>{{ $intervento->cliente->indirizzo ?? '—' }}, {{ $intervento->cliente->citta ?? '—' }}</div>
                                    <div>Zona: {{ $intervento->cliente->zona ?? '—' }}</div>
                                    <div>Tel: {{ $intervento->cliente->telefono ?? '—' }} · Email: {{ $intervento->cliente->email ?? '—' }}</div>
                                    @if(!empty($intervento->cliente->note))
                                        <div class="mt-2 p-2 rounded border-2 border-blue-200 bg-blue-50">
                                            <div class="text-[11px] uppercase font-bold text-blue-800">Note Anagrafica</div>
                                            <div class="text-sm font-extrabold text-gray-900 whitespace-pre-wrap">{{ $intervento->cliente->note }}</div>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $intervento->sede->nome ?? 'Sede principale' }}</td>
                            <td class="text-xs">
                                <span class="font-medium">
                                    {{ optional($intervento->pivot?->scheduled_start_at)->format('H:i') ?? '—' }}
                                </span>
                                -
                                <span class="font-medium">
                                    {{ optional($intervento->pivot?->scheduled_end_at)->format('H:i') ?? '—' }}
                                </span>
                            </td>
                            <td>{{ \Carbon\Carbon::parse($intervento->data_intervento)->format('d/m/Y') }}</td>
                            <td>{{ $intervento->stato }}</td>
                            <td class="min-w-[220px]">
                                @if(!empty($noteByIntervento[$intervento->id]))
                                    <div class="mb-2 p-2 rounded border-2 border-yellow-200 bg-yellow-50">
                                        <div class="text-[11px] uppercase font-bold text-yellow-800">Note Intervento</div>
                                        <div class="text-sm font-extrabold text-gray-900 whitespace-pre-wrap">{{ $noteByIntervento[$intervento->id] }}</div>
                                    </div>
                                @endif
                                <div class="flex items-start gap-2">
                                    <textarea wire:model.defer="noteByIntervento.{{ $intervento->id }}" rows="2" class="w-full text-xs border-gray-300 rounded px-2 py-1" placeholder="Note intervento"></textarea>
                                    <button wire:click="salvaNoteIntervento({{ $intervento->id }})" class="btn btn-xs btn-primary whitespace-nowrap">
                                        Salva
                                    </button>
                                </div>
                            </td>
                            <td>
                                <button wire:click="apriIntervento({{ $intervento->id }})" class="btn btn-xs btn-primary">
                                    ✏️
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        {{-- Vista a schede --}}
        @else
            <div class="space-y-4">
                @foreach ($interventi as $intervento)
                    <div class="border rounded shadow p-4 bg-white">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="font-semibold text-lg">
                                    <a href="{{ route('clienti.mostra', $intervento->cliente_id) }}" class="text-red-700 hover:text-red-900 underline">
                                        {{ $intervento->cliente->nome }}
                                    </a>
                                </h3>
                                <p class="text-sm text-gray-600">
                                    {{ optional($intervento->pivot?->scheduled_start_at)->format('H:i') ?? '—' }} -
                                    {{ optional($intervento->pivot?->scheduled_end_at)->format('H:i') ?? '—' }} —
                                    {{ $intervento->sede->nome ?? 'Sede principale' }} —
                                    {{ \Carbon\Carbon::parse($intervento->data_intervento)->format('d/m/Y') }} —
                                    Stato: <strong>{{ $intervento->stato }}</strong>
                                </p>
                                <div class="text-xs text-gray-600 mt-1 space-y-0.5">
                                    <div>{{ $intervento->cliente->indirizzo ?? '—' }}, {{ $intervento->cliente->citta ?? '—' }}</div>
                                    <div>Zona: {{ $intervento->cliente->zona ?? '—' }}</div>
                                    <div>Tel: {{ $intervento->cliente->telefono ?? '—' }} · Email: {{ $intervento->cliente->email ?? '—' }}</div>
                                    @if(!empty($intervento->cliente->note))
                                        <div class="mt-2 p-2 rounded border-2 border-blue-200 bg-blue-50">
                                            <div class="text-[11px] uppercase font-bold text-blue-800">Note Anagrafica</div>
                                            <div class="text-sm font-extrabold text-gray-900 whitespace-pre-wrap">{{ $intervento->cliente->note }}</div>
                                        </div>
                                    @endif
                                </div>
                                <div class="mt-2">
                                    <label class="text-xs text-gray-600">Note intervento</label>
                                    @if(!empty($noteByIntervento[$intervento->id]))
                                        <div class="mt-1 mb-2 p-2 rounded border-2 border-yellow-200 bg-yellow-50">
                                            <div class="text-[11px] uppercase font-bold text-yellow-800">Note Intervento</div>
                                            <div class="text-sm font-extrabold text-gray-900 whitespace-pre-wrap">{{ $noteByIntervento[$intervento->id] }}</div>
                                        </div>
                                    @endif
                                    <div class="flex items-start gap-2 mt-1">
                                        <textarea wire:model.defer="noteByIntervento.{{ $intervento->id }}" rows="2" class="w-full text-xs border-gray-300 rounded px-2 py-1" placeholder="Note intervento"></textarea>
                                        <button wire:click="salvaNoteIntervento({{ $intervento->id }})" class="btn btn-xs btn-primary whitespace-nowrap">
                                            Salva
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <button wire:click="apriIntervento({{ $intervento->id }})" class="btn btn-sm btn-primary">
                                ✏️ Evadi
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
