<div class="max-w-7xl mx-auto px-3 sm:px-4 py-4 sm:py-6 space-y-6">
    <h1 class="text-2xl font-bold text-red-700">
        🛠 Evadi Intervento:
        <a href="{{ route('clienti.mostra', $intervento->cliente_id) }}" class="text-gray-800 hover:text-red-800 underline">
            {{ $intervento->cliente->nome }}
        </a>
    </h1>

    <div class="bg-white border rounded p-3 shadow-sm text-sm text-gray-700">
        <div class="font-semibold text-gray-800 mb-1">Dati cliente</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
            <div>Indirizzo: <span class="font-medium">{{ $intervento->cliente->indirizzo ?? '—' }}</span></div>
            <div>Città: <span class="font-medium">{{ $intervento->cliente->citta ?? '—' }}</span></div>
            <div>Zona: <span class="font-medium">{{ $intervento->cliente->zona ?? '—' }}</span></div>
            <div>Telefono: <span class="font-medium">{{ $intervento->cliente->telefono ?? '—' }}</span></div>
            <div>Email: <span class="font-medium">{{ $intervento->cliente->email ?? '—' }}</span></div>
            <div>Note: <span class="font-medium">{{ $intervento->cliente->note ?? '—' }}</span></div>
        </div>
    </div>

    @php
        $currentTecnico = $intervento->tecnici->firstWhere('id', auth()->id());
        $pivot = $currentTecnico?->pivot;
        $start = $pivot?->started_at ? \Carbon\Carbon::parse($pivot->started_at) : null;
        $end = $pivot?->ended_at ? \Carbon\Carbon::parse($pivot->ended_at) : null;
    @endphp
    @if($currentTecnico)
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 text-sm bg-white border rounded p-3 shadow-sm">
            <div class="font-semibold text-gray-700">⏱ Timer intervento</div>
            <div class="text-xs text-gray-600">
                Inizio: <span class="font-medium">{{ $start ? $start->format('H:i') : '—' }}</span>
                · Fine: <span class="font-medium">{{ $end ? $end->format('H:i') : '—' }}</span>
            </div>
            <div class="sm:ml-auto flex items-center gap-2">
                <button
                    class="px-3 py-2 text-sm rounded border {{ $pivot?->started_at ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white hover:bg-gray-50' }}"
                    wire:click="avviaIntervento"
                    @disabled($pivot?->started_at)>
                    ▶️ Inizia
                </button>
                <button
                    class="px-3 py-2 text-sm rounded border {{ ($pivot?->started_at && !$pivot?->ended_at) ? 'bg-white hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}"
                    wire:click="terminaIntervento"
                    @disabled(!$pivot?->started_at || $pivot?->ended_at)>
                    ⏹ Fine
                </button>
            </div>
        </div>
    @endif

    <datalist id="marca-serbatoio-opzioni">
        @foreach($marcaSuggestions as $marca)
            <option value="{{ $marca }}"></option>
        @endforeach
    </datalist>

    {{-- Switch Vista --}}
    <div class="flex flex-wrap items-center gap-3">
        <label class="font-medium text-sm">Vista:</label>
        <button wire:click="$set('vistaSchede', false)"
            class="text-sm px-4 py-2 rounded border {{ $vistaSchede ? 'bg-white text-gray-700 border-gray-300' : 'bg-red-600 text-white border-red-600' }}">
            Tabella
        </button>
        <button wire:click="$set('vistaSchede', true)"
            class="text-sm px-4 py-2 rounded border {{ $vistaSchede ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-700 border-gray-300' }}">
            Schede
        </button>
    </div>
    <button wire:click="apriFormNuovoPresidio" class="text-sm px-4 py-2 bg-green-600 text-white rounded shadow">
        ➕ Aggiungi nuovo presidio
    </button>
    @if($formNuovoVisibile)
    <div class="border border-gray-300 bg-white rounded p-4 mb-6 shadow-sm space-y-4">
        <h2 class="text-lg font-semibold text-red-700">➕ Nuovo Presidio da aggiungere</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Ubicazione</label>
                <input type="text" wire:model.defer="nuovoPresidio.ubicazione" class="w-full border-gray-300 rounded px-2 py-1">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Categoria</label>
                <select wire:model="nuovoPresidio.categoria" class="w-full border-gray-300 rounded px-2 py-1">
                    <option value="Estintore">Estintore</option>
                    <option value="Idrante">Idrante</option>
                    <option value="Porta">Porta</option>
                </select>
            </div>

            @if(($nuovoPresidio['categoria'] ?? 'Estintore') === 'Estintore')
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo Estintore</label>
                    <select wire:model="nuovoPresidio.tipo_estintore_id" wire:change="aggiornaPreviewNuovo" class="w-full border-gray-300 rounded px-2 py-1">
                        <option value="">Seleziona tipo</option>
                        @foreach($tipiEstintori as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->sigla }} – {{ $tipo->descrizione }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Data Serbatoio</label>
                    <input type="date" wire:model="nuovoPresidio.data_serbatoio" wire:change="aggiornaPreviewNuovo" class="w-full border-gray-300 rounded px-2 py-1">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Marca Serbatoio</label>
                    <div class="flex items-center gap-2">
                        <input type="text" list="marca-serbatoio-opzioni" wire:model="nuovoPresidio.marca_serbatoio" wire:change="aggiornaPreviewNuovo" class="w-full border-gray-300 rounded px-2 py-1" placeholder="MB / altro">
                        <button type="button" wire:click="setMarcaMbNuovo" class="px-2 py-1 text-xs rounded border border-gray-300 hover:bg-gray-50">MB</button>
                    </div>
                    <label class="mt-1 inline-flex items-center gap-2 text-xs text-gray-600">
                        <input type="checkbox" wire:model="nuovoPresidio.marca_mb" class="border-gray-300">
                        Flag MB
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Ultima Revisione</label>
                    <input type="date" wire:model="nuovoPresidio.data_ultima_revisione" wire:change="aggiornaPreviewNuovo" class="w-full border-gray-300 rounded px-2 py-1">
                </div>
            @endif

            @if(($nuovoPresidio['categoria'] ?? '') === 'Idrante')
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo Idrante</label>
                    <select wire:model.defer="nuovoPresidio.idrante_tipo_id" class="w-full border-gray-300 rounded px-2 py-1">
                        <option value="">Seleziona tipo</option>
                        @foreach($tipiIdranti as $id => $tipo)
                            <option value="{{ $id }}">{{ $tipo }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if(($nuovoPresidio['categoria'] ?? '') === 'Porta')
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo Porta</label>
                    <select wire:model.defer="nuovoPresidio.porta_tipo_id" class="w-full border-gray-300 rounded px-2 py-1">
                        <option value="">Seleziona tipo</option>
                        @foreach($tipiPorte as $id => $tipo)
                            <option value="{{ $id }}">{{ $tipo }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Note</label>
                <textarea wire:model.defer="nuovoPresidio.note" rows="2" class="w-full border-gray-300 rounded px-2 py-1"></textarea>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm mt-1">
                    <input type="checkbox" wire:model.defer="nuovoPresidio.usa_ritiro" class="border-gray-300">
                    Presidio usato
                </label>
            </div>
    </div>
    @if(!empty($previewNuovo))
        @php $p = $previewNuovo; @endphp
        <div class="mt-2 text-xs bg-gray-50 border border-gray-200 rounded p-2 text-gray-700">
            <span class="font-semibold">Preview scadenze:</span>
            Revisione: <strong>{{ $p['revisione'] ? \Carbon\Carbon::parse($p['revisione'])->format('d/m/Y') : '—' }}</strong>,
            Collaudo: <strong>{{ $p['collaudo'] ? \Carbon\Carbon::parse($p['collaudo'])->format('d/m/Y') : '—' }}</strong>,
            Fine vita: <strong>{{ $p['fine_vita'] ? \Carbon\Carbon::parse($p['fine_vita'])->format('d/m/Y') : '—' }}</strong>,
            Sostituzione: <strong>{{ $p['sostituzione'] ? \Carbon\Carbon::parse($p['sostituzione'])->format('d/m/Y') : '—' }}</strong>
        </div>
    @endif
    <div class="flex justify-end mt-2">
        <button wire:click="salvaNuovoPresidio" class="px-4 py-2 bg-blue-600 text-white rounded shadow text-sm hover:bg-blue-700">
            💾 Salva nuovo presidio
        </button>
    </div>
    @endif
    {{-- VISTA TABELLARE --}}
    @if (!$vistaSchede)
        @php
            $catStyles = [
                'Estintore' => ['border' => 'border-red-500', 'bg' => 'bg-red-50', 'label' => 'ESTINTORI'],
                'Idrante'   => ['border' => 'border-blue-500', 'bg' => 'bg-blue-50', 'label' => 'IDRANTI'],
                'Porta'     => ['border' => 'border-amber-500', 'bg' => 'bg-amber-50', 'label' => 'PORTE'],
            ];
            $catIcons = [
                'Estintore' => 'fa-fire-extinguisher',
                'Idrante'   => 'fa-tint',
                'Porta'     => 'fa-door-open',
            ];
            $groups = $intervento->presidiIntervento->groupBy(function ($pi) {
                return $pi->presidio->categoria ?? 'Estintore';
            });
        @endphp
        <div class="space-y-6">
            @foreach ($groups as $cat => $items)
                @php
                    $style = $catStyles[$cat] ?? ['border' => 'border-gray-400', 'bg' => 'bg-gray-50', 'label' => strtoupper($cat)];
                @endphp
                <div class="border rounded shadow-sm bg-white">
                    <div class="flex items-center justify-between px-3 py-2 border-l-4 {{ $style['border'] }} {{ $style['bg'] }}">
                        <div class="font-semibold text-sm">{{ $style['label'] }}</div>
                        <div class="flex flex-col items-end gap-1">
                            <div class="text-xs text-gray-600">Totale: {{ $items->count() }}</div>
                            @if($cat === 'Idrante' && $showControlloAnnualeIdranti)
                                <span class="inline-flex items-center px-2 py-1 rounded text-[11px] font-bold bg-orange-500 text-white shadow-sm">
                                    CONTROLLO ANNUALE
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-gray-800">
                            <thead class="bg-gray-100 text-xs uppercase text-gray-600">
                                <tr>
                                    <th class="p-2">Progressivo</th>
                                    <th class="p-2">Ubicazione</th>
                                    <th class="p-2">Esito</th>
                                    <th class="p-2">Anomalie</th>
                                    <th class="p-2">Sostituzione</th>
                                    <th class="p-2">Note</th>
                                    <th class="p-2">Tipo</th>
                                    <th class="p-2">⚠️ Da Ritirare</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $pi)
                                    @php
                                        $d = $input[$pi->id];
                                        $hex = $pi->presidio->tipoEstintore?->colore?->hex ?? null;
                                        $rowRgba = null;
                                        if ($hex) {
                                            $h = ltrim($hex, '#');
                                            if (strlen($h) === 6) {
                                                $r = hexdec(substr($h, 0, 2));
                                                $g = hexdec(substr($h, 2, 2));
                                                $b = hexdec(substr($h, 4, 2));
                                                $rowRgba = "rgba($r, $g, $b, 0.06)";
                                            }
                                        }
                                        $rowMissing = empty($d['esito']);
                                        $icon = $catIcons[$cat] ?? 'fa-tag';
                                    @endphp
                                    <tr class="{{ $rowMissing ? 'bg-red-50' : '' }}"
                                        style="{{ (!$rowMissing && $rowRgba) ? 'background-color: '.$rowRgba.';' : '' }}">
                                        <td class="p-2 font-mono">
                                            <i class="fa {{ $icon }} text-gray-500 mr-1"></i>{{ $pi->presidio->progressivo }}
                                        </td>
                                        <td class="p-2"><input type="text" wire:model.lazy="input.{{ $pi->id }}.ubicazione" class="w-full border-gray-300 rounded px-2 py-1"></td>
                                        <td class="p-2">
                                            <select wire:model="input.{{ $pi->id }}.esito" wire:change="salvaEsito({{ $pi->id }})" class="w-full border-gray-300 rounded px-2 py-1">
                                                <option value="verificato">✅ Verificato</option>
                                                <option value="non_verificato">❌ Non Verificato</option>
                                                <option value="anomalie">⚠️ Anomalie</option>
                                                <option value="sostituito">🔁 Sostituito</option>
                                            </select>
                                            @if($pi->usa_ritiro)
                                                <div class="text-xs mt-1 text-blue-500 font-semibold">Presidio usato</div>
                                            @endif
                                        </td>
                                        <td class="p-2">
                                            @php
                                                $anomList = ($anomalie[$pi->presidio->categoria] ?? collect());
                                                $selectedAnom = collect($input[$pi->id]['anomalie'] ?? [])->map(fn($v)=>(int)$v)->values()->all();
                                                $anomMap = $anomList->pluck('etichetta', 'id')->toArray();
                                            @endphp
                                            <div class="space-y-2">
                                                <div class="space-y-1 max-h-36 overflow-auto border border-gray-200 rounded p-2 bg-white">
                                                    @forelse($anomList as $anomalia)
                                                        @php $sel = in_array((int) $anomalia->id, $selectedAnom, true); @endphp
                                                        <label class="flex items-center gap-2 text-xs py-1">
                                                            <input type="checkbox"
                                                                   @checked($sel)
                                                                   wire:change="toggleAnomalia({{ $pi->id }}, {{ $anomalia->id }}, $event.target.checked)"
                                                                   class="h-5 w-5 border-gray-300">
                                                            <span>{{ $anomalia->etichetta }}</span>
                                                        </label>
                                                    @empty
                                                        <div class="text-xs text-gray-500">Nessuna anomalia configurata per questa categoria.</div>
                                                    @endforelse
                                                </div>
                                                @if(!empty($selectedAnom))
                                                    <div class="border border-blue-200 rounded bg-blue-50 p-2">
                                                        <div class="text-[11px] font-semibold text-blue-800 mb-1">Anomalie selezionate: stato riparazione</div>
                                                        <div class="space-y-1">
                                                            @foreach($selectedAnom as $anomId)
                                                                @php
                                                                    $rip = (bool)($input[$pi->id]['anomalie_riparate'][$anomId] ?? false);
                                                                    $label = $anomMap[$anomId] ?? ('Anomalia #'.$anomId);
                                                                @endphp
                                                                <div class="flex items-center justify-between gap-2 text-xs">
                                                                    <span class="truncate">{{ $label }}</span>
                                                                    <label class="inline-flex items-center gap-2 shrink-0">
                                                                        <input type="checkbox"
                                                                               @checked($rip)
                                                                               wire:change="toggleAnomaliaRiparata({{ $pi->id }}, {{ $anomId }}, $event.target.checked)"
                                                                               class="h-5 w-5 border-gray-300">
                                                                        <span>Riparata</span>
                                                                    </label>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="p-2">
                                            @if ($d['sostituito_con'] ?? false)
                                                <div class="text-green-500 font-semibold">Sostituito con presidio: {{ $d['sostituito_con']->id }}</div>
                                            @else
                                                <button wire:click="toggleSostituzione({{ $pi->id }})" class="bg-blue-500 text-white px-2 py-1 rounded">
                                                    {{ $d['sostituzione'] ? 'Annulla' : 'Sostituisci' }}
                                                </button>
                                                @if ($d['sostituzione'])
                                                    @php $cat = $pi->presidio->categoria ?? 'Estintore'; @endphp
                                                    <div class="mt-2 space-y-1">
                                                        @if($cat === 'Estintore')
                                                            <select wire:model="input.{{ $pi->id }}.nuovo_tipo_estintore_id" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full border-gray-300 rounded px-2 py-1">
                                                                <option value="">Tipo Estintore</option>
                                                                @foreach ($tipiEstintori as $tipo)
                                                                    <option value="{{ $tipo->id }}">{{ $tipo->sigla }} – {{ $tipo->descrizione }}</option>
                                                                @endforeach
                                                            </select>
                                                            <input type="date" wire:model="input.{{ $pi->id }}.nuova_data_serbatoio" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full border-gray-300 rounded px-2 py-1">
                                                            <div class="flex items-center gap-2">
                                                                <input type="text" list="marca-serbatoio-opzioni" wire:model="input.{{ $pi->id }}.nuova_marca_serbatoio" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full border-gray-300 rounded px-2 py-1" placeholder="Marca serbatoio (MB / altro)">
                                                                <button type="button" wire:click="setMarcaMbSostituzione({{ $pi->id }})" class="px-2 py-1 text-xs rounded border border-gray-300 hover:bg-gray-50">MB</button>
                                                            </div>
                                                            <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                                                <input type="checkbox" wire:model="input.{{ $pi->id }}.nuova_marca_mb" class="border-gray-300">
                                                                Flag MB
                                                            </label>
                                                            <input type="date" wire:model="input.{{ $pi->id }}.nuova_data_ultima_revisione" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full border-gray-300 rounded px-2 py-1" placeholder="Ultima revisione">
                                                            <label class="flex gap-1 items-center text-sm">
                                                                <input type="checkbox" wire:model="input.{{ $pi->id }}.usa_ritiro" class="border-gray-300">
                                                                Presidio usato
                                                            </label>
                                                            @php $prev = $previewSostituzione[$pi->id] ?? null; @endphp
                                                            @if(!empty($prev))
                                                                <div class="text-xs bg-gray-50 border border-gray-200 rounded p-2 text-gray-700">
                                                                    <span class="font-semibold">Preview scadenze:</span>
                                                                    Revisione: <strong>{{ $prev['revisione'] ? \Carbon\Carbon::parse($prev['revisione'])->format('d/m/Y') : '—' }}</strong>,
                                                                    Collaudo: <strong>{{ $prev['collaudo'] ? \Carbon\Carbon::parse($prev['collaudo'])->format('d/m/Y') : '—' }}</strong>,
                                                                    Fine vita: <strong>{{ $prev['fine_vita'] ? \Carbon\Carbon::parse($prev['fine_vita'])->format('d/m/Y') : '—' }}</strong>,
                                                                    Sostituzione: <strong>{{ $prev['sostituzione'] ? \Carbon\Carbon::parse($prev['sostituzione'])->format('d/m/Y') : '—' }}</strong>
                                                                </div>
                                                            @endif
                                                        @elseif($cat === 'Idrante')
                                                            <select wire:model="input.{{ $pi->id }}.nuovo_idrante_tipo_id" class="w-full border-gray-300 rounded px-2 py-1">
                                                                <option value="">Tipo Idrante</option>
                                                                @foreach ($tipiIdranti as $id => $tipo)
                                                                    <option value="{{ $id }}">{{ $tipo }}</option>
                                                                @endforeach
                                                            </select>
                                                        @elseif($cat === 'Porta')
                                                            <select wire:model="input.{{ $pi->id }}.nuovo_porta_tipo_id" class="w-full border-gray-300 rounded px-2 py-1">
                                                                <option value="">Tipo Porta</option>
                                                                @foreach ($tipiPorte as $id => $tipo)
                                                                    <option value="{{ $id }}">{{ $tipo }}</option>
                                                                @endforeach
                                                            </select>
                                                        @endif
                                                        <button wire:click="sostituisciPresidio({{ $pi->id }})" class="bg-blue-600 text-white px-3 py-1 rounded text-sm mt-2">
                                                            Conferma Sostituzione
                                                        </button>
                                                    </div>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="p-2">
                                            <textarea wire:model.lazy="input.{{ $pi->id }}.note" class="w-full border-gray-300 rounded px-2 py-1" rows="2"></textarea>
                                        </td>
                                        <td class="p-2">
                                            @php
                                                $hex = $pi->presidio->tipoEstintore?->colore?->hex ?? null;
                                                $cat = $pi->presidio->categoria ?? 'Estintore';
                                            @endphp
                                            <div class="flex items-center gap-2">
                                                @if($hex)
                                                    <span class="inline-block w-2.5 h-2.5 rounded-full ring-1 ring-black/10"
                                                          style="background-color: {{ $hex }}"></span>
                                                @endif
                                                @if($cat === 'Estintore')
                                                    <span><strong>Tipo Estintore:</strong> {{ $pi->presidio->tipoEstintore?->sigla }} – {{ $pi->presidio->tipoEstintore?->descrizione }}</span>
                                                @elseif($cat === 'Idrante')
                                                    <span><strong>Tipo Idrante:</strong> {{ $pi->presidio->idranteTipoRef?->nome ?? $pi->presidio->idrante_tipo ?? '—' }}</span>
                                                @elseif($cat === 'Porta')
                                                    <span><strong>Tipo Porta:</strong> {{ $pi->presidio->portaTipoRef?->nome ?? $pi->presidio->porta_tipo ?? '—' }}</span>
                                                @else
                                                    <span>—</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="p-2">
                                            @if($d['deve_ritirare'])
                                                <span class="text-red-600 font-semibold">Sì</span>
                                            @else
                                                <span class="text-gray-500">—</span>
                                            @endif
                                        </td>
                                        <td class="p-2 text-center">
                                            <button wire:click="toggleEditPresidio({{ $pi->id }})"
                                                class="text-blue-600 hover:text-blue-800 text-sm mr-2"
                                                title="Modifica presidio">
                                                ✏️
                                            </button>
                                            <button wire:click="rimuoviPresidioIntervento({{ $pi->id }})"
                                                class="text-red-600 hover:text-red-800 text-sm"
                                                onclick="return confirm('Rimuovere questo presidio dall\'intervento?')">
                                                ❌
                                            </button>
                                        </td>
                                    </tr>
                                    @if(($editMode[$pi->id] ?? false))
                                        @php $catEdit = $pi->presidio->categoria ?? 'Estintore'; @endphp
                                        <tr class="bg-gray-50">
                                            <td colspan="9" class="p-3">
                                                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                                                    <div>
                                                        <label class="text-xs text-gray-600">Progressivo</label>
                                                        <input type="text" wire:model.defer="editPresidio.{{ $pi->id }}.progressivo" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                                    </div>
                                                    <div class="md:col-span-2">
                                                        <label class="text-xs text-gray-600">Ubicazione</label>
                                                        <input type="text" wire:model.defer="editPresidio.{{ $pi->id }}.ubicazione" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                                    </div>
                                                    @if($catEdit === 'Estintore')
                                                        <div>
                                                            <label class="text-xs text-gray-600">Tipo Estintore</label>
                                                            <select wire:model.defer="editPresidio.{{ $pi->id }}.tipo_estintore_id" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                                                <option value="">Seleziona tipo</option>
                                                                @foreach($tipiEstintori as $tipo)
                                                                    <option value="{{ $tipo->id }}">{{ $tipo->sigla }} – {{ $tipo->descrizione }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-gray-600">Data Serbatoio</label>
                                                            <input type="date" wire:model.defer="editPresidio.{{ $pi->id }}.data_serbatoio" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                                        </div>
                                                        <div>
                                                            <label class="text-xs text-gray-600">Ultima Revisione</label>
                                                            <input type="date" wire:model.defer="editPresidio.{{ $pi->id }}.data_ultima_revisione" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                                        </div>
                                                    @elseif($catEdit === 'Idrante')
                                                        <div>
                                                            <label class="text-xs text-gray-600">Tipo Idrante</label>
                                                            <select wire:model.defer="editPresidio.{{ $pi->id }}.idrante_tipo_id" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                                                <option value="">Seleziona tipo</option>
                                                                @foreach($tipiIdranti as $id => $tipo)
                                                                    <option value="{{ $id }}">{{ $tipo }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @elseif($catEdit === 'Porta')
                                                        <div>
                                                            <label class="text-xs text-gray-600">Tipo Porta</label>
                                                            <select wire:model.defer="editPresidio.{{ $pi->id }}.porta_tipo_id" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                                                <option value="">Seleziona tipo</option>
                                                                @foreach($tipiPorte as $id => $tipo)
                                                                    <option value="{{ $id }}">{{ $tipo }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="mt-3 flex items-center gap-2">
                                                    <button wire:click="salvaModificaPresidio({{ $pi->id }})" class="px-3 py-1 text-sm rounded bg-green-600 text-white">Salva</button>
                                                    <button wire:click="toggleEditPresidio({{ $pi->id }})" class="px-3 py-1 text-sm rounded border">Annulla</button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @else
    {{-- Vista a Schede --}}
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($intervento->presidiIntervento as $pi)
            @php $d = $input[$pi->id]; @endphp
            @php $nonVerificato = ($d['esito'] ?? 'non_verificato') === 'non_verificato'; @endphp
            @php
                $cat = $pi->presidio->categoria ?? 'Estintore';
                $catBorder = [
                    'Estintore' => 'border-red-400',
                    'Idrante' => 'border-blue-400',
                    'Porta' => 'border-amber-400',
                ][$cat] ?? 'border-gray-300';
                $catBadge = [
                    'Estintore' => 'bg-red-100 text-red-700',
                    'Idrante' => 'bg-blue-100 text-blue-700',
                    'Porta' => 'bg-amber-100 text-amber-700',
                ][$cat] ?? 'bg-gray-100 text-gray-700';
                $catIcon = [
                    'Estintore' => 'fa-fire-extinguisher',
                    'Idrante' => 'fa-tint',
                    'Porta' => 'fa-door-open',
                ][$cat] ?? 'fa-tag';
                $bgClass = $nonVerificato ? 'bg-red-50' : 'bg-white';
                $hex = $pi->presidio->tipoEstintore?->colore?->hex ?? null;
                $cardRgba = null;
                if ($hex) {
                    $h = ltrim($hex, '#');
                    if (strlen($h) === 6) {
                        $r = hexdec(substr($h, 0, 2));
                        $g = hexdec(substr($h, 2, 2));
                        $b = hexdec(substr($h, 4, 2));
                        $cardRgba = "rgba($r, $g, $b, 0.06)";
                    }
                }
            @endphp
        <div class="border rounded shadow p-4 border-l-4 {{ $catBorder }} {{ $bgClass }}"
             style="{{ (!$nonVerificato && $cardRgba) ? 'background-color: '.$cardRgba.';' : '' }}">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-md font-semibold text-gray-800">
                    <i class="fa {{ $catIcon }} text-gray-500 mr-1"></i>
                    Presidio #{{ $pi->presidio->progressivo }}
                </h3>
                <div class="flex flex-col items-end gap-1">
                    <span class="text-xs px-2 py-0.5 rounded {{ $catBadge }}">{{ strtoupper($cat) }}</span>
                    @if($cat === 'Idrante' && $showControlloAnnualeIdranti)
                        <span class="inline-flex items-center px-2 py-1 rounded text-[11px] font-bold bg-orange-500 text-white shadow-sm">
                            CONTROLLO ANNUALE
                        </span>
                    @endif
                </div>
            </div>

                <div class="space-y-2">
                    <div>
                        <label class="text-sm">Ubicazione</label>
                        <input type="text" wire:model.lazy="input.{{ $pi->id }}.ubicazione" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                    </div>
                    <div class="text-sm text-gray-700 flex items-center gap-2">
                        @php
                            $hex = $pi->presidio->tipoEstintore?->colore?->hex ?? null;
                        @endphp
                        @if($hex)
                            <span class="inline-block w-2.5 h-2.5 rounded-full ring-1 ring-black/10"
                                  style="background-color: {{ $hex }}"></span>
                        @endif
                        @if($cat === 'Estintore')
                            <strong>Tipo Estintore:</strong> {{ $pi->presidio->tipoEstintore?->sigla }} – {{ $pi->presidio->tipoEstintore?->descrizione }}
                        @elseif($cat === 'Idrante')
                            <strong>Tipo Idrante:</strong> {{ $pi->presidio->idranteTipoRef?->nome ?? $pi->presidio->idrante_tipo ?? '—' }}
                        @elseif($cat === 'Porta')
                            <strong>Tipo Porta:</strong> {{ $pi->presidio->portaTipoRef?->nome ?? $pi->presidio->porta_tipo ?? '—' }}
                        @else
                            <strong>Tipo:</strong> —
                        @endif
                    </div>

                    @if($d['deve_ritirare'])
                        <div class="text-sm font-semibold text-red-600">
                            ⚠️ Da ritirare questo mese (revisione, collaudo o fine vita)
                        </div>
                    @endif

                    <div>
                        <label class="text-sm">Esito</label>
                        <select wire:model="input.{{ $pi->id }}.esito" wire:change="salvaEsito({{ $pi->id }})" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                            <option value="verificato">✅ Verificato</option>
                            <option value="non_verificato">❌ Non Verificato</option>
                            <option value="anomalie">⚠️ Anomalie</option>
                            <option value="sostituito">🔁 Sostituito</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm">Anomalie</label>
                        @php
                            $anomList = ($anomalie[$pi->presidio->categoria] ?? collect());
                            $selectedAnom = collect($input[$pi->id]['anomalie'] ?? [])->map(fn($v)=>(int)$v)->values()->all();
                            $anomMap = $anomList->pluck('etichetta', 'id')->toArray();
                        @endphp
                        <div class="space-y-2">
                            <div class="space-y-1 max-h-44 overflow-auto border border-gray-200 rounded p-2 bg-white">
                                @forelse($anomList as $anomalia)
                                    @php $sel = in_array((int) $anomalia->id, $selectedAnom, true); @endphp
                                    <label class="flex items-center gap-2 text-sm py-1">
                                        <input type="checkbox"
                                               @checked($sel)
                                               wire:change="toggleAnomalia({{ $pi->id }}, {{ $anomalia->id }}, $event.target.checked)"
                                               class="h-5 w-5 border-gray-300">
                                        <span>{{ $anomalia->etichetta }}</span>
                                    </label>
                                @empty
                                    <div class="text-xs text-gray-500">Nessuna anomalia configurata per questa categoria.</div>
                                @endforelse
                            </div>

                            @if(!empty($selectedAnom))
                                <div class="border border-blue-200 rounded bg-blue-50 p-2">
                                    <div class="text-xs font-semibold text-blue-800 mb-1">Stato anomalie selezionate</div>
                                    <div class="space-y-2">
                                        @foreach($selectedAnom as $anomId)
                                            @php
                                                $rip = (bool)($input[$pi->id]['anomalie_riparate'][$anomId] ?? false);
                                                $label = $anomMap[$anomId] ?? ('Anomalia #'.$anomId);
                                            @endphp
                                            <div class="flex items-center justify-between gap-2 text-sm">
                                                <span class="truncate">{{ $label }}</span>
                                                <div class="flex items-center gap-3 shrink-0">
                                                    <label class="inline-flex items-center gap-2">
                                                        <input type="checkbox"
                                                               @checked($rip)
                                                               wire:change="toggleAnomaliaRiparata({{ $pi->id }}, {{ $anomId }}, $event.target.checked)"
                                                               class="h-5 w-5 border-gray-300">
                                                        <span class="text-xs">Riparata</span>
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="text-sm">Note</label>
                        <textarea wire:model.lazy="input.{{ $pi->id }}.note" rows="2" class="w-full text-sm border-gray-300 rounded px-2 py-1"></textarea>
                    </div>

                    {{-- Sostituzione --}}
                    <div>
                        @if ($d['sostituito_con'] ?? false)
                            <div class="text-green-600 text-sm font-semibold flex items-center gap-2">
                                <i class="fa fa-check-circle text-green-500"></i>
                                Sostituito con presidio: {{ $d['sostituito_con']->id }}

                                @if ($d['usa_ritiro'] ?? false)
                                    <span class="ml-2 px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded">
                                        Presidio usato
                                    </span>
                                @endif
                            </div>
                        @else
                            <div class="flex items-center gap-2 mt-2">
                                <button wire:click="toggleSostituzione({{ $pi->id }})" class="bg-blue-500 text-white py-1 px-3 rounded text-sm">
                                    {{ $d['sostituzione'] ? 'Annulla Sostituzione' : 'Sostituisci Presidio' }}
                                </button>
                            </div>

                            @if ($d['sostituzione'] ?? false)
                                @php $cat = $pi->presidio->categoria ?? 'Estintore'; @endphp
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-3">
                                    @if($cat === 'Estintore')
                                        <select wire:model="input.{{ $pi->id }}.nuovo_tipo_estintore_id" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                            <option value="">Tipo Estintore</option>
                                            @foreach ($tipiEstintori as $tipo)
                                                <option value="{{ $tipo->id }}">{{ $tipo->sigla }} – {{ $tipo->descrizione }}</option>
                                            @endforeach
                                        </select>

                                        <input type="date" wire:model="input.{{ $pi->id }}.nuova_data_serbatoio" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                        <div class="flex items-center gap-2">
                                            <input type="text" list="marca-serbatoio-opzioni" wire:model="input.{{ $pi->id }}.nuova_marca_serbatoio" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full text-sm border-gray-300 rounded px-2 py-1" placeholder="Marca serbatoio (MB / altro)">
                                            <button type="button" wire:click="setMarcaMbSostituzione({{ $pi->id }})" class="px-2 py-1 text-xs rounded border border-gray-300 hover:bg-gray-50">MB</button>
                                        </div>
                                        <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                            <input type="checkbox" wire:model="input.{{ $pi->id }}.nuova_marca_mb" class="border-gray-300">
                                            Flag MB
                                        </label>
                                        <input type="date" wire:model="input.{{ $pi->id }}.nuova_data_ultima_revisione" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                    @elseif($cat === 'Idrante')
                                        <select wire:model="input.{{ $pi->id }}.nuovo_idrante_tipo_id" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                            <option value="">Tipo Idrante</option>
                                            @foreach ($tipiIdranti as $id => $tipo)
                                                <option value="{{ $id }}">{{ $tipo }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($cat === 'Porta')
                                        <select wire:model="input.{{ $pi->id }}.nuovo_porta_tipo_id" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                            <option value="">Tipo Porta</option>
                                            @foreach ($tipiPorte as $id => $tipo)
                                                <option value="{{ $id }}">{{ $tipo }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                @if($cat === 'Estintore')
                                    @php $prev = $previewSostituzione[$pi->id] ?? null; @endphp
                                    @if(!empty($prev))
                                        <div class="mt-2 text-xs bg-gray-50 border border-gray-200 rounded p-2 text-gray-700">
                                            <span class="font-semibold">Preview scadenze:</span>
                                            Revisione: <strong>{{ $prev['revisione'] ? \Carbon\Carbon::parse($prev['revisione'])->format('d/m/Y') : '—' }}</strong>,
                                            Collaudo: <strong>{{ $prev['collaudo'] ? \Carbon\Carbon::parse($prev['collaudo'])->format('d/m/Y') : '—' }}</strong>,
                                            Fine vita: <strong>{{ $prev['fine_vita'] ? \Carbon\Carbon::parse($prev['fine_vita'])->format('d/m/Y') : '—' }}</strong>,
                                            Sostituzione: <strong>{{ $prev['sostituzione'] ? \Carbon\Carbon::parse($prev['sostituzione'])->format('d/m/Y') : '—' }}</strong>
                                        </div>
                                    @endif
                                @endif

                                @if($cat === 'Estintore')
                                    <div class="mt-2">
                                        <label class="text-sm">Stato del presidio ritirato</label>
                                        <select wire:model="input.{{ $pi->id }}.stato_presidio_ritirato" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                            <option value="Disponibile">Disponibile</option>
                                            <option value="Rottamato">Rottamato</option>
                                            <option value="Da Revisionare">Da Revisionare</option>
                                        </select>
                                    </div>
                                @endif

                                @if($cat === 'Estintore')
                                    <div class="mt-2">
                                        <label class="text-sm flex items-center gap-2">
                                            <input type="checkbox" wire:model="input.{{ $pi->id }}.usa_ritiro" class="border-gray-300">
                                            Presidio usato
                                        </label>
                                    </div>
                                @endif

                                <div class="mt-2">
                                    <button wire:click="sostituisciPresidio({{ $pi->id }})" class="bg-green-600 text-white py-2 px-4 rounded text-sm">
                                        ✅ Conferma Sostituzione
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="mt-3">
                        <button wire:click="toggleEditPresidio({{ $pi->id }})" class="text-xs px-2 py-1 rounded border">
                            ✏️ Modifica dati presidio
                        </button>
                    </div>
                    @if(($editMode[$pi->id] ?? false))
                        @php $catEdit = $pi->presidio->categoria ?? 'Estintore'; @endphp
                        <div class="mt-3 p-3 border rounded bg-gray-50 space-y-2">
                            <div>
                                <label class="text-xs text-gray-600">Progressivo</label>
                                <input type="text" wire:model.defer="editPresidio.{{ $pi->id }}.progressivo" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Ubicazione</label>
                                <input type="text" wire:model.defer="editPresidio.{{ $pi->id }}.ubicazione" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                            </div>
                            @if($catEdit === 'Estintore')
                                <div>
                                    <label class="text-xs text-gray-600">Tipo Estintore</label>
                                    <select wire:model.defer="editPresidio.{{ $pi->id }}.tipo_estintore_id" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                        <option value="">Seleziona tipo</option>
                                        @foreach($tipiEstintori as $tipo)
                                            <option value="{{ $tipo->id }}">{{ $tipo->sigla }} – {{ $tipo->descrizione }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-600">Data Serbatoio</label>
                                    <input type="date" wire:model.defer="editPresidio.{{ $pi->id }}.data_serbatoio" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-600">Ultima Revisione</label>
                                    <input type="date" wire:model.defer="editPresidio.{{ $pi->id }}.data_ultima_revisione" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                </div>
                            @elseif($catEdit === 'Idrante')
                                <div>
                                    <label class="text-xs text-gray-600">Tipo Idrante</label>
                                    <select wire:model.defer="editPresidio.{{ $pi->id }}.idrante_tipo_id" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                        <option value="">Seleziona tipo</option>
                                        @foreach($tipiIdranti as $id => $tipo)
                                            <option value="{{ $id }}">{{ $tipo }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif($catEdit === 'Porta')
                                <div>
                                    <label class="text-xs text-gray-600">Tipo Porta</label>
                                    <select wire:model.defer="editPresidio.{{ $pi->id }}.porta_tipo_id" class="w-full border-gray-300 rounded px-2 py-1 text-sm">
                                        <option value="">Seleziona tipo</option>
                                        @foreach($tipiPorte as $id => $tipo)
                                            <option value="{{ $id }}">{{ $tipo }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="flex items-center gap-2">
                                <button wire:click="salvaModificaPresidio({{ $pi->id }})" class="px-3 py-1 text-sm rounded bg-green-600 text-white">Salva</button>
                                <button wire:click="toggleEditPresidio({{ $pi->id }})" class="px-3 py-1 text-sm rounded border">Annulla</button>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="mt-4 text-end">
                    <button wire:click="rimuoviPresidioIntervento({{ $pi->id }})"
                        class="text-xs text-red-600 hover:text-red-800"
                        onclick="return confirm('Rimuovere questo presidio dall\'intervento?')">
                        ❌ Rimuovi questo presidio
                    </button>
                </div>

            </div>
        @endforeach
    </div>
    @endif

    {{-- Riepilogo Ordine Preventivo --}}
    @php
        $confrontoOrdine = $riepilogoOrdine['confronto'] ?? [];
        $anomalieRiep = $riepilogoOrdine['anomalie'] ?? ['totale' => 0, 'riparate' => 0, 'preventivo' => 0, 'dettaglio' => []];
        $senzaCodice = $riepilogoOrdine['presidi_senza_codice'] ?? [];
    @endphp
    <div class="bg-white border rounded p-4 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <h2 class="text-lg font-semibold text-gray-800">Riepilogo Ordine Preventivo</h2>
            <button wire:click="ricaricaOrdinePreventivo" class="px-3 py-1 text-xs rounded border border-gray-300 hover:bg-gray-50">
                Ricarica ordine da Business
            </button>
        </div>

        <div>
            <div class="text-sm font-semibold mb-1">Riepilogo presidi intervento (senza prezzi)</div>
            <div class="overflow-auto border rounded">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-100 text-gray-600">
                        <tr>
                            <th class="p-2 text-left">Cod. Art.</th>
                            <th class="p-2 text-left">Descrizione</th>
                            <th class="p-2 text-right">Q.tà</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($riepilogoOrdine['righe_intervento'] ?? []) as $riga)
                            <tr class="border-t">
                                <td class="p-2 font-mono">{{ $riga['codice_articolo'] }}</td>
                                <td class="p-2">{{ $riga['descrizione'] ?: '—' }}</td>
                                <td class="p-2 text-right">{{ number_format((float)$riga['quantita'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-2 text-gray-500">Nessun presidio nel riepilogo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="text-[11px] text-gray-500 mt-1">I prezzi vengono letti solo dall'ordine Business.</div>
        </div>

        @if(!empty($senzaCodice))
            <div class="rounded border border-amber-300 bg-amber-50 p-3 text-xs">
                <div class="font-semibold text-amber-800 mb-1">Presidi senza codice articolo di fatturazione</div>
                <div class="space-y-1">
                    @foreach($senzaCodice as $row)
                        <div>{{ $row['categoria'] }} #{{ $row['progressivo'] }} — {{ $row['tipo'] }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(!($ordinePreventivo['found'] ?? false))
            <div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
                {{ $ordinePreventivo['error'] ?? 'Ordine preventivo non trovato.' }}
            </div>
        @else
            @php $h = $ordinePreventivo['header'] ?? []; @endphp
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-xs">
                <div class="rounded border border-gray-200 p-2">
                    <div class="text-gray-500">Ordine</div>
                    <div class="font-semibold">{{ ($h['tipork'] ?? '-') . '/' . ($h['serie'] ?? '-') . '/' . ($h['anno'] ?? '-') . '/' . ($h['numero'] ?? '-') }}</div>
                </div>
                <div class="rounded border border-gray-200 p-2">
                    <div class="text-gray-500">Data</div>
                    <div class="font-semibold">{{ !empty($h['data']) ? \Carbon\Carbon::parse($h['data'])->format('d/m/Y') : '—' }}</div>
                </div>
                <div class="rounded border border-gray-200 p-2">
                    <div class="text-gray-500">Conto</div>
                    <div class="font-semibold">{{ $h['conto'] ?? '—' }}</div>
                </div>
                <div class="rounded border border-gray-200 p-2">
                    <div class="text-gray-500">Totale Documento</div>
                    <div class="font-semibold">€ {{ number_format((float)($h['totale_documento'] ?? 0), 2, ',', '.') }}</div>
                </div>
            </div>

            <div>
                <div class="text-sm font-semibold mb-1">Righe ordine (Business)</div>
                <div class="overflow-auto border rounded">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-100 text-gray-600">
                            <tr>
                                <th class="p-2 text-left">Cod. Art.</th>
                                <th class="p-2 text-left">Descrizione</th>
                                <th class="p-2 text-right">Q.tà</th>
                                <th class="p-2 text-right">Prezzo</th>
                                <th class="p-2 text-right">Importo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($ordinePreventivo['rows'] ?? []) as $riga)
                                <tr class="border-t">
                                    <td class="p-2 font-mono">{{ $riga['codice_articolo'] }}</td>
                                    <td class="p-2">{{ $riga['descrizione'] ?: '—' }}</td>
                                    <td class="p-2 text-right">{{ number_format((float)$riga['quantita'], 2, ',', '.') }}</td>
                                    <td class="p-2 text-right">€ {{ number_format((float)$riga['prezzo_unitario'], 2, ',', '.') }}</td>
                                    <td class="p-2 text-right">€ {{ number_format((float)$riga['importo'], 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-2 text-gray-500">Nessuna riga ordine.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($confrontoOrdine['ok'] ?? false)
                <div class="rounded border border-green-300 bg-green-50 p-3 text-sm text-green-700">
                    Nessuna differenza tra ordine preventivo e presidi dell'intervento.
                </div>
            @else
                <div class="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-700 space-y-2">
                    <div class="font-semibold">Differenze rilevate</div>
                    @foreach(($confrontoOrdine['solo_ordine'] ?? []) as $row)
                        <div>Solo in ordine: {{ $row['codice_articolo'] }} ({{ $row['descrizione'] ?: '—' }}) — q.tà ordine {{ number_format((float)$row['quantita_ordine'], 2, ',', '.') }}</div>
                    @endforeach
                    @foreach(($confrontoOrdine['solo_intervento'] ?? []) as $row)
                        <div>Solo in intervento: {{ $row['codice_articolo'] }} ({{ $row['descrizione'] ?: '—' }}) — q.tà intervento {{ number_format((float)$row['quantita_intervento'], 2, ',', '.') }}</div>
                    @endforeach
                    @foreach(($confrontoOrdine['differenze_quantita'] ?? []) as $row)
                        <div>Q.tà diversa: {{ $row['codice_articolo'] }} — ordine {{ number_format((float)$row['quantita_ordine'], 2, ',', '.') }}, intervento {{ number_format((float)$row['quantita_intervento'], 2, ',', '.') }}</div>
                    @endforeach
                </div>
            @endif
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="rounded border border-gray-200 p-2">
                <div class="text-xs text-gray-500">Anomalie totali</div>
                <div class="text-lg font-semibold">{{ $anomalieRiep['totale'] ?? 0 }}</div>
            </div>
            <div class="rounded border border-green-200 bg-green-50 p-2">
                <div class="text-xs text-green-700">Riparate</div>
                <div class="text-lg font-semibold text-green-700">{{ $anomalieRiep['riparate'] ?? 0 }}</div>
            </div>
            <div class="rounded border border-amber-200 bg-amber-50 p-2">
                <div class="text-xs text-amber-700">Da preventivare</div>
                <div class="text-lg font-semibold text-amber-700">{{ $anomalieRiep['preventivo'] ?? 0 }}</div>
            </div>
        </div>

        @if(!empty($anomalieRiep['dettaglio']))
            <div class="overflow-auto border rounded">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-100 text-gray-600">
                        <tr>
                            <th class="p-2 text-left">Anomalia</th>
                            <th class="p-2 text-right">Totale</th>
                            <th class="p-2 text-right">Riparate</th>
                            <th class="p-2 text-right">Preventivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($anomalieRiep['dettaglio'] as $row)
                            <tr class="border-t">
                                <td class="p-2">{{ $row['etichetta'] }}</td>
                                <td class="p-2 text-right">{{ $row['totale'] }}</td>
                                <td class="p-2 text-right text-green-700">{{ $row['riparate'] }}</td>
                                <td class="p-2 text-right text-amber-700">{{ $row['preventivo'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

{{-- Tempo Effettivo --}}
<div class="mt-6 space-y-3">
    @if ($messaggioErrore)
        <div 
            x-data="{ show: true }" 
            x-show="show" 
            x-init="setTimeout(() => show = false, 4000)" 
            class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded transition-all"
        >
            {{ $messaggioErrore }}
        </div>
    @endif

    @if ($messaggioSuccesso)
        <div 
            x-data="{ show: true }" 
            x-show="show" 
            x-init="setTimeout(() => show = false, 4000)" 
            class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded transition-all"
        >
            {{ $messaggioSuccesso }}
        </div>
    @endif



    <div>
        <label class="text-sm font-medium">⏱ Tempo effettivo (minuti)</label>
        <input type="number" wire:model="durataEffettiva" class="input input-sm input-bordered w-full max-w-xs">
    </div>
    <div class="my-4 p-4 border rounded bg-white">
        <label class="block font-medium mb-2">Firma Cliente:</label>
        <canvas id="firmaCanvas" width="600" height="300" style="border:1px solid #ccc;"></canvas>

        <div class="mt-3">
            <button onclick="salvaFirma()"  class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">Salva Firma</button>
            <button onclick="pulisciFirma()"  class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 text-sm">Cancella Firma</button>
        </div>
    </div>




    <div class="sticky bottom-0 z-20 -mx-3 sm:mx-0 mt-6 border-t border-gray-200 bg-white/95 backdrop-blur px-3 py-3">
        <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
            <button wire:click="salva" class="w-full sm:w-auto px-4 py-3 bg-green-600 text-white rounded hover:bg-green-700 text-sm font-semibold">
                💾 Salva e completa intervento
            </button>
            <a href="{{ route('rapportino.pdf', $intervento->id) }}" target="_blank"
               class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-3 rounded border border-green-600 text-green-700 hover:bg-green-50 text-sm font-medium">
                <i class="fa fa-file-pdf mr-1"></i> Scarica Rapportino PDF
            </a>
        </div>
    </div>

</div>
</div>
<script>
let canvas = document.getElementById('firmaCanvas');
let ctx = canvas.getContext('2d');
let drawing = false;

document.addEventListener('livewire:init', () => {
    if (!window.__evadiInterventoCompletatoHooked) {
        window.__evadiInterventoCompletatoHooked = true;
        Livewire.on('intervento-completato', (payload) => {
            const pdfUrl = payload?.pdfUrl ?? null;
            const redirectUrl = payload?.redirectUrl ?? null;
            if (pdfUrl) {
                window.open(pdfUrl, '_blank');
            }
            if (redirectUrl) {
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 500);
            }
        });
    }
});

canvas.addEventListener('mousedown', start);
canvas.addEventListener('mouseup', stop);
canvas.addEventListener('mouseout', stop);
canvas.addEventListener('mousemove', draw);

// Supporto Touchscreen
canvas.addEventListener('touchstart', (e) => { start(e.touches[0]) });
canvas.addEventListener('touchend', stop);
canvas.addEventListener('touchmove', (e) => {
    draw(e.touches[0]);
    e.preventDefault();
});

function start(e) {
    drawing = true;
    ctx.beginPath();
    ctx.moveTo(getX(e), getY(e));
}

function stop() {
    drawing = false;
}

function draw(e) {
    if (!drawing) return;
    ctx.lineTo(getX(e), getY(e));
    ctx.stroke();
}

function getX(e) {
    return e.clientX - canvas.getBoundingClientRect().left;
}

function getY(e) {
    return e.clientY - canvas.getBoundingClientRect().top;
}

function pulisciFirma() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function salvaFirma() {
    let base64 = canvas.toDataURL("image/png");
    Livewire.dispatch('firmaClienteAcquisita', { data: { base64: base64 } });
}
</script>
