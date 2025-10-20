<div class="space-y-4">
    <div class="flex items-center justify-between">
        <button wire:click="settimanaPrecedente" class="btn btn-sm btn-outline">
            ⬅️ Settimana precedente
        </button>
        <h2 class="text-lg font-bold">
            Settimana dal {{ \Carbon\Carbon::parse($inizioSettimana)->format('d/m/Y') }}
        </h2>
        <button wire:click="settimanaSuccessiva" class="btn btn-sm btn-outline">
            Settimana successiva ➡️
        </button>
    </div>

    <div class="overflow-auto rounded shadow border border-gray-300">
        <table class="min-w-full text-sm text-center" wire:key="{{ $this->keySettimana }}">
            <thead class="bg-gray-100">
                <tr>
                    <th class="py-2 px-3 bg-white text-left">Tecnico</th>
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
                    <tr class="border-t">
                        <td class="py-2 px-3 text-left font-medium bg-gray-50">
                            {{ $tec->name }}
                        </td>
                        @foreach($giorni as $giorno)
                            @php
                                $interventiGiorno = $tec->interventi->where('data_intervento', $giorno['data']->toDateString());
                                $minuti = $interventiGiorno->sum('durata_minuti');
                            @endphp
                            <td class="py-2 px-2 align-top {{ $giorno['festivo'] ? 'bg-yellow-100' : ($minuti > 480 ? 'bg-red-100' : 'bg-white') }}">
                                @foreach($interventiGiorno as $int)
                                    <div class="mb-1 text-left flex items-start justify-between gap-2">
                                        <div class="flex-1">
                                            <a href="{{ route('interventi.evadi.dettaglio', ['intervento' => $int->id]) }}"
                                                class="block text-left text-blue-700 hover:underline font-medium">
                                                📍 {{ $int->cliente->nome }}
                                                @if($int->sede)
                                                    <span class="text-sm text-gray-600">({{ $int->sede->nome }})</span>
                                                @endif
                                                <br>
                                                <span class="text-sm">{{ round($int->durata_minuti / 60, 1) }}h</span>
                                            </a>
                                        </div>
                                        <button wire:click="annullaIntervento({{ $int->id }})"
                                            class="text-red-600 hover:text-red-800 text-lg font-bold px-1"
                                            title="Annulla intervento">✖️</button>
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
