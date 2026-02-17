<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-red-700">Prezzi Anomalie</h2>
            <p class="text-xs text-gray-600">Prezzo base + override per tipo (solo se spuntato).</p>
        </div>
        <button type="button"
                wire:click.prevent="salvaTutti"
                class="px-3 py-1.5 rounded border border-red-700 bg-red-700 text-white text-xs hover:bg-red-800">
            Salva tutto
        </button>
    </div>

    @if(!$hasPrezzoColumn)
        <div class="rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800">
            Colonna <code>anomalie.prezzo</code> non trovata.
        </div>
    @endif

    @if(!$hasFlagTipoEstintoreColumn || !$hasFlagTipoPresidioColumn || !$hasPrezziTipoEstintoreTable || !$hasPrezziTipoPresidioTable)
        <div class="rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800">
            Struttura prezzi per tipo non completa: esegui migration.
        </div>
    @endif

    @forelse($anomalieByCategoria as $categoria => $anomalieCategoria)
        <div class="rounded border border-gray-200 bg-white">
            <div class="px-3 py-2 border-b bg-gray-50 text-sm font-semibold text-gray-700">
                {{ $categoria ?: 'Senza categoria' }}
            </div>

            <div class="divide-y divide-gray-100">
                @foreach($anomalieCategoria as $anomalia)
                    @php
                        $anomaliaId = (int) $anomalia->id;
                        $cat = mb_strtolower(trim((string) $anomalia->categoria));
                        $isEstintore = str_contains($cat, 'estint');
                        $isPorta = str_contains($cat, 'port');
                        $tipi = $isEstintore ? $tipiEstintori : ($isPorta ? $tipiPorte : $tipiIdranti);
                        $useTipo = (bool)($usaPrezziTipo[$anomaliaId] ?? false);
                        $attiviMap = $isEstintore
                            ? ($prezziTipoAttiviEstintore[$anomaliaId] ?? [])
                            : ($prezziTipoAttiviPresidio[$anomaliaId] ?? []);
                    @endphp

                    <div class="p-3" wire:key="anomalia-compact-{{ $anomaliaId }}">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-center">
                            <div class="md:col-span-4">
                                <div class="text-sm font-medium text-gray-900">{{ $anomalia->etichetta }}</div>
                            </div>

                            <div class="md:col-span-2">
                                <input type="text"
                                       wire:model.live.debounce.250ms="prezzi.{{ $anomaliaId }}"
                                       wire:blur="salvaRiga({{ $anomaliaId }})"
                                       wire:keydown.enter.prevent="salvaRiga({{ $anomaliaId }})"
                                       class="w-full border rounded px-2 py-1 text-right text-sm {{ !empty($invalidPrezzi[$anomaliaId] ?? null) ? 'border-red-500 bg-red-50' : 'border-gray-300' }}"
                                       placeholder="Prezzo base">
                            </div>

                            <div class="md:col-span-2">
                                <label class="inline-flex items-center gap-2 text-xs">
                                    <input type="checkbox"
                                           wire:model="attive.{{ $anomaliaId }}"
                                           class="h-4 w-4 border-gray-300 rounded">
                                    <span>Attiva</span>
                                </label>
                            </div>

                            <div class="md:col-span-2">
                                <label class="inline-flex items-center gap-2 text-xs">
                                    <input type="checkbox"
                                           wire:model="usaPrezziTipo.{{ $anomaliaId }}"
                                           class="h-4 w-4 border-gray-300 rounded">
                                    <span>Prezzi per tipo</span>
                                </label>
                            </div>

                            <div class="md:col-span-2 md:text-right">
                                <button type="button"
                                        wire:click.prevent="salvaRiga({{ $anomaliaId }})"
                                        class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50 text-xs">
                                    Salva
                                </button>
                            </div>
                        </div>

                        @if($useTipo)
                            <div class="mt-2 rounded border border-gray-200 bg-gray-50 p-2">
                                <div class="text-[11px] text-gray-600 mb-2">
                                    Spunta i tipi con prezzo diverso dal base.
                                </div>

                                <div class="space-y-1">
                                    @foreach($tipi as $tipo)
                                        @php
                                            $tipoId = (int) data_get($tipo, 'id');
                                            $tipoLabel = (string) data_get($tipo, 'label');
                                            $checked = (bool)($attiviMap[$tipoId] ?? $attiviMap[(string)$tipoId] ?? false);
                                            $invalidKey = $anomaliaId . ':' . $tipoId;
                                        @endphp
                                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center border border-gray-200 rounded bg-white px-2 py-1">
                                            <div class="sm:col-span-7">
                                                <label class="inline-flex items-center gap-2 text-xs">
                                                    @if($isEstintore)
                                                        <input type="checkbox"
                                                               wire:model="prezziTipoAttiviEstintore.{{ $anomaliaId }}.{{ $tipoId }}"
                                                               class="h-4 w-4 border-gray-300 rounded">
                                                    @else
                                                        <input type="checkbox"
                                                               wire:model="prezziTipoAttiviPresidio.{{ $anomaliaId }}.{{ $tipoId }}"
                                                               class="h-4 w-4 border-gray-300 rounded">
                                                    @endif
                                                    <span>{{ $tipoLabel }}</span>
                                                </label>
                                            </div>
                                            <div class="sm:col-span-5">
                                                @if($isEstintore)
                                                    <input type="text"
                                                           wire:model.lazy="prezziTipoEstintore.{{ $anomaliaId }}.{{ $tipoId }}"
                                                           wire:blur="salvaRiga({{ $anomaliaId }})"
                                                           @disabled(!$checked)
                                                           class="w-full border rounded px-2 py-1 text-right text-xs {{ !empty($invalidPrezziTipoEstintore[$invalidKey] ?? null) ? 'border-red-500 bg-red-50' : 'border-gray-300' }}"
                                                           placeholder="Prezzo tipo">
                                                @else
                                                    <input type="text"
                                                           wire:model.lazy="prezziTipoPresidio.{{ $anomaliaId }}.{{ $tipoId }}"
                                                           wire:blur="salvaRiga({{ $anomaliaId }})"
                                                           @disabled(!$checked)
                                                           class="w-full border rounded px-2 py-1 text-right text-xs {{ !empty($invalidPrezziTipoPresidio[$invalidKey] ?? null) ? 'border-red-500 bg-red-50' : 'border-gray-300' }}"
                                                           placeholder="Prezzo tipo">
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded border border-gray-200 bg-white p-4 text-sm text-gray-500">
            Nessuna anomalia configurata.
        </div>
    @endforelse
</div>
