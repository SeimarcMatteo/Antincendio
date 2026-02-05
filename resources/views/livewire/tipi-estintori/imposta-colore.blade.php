<div class="p-6">
    <h2 class="text-xl font-semibold text-red-600 mb-4">
        Imposta colori per tipologia di estintore
    </h2>

    <div class="bg-white shadow rounded border p-4">
        {{-- Intestazioni --}}
        <div class="grid grid-cols-2 gap-4 border-b pb-2 mb-2 text-sm font-semibold text-gray-600">
            <div>Tipologia estintore</div>
            <div>Colore</div>
        </div>

        {{-- Righe tipologie --}}
        @forelse ($tipi as $tipo)
            

            <div class="grid grid-cols-2 gap-4 items-center py-2 border-b last:border-b-0" wire:key="tipo-{{ $tipo->id }}">
                {{-- Colonna sinistra: nome tipo + pallina colore attuale --}}
                <div class="text-sm text-gray-800 flex items-center space-x-2">
                    @if ($tipo->colore)
                        <span class="w-3 h-3 rounded-full border"
                              style="background-color: {{ $tipo->colore->hex }}"></span>
                    @else
                        <span class="w-3 h-3 rounded-full border border-gray-300 bg-white"></span>
                    @endif
                    <span>{{ $tipo->descrizione }}</span>
                </div>

                {{-- Colonna destra: select colore --}}
                <div class="flex items-center gap-3">
                    <select
                        class="input input-bordered text-sm w-full"
                        wire:model="coloreSelezionato.{{ $tipo->id }}">
                        <option value="">Nessun colore</option>
                        @foreach ($colori as $colore)
                            <option value="{{ $colore->id }}" @selected($tipo->colore_id == $colore->id)>
                                {{ $colore->nome }}
                            </option>
                        @endforeach
                    </select>
                    @if ($tipo->colore)
                        <span class="w-5 h-5 rounded-full border"
                              title="{{ $tipo->colore->nome }}"
                              style="background-color: {{ $tipo->colore->hex }}"></span>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">
                Nessuna tipologia di estintore configurata.
            </p>
        @endforelse

    </div>
</div>
