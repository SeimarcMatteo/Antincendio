<div class="p-6">
    <div class="bg-white shadow rounded-lg p-6 space-y-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-red-600">Dettagli Cliente</h2>
            <a href="{{ route('clienti.index') }}" class="text-sm text-gray-600 hover:text-red-600">
                <i class="fa fa-arrow-left mr-1"></i> Torna alla lista
            </a>
        </div>

        @if (session()->has('message'))
            <div class="bg-green-100 text-green-800 px-4 py-2 rounded">
                {{ session('message') }}
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-sm">
    {{-- SX riga 1 --}}
    <div>
        <span class="font-semibold text-gray-700">Codice esterno:</span>
        <div class="text-gray-900">{{ $cliente->codice_esterno }}</div>
    </div>
    {{-- DX riga 1 --}}
    <div>
        <span class="font-semibold text-gray-700">Partita IVA:</span>
        <div class="text-gray-900">{{ $cliente->p_iva ?? '—' }}</div>
    </div>

    {{-- full riga 2 --}}
    <div class="sm:col-span-2">
        <span class="font-semibold text-gray-700">Ragione sociale:</span>
        <div class="text-gray-900">{{ $cliente->nome }}</div>
    </div>

    {{-- SX riga 3 --}}
    <div>
        <span class="font-semibold text-gray-700">Telefono:</span>
        <div>
            @if ($cliente->telefono)
                <a href="tel:{{ preg_replace('/\s+/', '', $cliente->telefono) }}" class="text-red-600 hover:underline">
                    <i class="fa fa-phone-alt mr-1"></i>{{ $cliente->telefono }}
                </a>
            @else
                <span class="text-gray-500">—</span>
            @endif
        </div>
    </div>

    {{-- DX riga 3: ZONA (tra P.IVA ed Email) --}}
    <div class="sm:col-start-2 mt-0">
    <label class="font-semibold text-gray-700">Zona</label>
    <div class="mt-0 flex items-center gap-2">   {{-- mt-0 invece di mt-1 --}}
        <input
            list="zone-list"
            wire:model.defer="zonaInput"
            class="input input-bordered w-56"
            placeholder="Seleziona o scrivi…">
        <datalist id="zone-list">
            @foreach($zoneSuggestions as $z)
                <option value="{{ $z }}"></option>
            @endforeach
        </datalist>
        <button wire:click="salvaZona" class="btn btn-primary btn-sm">💾 Salva</button>
    </div>
    <div class="text-xs text-gray-500 mt-0.5">
        Attuale: <span class="font-medium">{{ $cliente->zona ?? '—' }}</span>
    </div>
</div>

    {{-- DX riga 4: EMAIL --}}
    <div class="sm:col-start-2">
        <span class="font-semibold text-gray-700">Email:</span>
        <div>
            @if ($cliente->email)
                <a href="mailto:{{ $cliente->email }}" class="text-red-600 hover:underline">
                    <i class="fa fa-envelope mr-1"></i>{{ $cliente->email }}
                </a>
            @else
                <span class="text-gray-500">—</span>
            @endif
        </div>
    </div>

    <div class="sm:col-span-2">
    <button wire:click="vaiAiPresidi" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">
        <i class="fa fa-fire-extinguisher mr-1"></i> Gestisci presidi sede principale
    </button>
</div>


    {{-- NOTE: full width --}}
<div class="sm:col-span-2">
    <div class="flex items-center justify-between">
        <span class="font-semibold text-gray-700">Note</span>
        <button class="btn btn-xs btn-warning" wire:click="toggleNote">✏️</button>
    </div>

    @if($noteEdit)
        <textarea
            wire:model.defer="note"
            rows="6"
            class="textarea textarea-bordered w-full mt-2"
            placeholder="Inserisci qui eventuali note operative..."></textarea>

        <div class="mt-2 flex gap-2">
            <button wire:click="salvaNote" class="btn btn-primary btn-sm">💾 Salva note</button>
            <button wire:click="toggleNote" class="btn btn-ghost btn-sm">Annulla</button>
        </div>
    @else
        <div class="mt-2 p-3 rounded border border-gray-200 bg-gray-50 min-h-[3rem] w-full">
            {{ $cliente->note ?: '—' }}
        </div>
    @endif
</div>

        {{-- ===== sotto le NOTE ===== --}}
