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
                                Pianificato:
                                <span class="font-medium">
                                    {{ $int->pivot->scheduled_start_at ? $int->pivot->scheduled_start_at->format('H:i') : '—' }}
                                </span>
                                -
                                <span class="font-medium">
                                    {{ $int->pivot->scheduled_end_at ? $int->pivot->scheduled_end_at->format('H:i') : '—' }}
                                </span>
                            </div>
                            <div class="mt-2">
                                <label class="text-xs text-gray-600">Note intervento</label>
                                <textarea wire:model.debounce.500ms="noteByIntervento.{{ $int->id }}" rows="2" class="w-full text-xs border-gray-300 rounded px-2 py-1" placeholder="Note per i tecnici"></textarea>
                            </div>
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
