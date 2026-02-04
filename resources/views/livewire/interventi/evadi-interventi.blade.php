<div class="space-y-6">

    {{-- Header: selezione data + vista --}}
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-2">
            <input type="date" wire:model.lazy="dataSelezionata" class="input input-sm input-bordered" />
            <button wire:click="caricaInterventi" class="btn btn-sm btn-secondary">
                🔄 Carica interventi
            </button>
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
                        <th>Data</th>
                        <th>Stato</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($interventi as $intervento)
                        <tr>
                            <td>{{ $intervento->cliente->nome }}</td>
                            <td>{{ $intervento->sede->nome ?? 'Sede principale' }}</td>
                            <td>{{ \Carbon\Carbon::parse($intervento->data_intervento)->format('d/m/Y') }}</td>
                            <td>{{ $intervento->stato }}</td>
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
                                <h3 class="font-semibold text-lg">{{ $intervento->cliente->nome }}</h3>
                                <p class="text-sm text-gray-600">
                                    {{ $intervento->sede->nome ?? 'Sede principale' }} —
                                    {{ \Carbon\Carbon::parse($intervento->data_intervento)->format('d/m/Y') }} —
                                    Stato: <strong>{{ $intervento->stato }}</strong>
                                </p>
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