<div class="mt-6 space-y-6">

    {{-- RIGA 1: Mesi visita + Fatturazione (due card affiancate) --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- Card: Mesi visita --}}
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-red-600">
                    Mesi visita (sede principale):
                    <span class="font-normal text-gray-800">
                        {{ is_array($cliente->mesi_visita) ? implode(', ', $cliente->mesi_visita) : '—' }}
                    </span>
                </h3>
                <button wire:click="toggleMesiVisibili('cliente')" class="btn btn-sm btn-warning">✏️ Mesi</button>
            </div>

            @if($modificaMesiVisibile['cliente'] ?? false)
                <div class="grid grid-cols-6 gap-2 mt-4">
                    @for($i = 1; $i <= 12; $i++)
                        <label class="inline-flex items-center text-sm">
                            <input type="checkbox"
                                   wire:model.defer="modificaMesi.cliente.{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}"
                                   class="mr-1">
                            {{ Date::create()->month($i)->format('M') }}
                        </label>
                    @endfor
                </div>
                <button wire:click="salvaMesi" class="btn btn-xs btn-primary mt-3">💾 Salva mesi</button>
            @endif
        </div>

        {{-- Card: Fatturazione --}}
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-red-600 mb-3">Fatturazione</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo Fatturazione</label>
                    <select wire:model="fatturazione_tipo" class="input input-bordered w-full mt-1">
                        <option value="">—</option>
                        <option value="annuale">Annuale</option>
                        <option value="semestrale">Semestrale</option>
                    </select>
                </div>

                @if ($fatturazione_tipo === 'annuale')
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Mese Fatturazione</label>
                        <select wire:model="mese_fatturazione" class="input input-bordered w-full mt-1">
                            <option value="">—</option>
                            @for ($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}">{{ Date::create()->month($i)->format('F') }}</option>
                            @endfor
                        </select>
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <button wire:click="salvaFatturazione" class="btn btn-primary mt-2">
                        💾 Salva Fatturazione
                    </button>
                </div>
            </div>
        </div>

    </div>

    {{-- RIGA 2: Sedi associate (una card full-width) --}}
    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-red-600 mb-3">Sedi associate</h3>

        @if ($cliente->sedi->count())
            <ul class="divide-y divide-gray-200">
                @foreach ($cliente->sedi as $sede)
                    <li class="py-3">
                        <div class="flex justify-between items-start gap-4">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-800 truncate">{{ $sede->nome }}</div>
                                <div class="text-sm text-gray-600">
                                    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($sede->indirizzo . ', ' . $sede->cap . ' ' . $sede->citta . ' ' . $sede->provincia) }}"
                                       target="_blank" class="text-red-600 hover:underline">
                                        <i class="fa fa-map-marker-alt mr-1"></i>
                                        {{ $sede->indirizzo }} - {{ $sede->cap }} {{ $sede->citta }} ({{ $sede->provincia }})
                                    </a>
                                    <span class="ml-2 text-xs text-gray-500">
                                        {{ is_array($sede->mesi_visita ) ? implode(', ', $sede->mesi_visita ) : '—' }}
                                    </span>
                                </div>
                                @if ($sede->media_durata_effettiva)
                                    <div class="text-xs text-gray-500 italic">
                                        Media interventi: {{ round($sede->media_durata_effettiva) }} minuti
                                    </div>
                                @endif
                            </div>

                            <div class="mt-1 shrink-0 space-x-2">
                                <button wire:click="vaiAiPresidi({{ $sede->id }})"
                                        class="bg-red-600 text-white text-xs px-3 py-1 rounded hover:bg-red-700 transition">
                                    <i class="fa fa-fire mr-1"></i>Gestione Presidi
                                </button>
                                <button wire:click="toggleMesiVisibili({{ $sede->id }})" class="btn btn-xs btn-warning">✏️ Mesi</button>
                            </div>
                        </div>

                        @if($modificaMesiVisibile[$sede->id] ?? false)
                            <div class="grid grid-cols-6 gap-2 mt-3">
                                @for($i = 1; $i <= 12; $i++)
                                    <label class="inline-flex items-center text-sm">
                                        <input type="checkbox"
                                               wire:model.defer="modificaMesi.{{ $sede->id }}.{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}"
                                               class="mr-1">
                                        {{ Date::create()->month($i)->format('M') }}
                                    </label>
                                @endfor
                            </div>
                            <button wire:click="salvaMesi({{ $sede->id }})" class="btn btn-xs btn-primary mt-2">💾 Salva mesi</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="text-sm text-gray-500">Nessuna sede associata.</div>
        @endif
    </div>

</div>
