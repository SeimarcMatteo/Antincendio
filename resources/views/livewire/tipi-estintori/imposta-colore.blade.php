<div class="p-6">
    <h2 class="text-xl font-semibold text-red-600 mb-4">
        Gestione colori estintori
    </h2>

    @if (session()->has('message'))
        <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
            {{ session('message') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- SINISTRA: elenco tipologie --}}
        <div class="bg-white shadow rounded border lg:col-span-1">
            <h3 class="font-semibold text-gray-700 px-4 py-3 border-b">
                Tipologie estintori
            </h3>

            <div class="max-h-[500px] overflow-y-auto">
                @foreach ($tipi as $tipo)
                    @php
                        $isActive = $selectedTipoId === $tipo->id;
                        $coloreHex = $tipo->colore->hex ?? '#E5E7EB'; // grigino di default
                    @endphp

                    <button
                        type="button"
                        wire:click="selectTipo({{ $tipo->id }})"
                        class="w-full text-left px-4 py-2 flex items-center border-b last:border-b-0
                               hover:bg-red-50
                               {{ $isActive ? 'bg-red-50 border-l-4 border-l-red-500' : '' }}"
                    >
                        <span class="w-3 h-3 rounded-full mr-2 border"
                              style="background-color: {{ $coloreHex }}"></span>

                        <span class="text-sm text-gray-800">
                            {{ $tipo->descrizione }}
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- DESTRA: dettaglio tipo selezionato --}}
        <div class="bg-white shadow rounded border lg:col-span-2 p-4">
            @php
                $tipoSelezionato = $tipi->firstWhere('id', $selectedTipoId);
            @endphp

            @if ($tipoSelezionato)
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-800 text-lg">
                            {{ $tipoSelezionato->descrizione }}
                        </h3>
                        <p class="text-xs text-gray-500">
                            Imposta il colore da usare in tutto il gestionale.
                        </p>
                    </div>

                    @if ($tipoSelezionato->colore)
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-gray-500">Colore attuale:</span>
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs text-white"
                                  style="background-color: {{ $tipoSelezionato->colore->hex }}">
                                {{ $tipoSelezionato->colore->nome }}
                            </span>
                        </div>
                    @endif
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Seleziona colore
                    </label>

                    <select
                        wire:model="selectedColoreId"
                        class="border-gray-300 rounded w-full max-w-md"
                    >
                        <option value="">— Nessun colore —</option>

                        @foreach ($colori as $colore)
                            <option value="{{ $colore->id }}">
                                {{ $colore->nome }} ({{ $colore->hex }})
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($selectedColoreId)
                    @php
                        $coloreSel = $colori->firstWhere('id', (int) $selectedColoreId);
                    @endphp
                    @if ($coloreSel)
                        <div class="mt-4">
                            <span class="text-xs text-gray-500 block mb-1">Preview:</span>
                            <div class="flex items-center space-x-3">
                                <span class="w-8 h-8 rounded-full border"
                                      style="background-color: {{ $coloreSel->hex }}"></span>
                                <span class="text-sm text-gray-700">
                                    {{ $coloreSel->nome }} ({{ $coloreSel->hex }})
                                </span>
                            </div>
                        </div>
                    @endif
                @endif

            @else
                <p class="text-sm text-gray-500">
                    Nessuna tipologia selezionata.
                </p>
            @endif
        </div>

    </div>
</div>
