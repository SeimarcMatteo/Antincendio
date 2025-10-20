<div class="space-y-6">
    <div class="flex items-center gap-4">
        <label>Mese:
            <select wire:model="meseSelezionato" class="border rounded p-1">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">
                        {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                    </option>
                @endfor
            </select>
        </label>
        <label>Anno:
            <select wire:model="annoSelezionato" class="border rounded p-1">
                @for ($a = now()->year; $a <= now()->year + 1; $a++)
                    <option value="{{ $a }}">{{ $a }}</option>
                @endfor
            </select>
        </label>
    </div>

    @forelse ($clienti as $cliente)
        <div class="border rounded shadow p-4 bg-white">
            <h2 class="text-lg font-bold">{{ $cliente->nome }}</h2>
            <p class="text-sm text-gray-600">Presidi totali: {{ $cliente->presidi->count() }}</p>

            @if ($cliente->sedi->count())
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($cliente->sedi as $sede)
                        @if (! $this->interventoEsistente($cliente->id, $sede->id))
                            <div class="p-2 border rounded">
                                <strong>{{ $sede->nome }}</strong><br>
                                {{ $sede->indirizzo }} - {{ $sede->citta }}
                                <div class="mt-1">
                                    <button wire:click="pianifica({{ $cliente->id }}, {{ $sede->id }})"
                                        class="btn btn-sm btn-primary">
                                        📅 Pianifica intervento
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                @if (! $this->interventoEsistente($cliente->id, null))
                    <div class="mt-2">
                        <em>Nessuna sede registrata.</em>
                        <button wire:click="pianifica({{ $cliente->id }}, null)" class="btn btn-sm btn-primary ml-2">
                            📅 Pianifica intervento (Sede principale)
                        </button>
                    </div>
                @endif
            @endif
        </div>
    @empty
        <div class="text-gray-500 italic">Nessun cliente da pianificare per il mese selezionato.</div>
    @endforelse
</div>
