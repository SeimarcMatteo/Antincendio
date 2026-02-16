<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold text-red-600">Prezzi Anomalie</h2>
            <p class="text-sm text-gray-600">Configura il prezzo da aggiungere all'intervento quando l'anomalia viene marcata come riparata.</p>
        </div>
        <button type="button"
                wire:click="salvaTutti"
                class="px-3 py-2 rounded border border-red-600 bg-red-600 text-white text-sm hover:bg-red-700">
            Salva Tutto
        </button>
    </div>

    @if(!$hasPrezzoColumn)
        <div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            Colonna <code>anomalie.prezzo</code> non trovata. Esegui la migration sul server.
        </div>
    @endif

    @forelse($anomalieByCategoria as $categoria => $anomalieCategoria)
        <div class="border rounded bg-white shadow-sm">
            <div class="px-4 py-2 border-b bg-gray-50 font-semibold text-gray-700">
                {{ $categoria ?: 'Senza categoria' }}
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-600">
                        <tr>
                            <th class="px-3 py-2 text-left">Anomalia</th>
                            <th class="px-3 py-2 text-right">Prezzo (€)</th>
                            <th class="px-3 py-2 text-center">Attiva</th>
                            <th class="px-3 py-2 text-right">Azione</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($anomalieCategoria as $anomalia)
                            <tr class="border-t" wire:key="anomalia-prezzo-{{ $anomalia->id }}">
                                <td class="px-3 py-2">
                                    {{ $anomalia->etichetta }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <input type="text"
                                           wire:model.live.debounce.250ms="prezzi.{{ $anomalia->id }}"
                                           wire:blur="salvaRiga({{ $anomalia->id }})"
                                           wire:keydown.enter.prevent="salvaRiga({{ $anomalia->id }})"
                                           class="w-28 border rounded px-2 py-1 text-right {{ !empty($invalidPrezzi[$anomalia->id] ?? null) ? 'border-red-500 bg-red-50' : 'border-gray-300' }}"
                                           placeholder="0,00">
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <input type="checkbox"
                                           wire:model="attive.{{ $anomalia->id }}"
                                           class="h-5 w-5 border-gray-300 rounded">
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button type="button"
                                            wire:click="salvaRiga({{ $anomalia->id }})"
                                            class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50 text-xs">
                                        Salva
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="rounded border border-gray-200 bg-white p-4 text-sm text-gray-500">
            Nessuna anomalia configurata.
        </div>
    @endforelse
</div>
