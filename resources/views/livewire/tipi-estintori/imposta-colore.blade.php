<div class="p-6 max-w-4xl mx-auto space-y-6">
    {{-- Titolo + sottotitolo --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-semibold text-red-600 tracking-tight">
                Imposta colori per tipologia di estintore
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                Associa un colore a ogni tipologia per riconoscerla a colpo d’occhio nelle schermate.
            </p>
        </div>
    </div>

    {{-- Messaggio di conferma --}}
    @if (session()->has('message'))
        <div class="flex items-center gap-3 bg-green-50 text-green-800 px-4 py-3 rounded-lg border border-green-100 text-sm">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-green-100">
                ✅
            </span>
            <span>{{ session('message') }}</span>
        </div>
    @endif

    {{-- Card principale --}}
    <div class="bg-white/80 backdrop-blur shadow-lg rounded-xl border border-gray-100">
        {{-- Header tabella --}}
        <div class="grid grid-cols-1 md:grid-cols-[minmax(0,2fr)_minmax(0,1.4fr)] gap-4 px-5 pt-4 pb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
            <div>Tipologia estintore</div>
            <div class="md:text-right">Colore associato</div>
        </div>

        {{-- Lista tipologie --}}
        @forelse ($tipi as $tipo)
            <div class="px-5 py-3 border-b border-gray-100 last:border-b-0 flex flex-col md:flex-row md:items-center md:justify-between gap-3 group">
                {{-- Colonna sinistra --}}
                <div class="flex items-center gap-3">
                    @php
                        $hex = $tipo->colore?->hex;
                    @endphp

                    <div
                        class="w-7 h-7 rounded-full border shadow-sm flex items-center justify-center ring-2 ring-offset-2 transition
                               {{ $hex ? 'ring-red-100 border-transparent' : 'ring-gray-100 border-gray-200 bg-white' }}"
                        style="{{ $hex ? "background: radial-gradient(circle at 30% 30%, #ffffffaa, $hex);" : '' }}"
                    >
                        @unless($hex)
                            <span class="w-2 h-2 rounded-full bg-gray-200"></span>
                        @endunless
                    </div>

                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-gray-900">
                            {{ $tipo->descrizione }}
                        </span>

                        @if ($hex)
                            <span class="text-xs text-gray-400">
                                {{ $hex }}
                            </span>
                        @else
                            <span class="inline-flex items-center text-[11px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 mt-0.5">
                                Nessun colore impostato
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Colonna destra: select colore --}}
                <div class="w-full md:w-60">
                    <select
                        class="w-full text-sm border-gray-200 rounded-lg shadow-sm bg-gray-50 hover:bg-white
                               focus:outline-none focus:ring-2 focus:ring-red-200 focus:border-red-500
                               transition-colors"
                        wire:change="salva({{ $tipo->id }}, $event.target.value)"
                        style="color: {{ $hex ?: 'inherit' }};"
                    >
                        <option value="">— Nessun colore —</option>

                        @foreach ($colori as $colore)
                            <option
                                value="{{ $colore->id }}"
                                style="color: {{ $colore->hex }};"
                                @selected($tipo->colore && $tipo->colore->id === $colore->id)
                            >
                                &#9679; {{ $colore->nome }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Piccolo hint di caricamento al cambio --}}
                    <div class="mt-1 text-[11px] text-gray-400" wire:loading.delay wire:target="salva">
                        Salvataggio in corso…
                    </div>
                </div>
            </div>
        @empty
            <p class="px-5 py-4 text-sm text-gray-500">
                Nessuna tipologia di estintore configurata.
            </p>
        @endforelse
    </div>
</div>
