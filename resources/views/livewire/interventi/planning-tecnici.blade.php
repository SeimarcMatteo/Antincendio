<div>
    <input type="date" wire:model="dataSelezionata" class="rounded shadow p-2 mb-4 border">

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach($tecnici as $tec)
            <div class="border rounded-lg shadow-lg p-4 bg-white">
                <h3 class="text-lg font-bold mb-2">
                    {{ $tec->name }}
                </h3>

                @if($tec->interventi->count())
                    @foreach($tec->interventi as $int)
                        <div class="mb-2 text-sm">
                            <span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-1"></span>
                            <strong>{{ $int->cliente->nome }}</strong>
                            {{ optional($int->sede)->nome }}
                            ({{ $this->formatMinutes($int->durata_minuti) }})
                            <div class="mt-1 text-xs text-gray-600">
                                Inizio: <span class="font-medium">{{ $int->pivot->started_at ? $int->pivot->started_at->format('H:i') : '—' }}</span>
                                · Fine: <span class="font-medium">{{ $int->pivot->ended_at ? $int->pivot->ended_at->format('H:i') : '—' }}</span>
                            </div>
                            @if(auth()->id() === $tec->id)
                                <div class="mt-1 flex items-center gap-2">
                                    <button
                                        class="px-2 py-1 text-xs rounded border {{ $int->pivot->started_at ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white hover:bg-gray-50' }}"
                                        wire:click="avviaIntervento({{ $int->id }}, {{ $tec->id }})"
                                        @disabled($int->pivot->started_at)>
                                        ▶️ Inizia
                                    </button>
                                    <button
                                        class="px-2 py-1 text-xs rounded border {{ ($int->pivot->started_at && !$int->pivot->ended_at) ? 'bg-white hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}"
                                        wire:click="terminaIntervento({{ $int->id }}, {{ $tec->id }})"
                                        @disabled(!$int->pivot->started_at || $int->pivot->ended_at)>
                                        ⏹ Fine
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                @else
                    <div class="text-sm text-gray-500">Nessun intervento.</div>
                @endif

                <div class="mt-3 pt-2 border-t text-sm font-bold">
                    Totale: {{ $this->formatMinutes($tec->totale_minuti) }} / 8 ore
                </div>
            </div>
        @endforeach
    </div>
</div>
