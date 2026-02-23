<div class="space-y-4">
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-lg font-bold text-gray-800">Planning Interventi</div>
            <div class="inline-flex rounded border border-gray-300 overflow-hidden">
                <button wire:click="setVista('settimanale')"
                        class="px-4 py-2 text-sm font-semibold {{ $vista === 'settimanale' ? 'bg-red-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    Vista settimanale
                </button>
                <button wire:click="setVista('mensile')"
                        class="px-4 py-2 text-sm font-semibold {{ $vista === 'mensile' ? 'bg-red-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                    Vista mensile
                </button>
            </div>
        </div>

        @if($vista === 'settimanale')
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button wire:click="settimanaPrecedente" class="btn btn-sm btn-outline min-h-10">
                    ⬅️ Settimana precedente
                </button>
                <div class="text-center">
                    <h2 class="text-lg font-bold">Settimana dal {{ \Carbon\Carbon::parse($inizioSettimana)->format('d/m/Y') }}</h2>
                    <div class="text-xs text-gray-500">Sposta e duplica assegnazioni per tecnico direttamente dalle card</div>
                </div>
                <button wire:click="settimanaSuccessiva" class="btn btn-sm btn-outline min-h-10">
                    Settimana successiva ➡️
                </button>
            </div>

            <div class="rounded border border-indigo-200 bg-indigo-50 p-3">
                <div class="text-sm font-semibold text-indigo-900 mb-2">
                    Spostamento massivo per zona (giorno selezionato: {{ \Carbon\Carbon::parse($bulkDataRef)->format('d/m/Y') }})
                </div>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                    <input type="date"
                           wire:model.live="bulkData"
                           class="w-full border border-indigo-200 rounded px-2 py-2 text-sm bg-white">

                    <select wire:model="bulkZona" class="w-full border border-indigo-200 rounded px-2 py-2 text-sm bg-white">
                        <option value="">Seleziona zona</option>
                        @foreach($zoneGiorno as $zona)
                            <option value="{{ $zona }}">{{ $zona }}</option>
                        @endforeach
                    </select>

                    <select wire:model="bulkTecnicoDa" class="w-full border border-indigo-200 rounded px-2 py-2 text-sm bg-white">
                        <option value="">Tecnico origine</option>
                        @foreach($tecniciDisponibili as $tecOpt)
                            <option value="{{ $tecOpt->id }}">{{ $tecOpt->name }}</option>
                        @endforeach
                    </select>

                    <select wire:model="bulkTecnicoA" class="w-full border border-indigo-200 rounded px-2 py-2 text-sm bg-white">
                        <option value="">Tecnico destinazione</option>
                        @foreach($tecniciDisponibili as $tecOpt)
                            <option value="{{ $tecOpt->id }}">{{ $tecOpt->name }}</option>
                        @endforeach
                    </select>

                    <button wire:click="spostaZonaGiorno"
                            class="w-full rounded bg-indigo-600 text-white font-semibold py-2 px-3 hover:bg-indigo-700">
                        Sposta zona del giorno
                    </button>
                </div>
                <div class="mt-2 text-[11px] text-indigo-800">
                    Sposta tutti gli interventi Pianificati della zona nel giorno selezionato dal tecnico origine al tecnico destinazione.
                    Gli interventi già assegnati al tecnico destinazione vengono saltati.
                </div>
            </div>
        @else
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button wire:click="mesePrecedente" class="btn btn-sm btn-outline min-h-10">
                    ⬅️ Mese precedente
                </button>
                <div class="text-center">
                    <h2 class="text-lg font-bold capitalize">{{ $meseLabel }}</h2>
                    <div class="text-xs text-gray-500">Giorni pianificati con zone e tecnici assegnati</div>
                </div>
                <button wire:click="meseSuccessivo" class="btn btn-sm btn-outline min-h-10">
                    Mese successivo ➡️
                </button>
            </div>
        @endif
    </div>

    @if($vista === 'settimanale')
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
                                <div class="text-xs text-gray-500">{{ $this->formatMinutes($totSett) }} sett.</div>
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
                                        @php
                                            $scheduledStart = $int->pivot?->scheduled_start_at ? \Carbon\Carbon::parse($int->pivot->scheduled_start_at) : null;
                                            $scheduledEnd = $int->pivot?->scheduled_end_at ? \Carbon\Carbon::parse($int->pivot->scheduled_end_at) : null;
                                            $azioneKey = $int->id . ':' . $tec->id;
                                        @endphp
                                        <div class="mb-2 text-left p-2 rounded border bg-white shadow-sm space-y-2">
                                            <a href="{{ route('interventi.evadi.dettaglio', ['intervento' => $int->id]) }}"
                                               class="block w-full rounded border border-blue-200 bg-blue-50 text-blue-800 hover:bg-blue-100 px-3 py-3 text-sm font-semibold leading-tight">
                                                {{ $int->cliente->nome }}
                                            </a>

                                            @if($int->sede)
                                                <div class="text-xs text-gray-600">Sede: <span class="font-semibold">{{ $int->sede->nome }}</span></div>
                                            @endif
                                            <div class="text-xs text-gray-600">Zona: <span class="font-semibold">{{ $int->zona ?: '—' }}</span></div>
                                            @if(!empty($int->note))
                                                <div class="text-xs rounded border border-amber-200 bg-amber-50 p-2 text-amber-900 whitespace-pre-wrap">
                                                    <span class="font-semibold">Note:</span> {{ $int->note }}
                                                </div>
                                            @endif
                                            <div class="text-xs text-indigo-700">
                                                🕒 {{ $scheduledStart ? $scheduledStart->format('H:i') : '—' }} - {{ $scheduledEnd ? $scheduledEnd->format('H:i') : '—' }}
                                            </div>
                                            <div class="text-xs text-gray-700">⏱ {{ (int)$int->durata_minuti }} min</div>

                                            <div>
                                                <label class="text-[11px] text-gray-500">Orario tecnico</label>
                                                <input type="time"
                                                       value="{{ $scheduledStart ? $scheduledStart->format('H:i') : '' }}"
                                                       wire:change="aggiornaOrarioTecnico({{ $int->id }}, {{ $tec->id }}, $event.target.value)"
                                                       class="mt-1 w-full border border-gray-300 rounded px-2 py-2 text-sm">
                                            </div>

                                            @if($int->stato === 'Pianificato')
                                                <div class="grid grid-cols-1 gap-1">
                                                    <select wire:model="azioniTecnico.{{ $azioneKey }}"
                                                            class="w-full border border-gray-300 rounded px-2 py-2 text-xs">
                                                        <option value="">Seleziona tecnico</option>
                                                        @foreach($tecniciDisponibili as $tecOpt)
                                                            @if((int)$tecOpt->id !== (int)$tec->id)
                                                                <option value="{{ $tecOpt->id }}">{{ $tecOpt->name }}</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                    <div class="grid grid-cols-2 gap-1">
                                                        <button wire:click="spostaInterventoTecnico({{ $int->id }}, {{ $tec->id }})"
                                                                class="w-full rounded border border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 py-2 text-xs font-semibold">
                                                            Sposta tecnico
                                                        </button>
                                                        <button wire:click="aggiungiTecnicoIntervento({{ $int->id }}, {{ $tec->id }})"
                                                                class="w-full rounded border border-green-300 bg-green-50 text-green-700 hover:bg-green-100 py-2 text-xs font-semibold">
                                                            Aggiungi tecnico
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif

                                            <button wire:click="annullaIntervento({{ $int->id }})"
                                                    class="w-full rounded border border-red-300 bg-red-50 text-red-700 hover:bg-red-100 py-2 text-xs font-semibold"
                                                    title="Annulla intervento">
                                                ✖️ Annulla intervento
                                            </button>
                                        </div>
                                    @endforeach
                                    <div class="mt-2 font-semibold text-xs text-gray-700">
                                        Totale: {{ $this->formatMinutes($minuti) }}
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="overflow-auto rounded-lg shadow border border-gray-200">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>
                        @foreach(['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'] as $dayName)
                            <th class="py-2 px-2 text-center font-semibold text-gray-700">{{ $dayName }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($calendarioMensile as $week)
                        <tr class="border-t">
                            @foreach($week as $cell)
                                <td class="align-top p-2 border-r border-gray-100 w-[14.28%] {{ $cell['in_month'] ? 'bg-white' : 'bg-gray-50' }}">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm font-bold {{ $cell['in_month'] ? 'text-gray-800' : 'text-gray-400' }}">
                                            {{ $cell['data']->format('d') }}
                                        </div>
                                        @if($cell['rows'])
                                            <span class="text-[10px] rounded px-1 py-0.5 bg-blue-100 text-blue-700">
                                                {{ count($cell['rows']) }} zone
                                            </span>
                                        @endif
                                    </div>

                                    @forelse($cell['rows'] as $row)
                                        <div class="mt-1 rounded border border-gray-200 bg-gray-50 p-2">
                                            <div class="text-xs font-semibold text-gray-800">{{ $row['zona'] }}</div>
                                            <div class="text-[11px] text-gray-600 mt-0.5">
                                                Tecnici: <span class="font-medium">{{ implode(', ', $row['tecnici']) ?: '—' }}</span>
                                            </div>
                                            <div class="text-[11px] text-gray-600 mt-0.5">
                                                Interventi: {{ (int)($row['tot_interventi'] ?? 0) }} · Pianificati: {{ (int)($row['pianificati'] ?? 0) }} · Completati: {{ (int)($row['completati'] ?? 0) }}
                                            </div>
                                        </div>
                                    @empty
                                        <div class="mt-1 text-[11px] text-gray-400">Nessuna pianificazione</div>
                                    @endforelse
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <livewire:report.elenco-chiamate-estintori />
</div>
