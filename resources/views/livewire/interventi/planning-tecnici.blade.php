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
                            ({{ round($int->durata_minuti/60,1) }} ore)
                        </div>
                    @endforeach
                @else
                    <div class="text-sm text-gray-500">Nessun intervento.</div>
                @endif

                <div class="mt-3 pt-2 border-t text-sm font-bold">
                    Totale: {{ round($tec->totale_minuti/60,1) }} / 8 ore
                </div>
            </div>
        @endforeach
    </div>
</div>
