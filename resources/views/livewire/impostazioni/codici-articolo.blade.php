<div class="space-y-6">
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Codici Articolo - Tipi Estintori</h2>
                <p class="text-sm text-gray-600">
                    Gestisci i codici usati nel confronto ordine/intervento per noleggio e FULL SERVICE.
                </p>
            </div>
            <button type="button"
                    wire:click="salvaTuttiEstintori"
                    class="rounded bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">
                Salva tutti estintori
            </button>
        </div>

        <div class="overflow-x-auto rounded border">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left">Sigla</th>
                        <th class="px-3 py-2 text-left">Descrizione</th>
                        <th class="px-3 py-2 text-left">Codice NOLEGGIO</th>
                        @if($hasCodiceArticoloFull)
                            <th class="px-3 py-2 text-left">Codice FULL SERVICE</th>
                        @endif
                        <th class="px-3 py-2 text-right">Azione</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tipiEstintori as $tipo)
                        <tr class="border-t" wire:key="cod-est-{{ $tipo->id }}">
                            <td class="px-3 py-2 font-mono">{{ $tipo->sigla }}</td>
                            <td class="px-3 py-2">{{ $tipo->descrizione }}</td>
                            <td class="px-3 py-2">
                                <input type="text"
                                       wire:model.defer="codiciEstintori.{{ $tipo->id }}"
                                       class="w-full rounded border border-gray-300 px-2 py-1"
                                       placeholder="Codice noleggio">
                            </td>
                            @if($hasCodiceArticoloFull)
                                <td class="px-3 py-2">
                                    <input type="text"
                                           wire:model.defer="codiciEstintoriFull.{{ $tipo->id }}"
                                           class="w-full rounded border border-gray-300 px-2 py-1"
                                           placeholder="Codice full service">
                                </td>
                            @endif
                            <td class="px-3 py-2 text-right">
                                <button type="button"
                                        wire:click="salvaCodiceEstintore({{ $tipo->id }})"
                                        class="rounded border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100">
                                    Salva
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $hasCodiceArticoloFull ? 5 : 4 }}" class="px-3 py-4 text-center text-gray-500">
                                Nessuna tipologia estintore trovata.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Codici Articolo - Tipi Presidio</h2>
                <p class="text-sm text-gray-600">
                    Gestisci i codici per idranti e porte.
                </p>
            </div>
            @if($hasCodiceArticoloPresidi)
                <button type="button"
                        wire:click="salvaTuttiPresidi"
                        class="rounded bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">
                    Salva tutti presidi
                </button>
            @endif
        </div>

        @if(!$hasCodiceArticoloPresidi)
            <div class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                La colonna <code>tipi_presidio.codice_articolo_fatturazione</code> non esiste nel database.
            </div>
        @else
        <div class="overflow-x-auto rounded border">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left">Categoria</th>
                        <th class="px-3 py-2 text-left">Tipologia</th>
                        <th class="px-3 py-2 text-left">Codice Articolo</th>
                        <th class="px-3 py-2 text-right">Azione</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tipiPresidio as $tipo)
                        <tr class="border-t" wire:key="cod-pres-{{ $tipo->id }}">
                            <td class="px-3 py-2">
                                <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">
                                    {{ $tipo->categoria }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ $tipo->nome }}</td>
                            <td class="px-3 py-2">
                                <input type="text"
                                       wire:model.defer="codiciPresidi.{{ $tipo->id }}"
                                       class="w-full rounded border border-gray-300 px-2 py-1"
                                       placeholder="Codice articolo">
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button"
                                        wire:click="salvaCodicePresidio({{ $tipo->id }})"
                                        class="rounded border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100">
                                    Salva
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-gray-500">
                                Nessuna tipologia presidio trovata.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
