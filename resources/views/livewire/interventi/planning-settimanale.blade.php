<div class="space-y-4">
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-4 flex items-center justify-between">
        <button wire:click="settimanaPrecedente" class="btn btn-sm btn-outline">
            ⬅️ Settimana precedente
        </button>
        <div class="text-center">
            <h2 class="text-lg font-bold">Settimana dal {{ \Carbon\Carbon::parse($inizioSettimana)->format('d/m/Y') }}</h2>
            <div class="text-xs text-gray-500">Vista settimanale tecnici</div>
        </div>
        <button wire:click="settimanaSuccessiva" class="btn btn-sm btn-outline">
            Settimana successiva ➡️
        </button>
    </div>

    <div class="overflow-auto rounded-lg shadow border border-gray-200 max-h-[75vh]">
        <table class="min-w-full text-sm text-center" wire:key="{{ $this->keySettimana }}">
            <thead class="bg-gray-100 sticky top-0 z-10">
                <tr>
                    <th class="py-2 px-3 bg-white text-left sticky left-0 z-10">Tecnico</th>
                    @foreach($giorni as $giorno)
                        <th class="py-2 px-3 {{ $giorno['festivo'] ? 'bg-yellow-100 text-red-700 font-bold' : '' }}">
                            <span class="block font-bold">{{ $giorno['data']->translatedFormat('D') }}</span>
                            <span class="block">{{ $giorno['data']->format('d/m') }}</span>
                            @if($giorno['festivo']) 🎉 @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($tecnici as $tec)
                    @php $totSett = $tec->interventi->sum('durata_minuti'); @endphp
                    <tr class="border-t">
                        <td class="py-2 px-3 text-left font-medium bg-gray-50 sticky left-0 z-0">
                            <div class="font-semibold">{{ $tec->name }}</div>
                            <div class="text-xs text-gray-500">{{ round($totSett/60, 1) }}h sett.</div>
                        </td>
                        @foreach($giorni as $giorno)
                            @php
                                $interventiGiorno = $tec->interventi->where('data_intervento', $giorno['data']->toDateString());
                                $minuti = $interventiGiorno->sum('durata_minuti');
                                $pct = min(100, ($minuti / 480) * 100);
                            @endphp
                            <td class="py-2 px-2 align-top {{ $giorno['festivo'] ? 'bg-yellow-100' : ($minuti > 480 ? 'bg-red-50' : 'bg-white') }}">
                                <div class="h-1 rounded bg-gray-200 mb-2">
                                    <div class="h-1 rounded {{ $minuti > 480 ? 'bg-red-500' : 'bg-green-500' }}" style="width: {{ $pct }}%"></div>
                                </div>
                                @foreach($interventiGiorno as $int)
                                    <div class="mb-2 text-left p-2 rounded border bg-white shadow-sm">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex-1">
                                                <a href="{{ route('interventi.evadi.dettaglio', ['intervento' => $int->id]) }}"
                                                   class="block text-left text-blue-700 hover:underline font-semibold">
                                                    {{ $int->cliente->nome }}
                                                </a>
                                                @if($int->sede)
                                                    <div class="text-xs text-gray-500">{{ $int->sede->nome }}</div>
                                                @endif
                                                <div class="text-xs text-gray-600 mt-1">⏱ {{ round($int->durata_minuti / 60, 1) }}h</div>
                                            </div>
                                            <button wire:click="annullaIntervento({{ $int->id }})"
                                                class="text-red-600 hover:text-red-800 text-sm font-bold px-1"
                                                title="Annulla intervento">✖️</button>
                                        </div>
                                    </div>
                                @endforeach
                                <div class="mt-2 font-semibold text-xs text-gray-700">
                                    Totale: {{ round($minuti/60, 1) }}h
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <livewire:report.elenco-chiamate-estintori />

</div>
