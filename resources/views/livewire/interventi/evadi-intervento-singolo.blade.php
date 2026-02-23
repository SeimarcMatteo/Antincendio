<div id="evadi-intervento-root"
     data-intervento-id="{{ $intervento->id }}"
     class="max-w-7xl mx-auto px-2 sm:px-4 py-4 sm:py-6 space-y-6 lg:space-y-7">
    <h1 class="text-2xl font-bold text-red-700">
        🛠 Evadi Intervento:
        <a href="{{ route('clienti.mostra', $intervento->cliente_id) }}" class="text-gray-800 hover:text-red-800 underline">
            {{ $intervento->cliente->nome }}
        </a>
    </h1>

    <div id="offline-sync-banner" class="hidden rounded border px-3 py-2 text-sm"></div>

    <div class="bg-white border rounded p-3 shadow-sm text-sm text-gray-700">
        <div class="font-semibold text-gray-800 mb-1">Dati cliente</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
            <div>Indirizzo: <span class="font-medium">{{ $intervento->cliente->indirizzo ?? '—' }}</span></div>
            <div>Città: <span class="font-medium">{{ $intervento->cliente->citta ?? '—' }}</span></div>
            <div>Zona: <span class="font-medium">{{ $intervento->cliente->zona ?? '—' }}</span></div>
            <div>Telefono: <span class="font-medium">{{ $intervento->cliente->telefono ?? '—' }}</span></div>
            <div>Email: <span class="font-medium">{{ $intervento->cliente->email ?? '—' }}</span></div>
            <div>Forma pagamento: <span class="font-medium">{{ $formaPagamentoDescrizione ?: '—' }}</span></div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-yellow-50 border-2 border-yellow-300 rounded p-3 shadow-sm">
            <div class="text-sm font-extrabold text-yellow-900 uppercase tracking-wide">Note Intervento</div>
            <div class="mt-1 text-base font-bold text-gray-900 whitespace-pre-wrap">{{ $noteInterventoGenerali ?: 'Nessuna nota intervento.' }}</div>
            <div class="mt-3">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Modifica rapida note intervento</label>
                <textarea wire:model.defer="noteInterventoGenerali"
                          rows="3"
                          class="w-full border-gray-300 rounded px-2 py-2 text-sm"
                          placeholder="Inserisci note intervento..."></textarea>
                <button wire:click="salvaNoteInterventoGenerali"
                        class="mt-2 px-3 py-2 text-xs font-semibold rounded bg-yellow-600 text-white hover:bg-yellow-700">
                    Salva note intervento
                </button>
            </div>
        </div>

        <div class="bg-blue-50 border-2 border-blue-300 rounded p-3 shadow-sm">
            <div class="text-sm font-extrabold text-blue-900 uppercase tracking-wide">Note Anagrafica Cliente</div>
            <div class="mt-1 text-base font-bold text-gray-900 whitespace-pre-wrap">{{ $noteClienteAnagrafica ?: 'Nessuna nota anagrafica.' }}</div>
            <div class="mt-3">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Modifica rapida note anagrafica cliente</label>
                <textarea wire:model.defer="noteClienteAnagrafica"
                          rows="3"
                          class="w-full border-gray-300 rounded px-2 py-2 text-sm"
                          placeholder="Inserisci note anagrafica cliente..."></textarea>
                <button wire:click="salvaNoteClienteAnagrafica"
                        class="mt-2 px-3 py-2 text-xs font-semibold rounded bg-blue-600 text-white hover:bg-blue-700">
                    Salva note anagrafica
                </button>
            </div>
            <div class="mt-2 text-[11px] font-semibold text-blue-800">
                Le note anagrafica salvate qui non vengono sovrascritte dalla sync clienti Business.
            </div>
        </div>
    </div>

    @php
        $lastSession = $timerSessioni[0] ?? null;
        $start = $lastSession['started_at'] ?? '—';
        $end = $lastSession['ended_at'] ?? '—';
    @endphp
    <div class="text-sm bg-white border rounded p-3 shadow-sm space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <div class="font-semibold text-gray-700">⏱ Timer intervento</div>
            @if($timerDisponibilePerUtente)
                <div class="text-xs text-gray-600 sm:ml-3">
                    Ultima sessione: <span class="font-medium">{{ $start }}</span> · <span class="font-medium">{{ $end }}</span>
                </div>
                <div class="text-xs font-semibold text-gray-700 sm:ml-auto">
                    Totale: {{ intdiv($timerTotaleMinuti, 60) }}h {{ $timerTotaleMinuti % 60 }}m
                </div>
            @endif
        </div>

        @if(!$timerDisponibilePerUtente)
            <div class="rounded border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
                Il tuo utente non risulta associato ai tecnici di questo intervento.
                <button wire:click="associaTecnicoCorrenteTimer"
                        class="ml-2 px-3 py-1 text-xs font-semibold rounded border border-amber-400 bg-white hover:bg-amber-100">
                    Associa il mio utente
                </button>
            </div>
        @else
            <div class="flex items-center gap-2">
                <button
                    class="px-4 py-2 text-sm font-semibold rounded border {{ $timerAttivo ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white hover:bg-gray-50' }}"
                    wire:click="avviaIntervento"
                    @disabled($timerAttivo)>
                    ▶️ Inizia
                </button>
                <button
                    class="px-4 py-2 text-sm font-semibold rounded border {{ $timerAttivo ? 'bg-white hover:bg-gray-50' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }}"
                    wire:click="terminaIntervento"
                    @disabled(!$timerAttivo)>
                    ⏹ Fine
                </button>
            </div>

            @if($timerSessioniEnabled)
                <div class="border border-gray-200 rounded p-2 bg-gray-50">
                    <div class="text-xs font-semibold text-gray-700 mb-2">Sessioni tecnico</div>
                    <div class="space-y-2">
                        @forelse($timerSessioni as $sess)
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end bg-white border rounded p-2">
                                <div>
                                    <label class="text-[11px] text-gray-600">Inizio</label>
                                    <input type="datetime-local"
                                           wire:model.defer="timerSessioniForm.{{ $sess['id'] }}.started_at"
                                           class="w-full border-gray-300 rounded px-2 py-1 text-xs">
                                </div>
                                <div>
                                    <label class="text-[11px] text-gray-600">Fine</label>
                                    <input type="datetime-local"
                                           wire:model.defer="timerSessioniForm.{{ $sess['id'] }}.ended_at"
                                           class="w-full border-gray-300 rounded px-2 py-1 text-xs">
                                </div>
                                <div class="text-xs text-gray-600">
                                    <span class="font-medium">Durata:</span> {{ intdiv((int)$sess['minutes'], 60) }}h {{ (int)$sess['minutes'] % 60 }}m
                                    @if($sess['is_open'])
                                        <span class="ml-1 text-green-700 font-semibold">(attiva)</span>
                                    @endif
                                </div>
                                <div>
                                    <button wire:click="salvaSessioneTimer({{ $sess['id'] }})"
                                            class="px-3 py-2 text-sm rounded border border-gray-300 hover:bg-gray-50 w-full md:w-auto">
                                        Salva orari
                                    </button>
                                </div>
                                <div class="text-[11px] text-gray-500">
                                    ID sessione #{{ $sess['id'] }}
                                </div>
                            </div>
                        @empty
                            <div class="text-xs text-gray-500">Nessuna sessione timer registrata.</div>
                        @endforelse
                    </div>
                </div>
            @endif
        @endif
    </div>

    <datalist id="marca-serbatoio-opzioni">
        @foreach($marcaSuggestions as $marca)
            <option value="{{ $marca }}"></option>
        @endforeach
    </datalist>

    {{-- Switch Vista --}}
    <div class="flex flex-wrap items-center gap-3">
        <label class="font-medium text-sm">Vista:</label>
        <button wire:click="$set('vistaSchede', false)"
            class="text-sm font-semibold px-4 py-2 rounded border {{ $vistaSchede ? 'bg-white text-gray-700 border-gray-300' : 'bg-red-600 text-white border-red-600' }}">
            Tabella
        </button>
        <button wire:click="$set('vistaSchede', true)"
            class="text-sm font-semibold px-4 py-2 rounded border {{ $vistaSchede ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-700 border-gray-300' }}">
            Schede
        </button>
    </div>
    <button wire:click="apriFormNuovoPresidio" class="text-sm font-semibold px-5 py-3 bg-green-600 text-white rounded-lg shadow">
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
                <select wire:model.live="nuovoPresidio.categoria" class="w-full border-gray-300 rounded px-2 py-1">
                    <option value="Estintore">Estintore</option>
                    <option value="Idrante">Idrante</option>
                    <option value="Porta">Porta</option>
                </select>
            </div>

            @php $categoriaNuovo = $nuovoPresidio['categoria'] ?? 'Estintore'; @endphp
            <div wire:key="nuovo-tipo-{{ $categoriaNuovo }}">
                <label class="block text-sm font-medium text-gray-700">
                    @if($categoriaNuovo === 'Idrante')
                        Tipo Idrante
                    @elseif($categoriaNuovo === 'Porta')
                        Tipo Porta
                    @else
                        Tipo Estintore
                    @endif
                </label>
                @if($categoriaNuovo === 'Idrante')
                    <select wire:model.defer="nuovoPresidio.idrante_tipo_id" class="w-full border-gray-300 rounded px-2 py-1">
                        <option value="">Seleziona tipo</option>
                        @foreach($tipiIdranti as $id => $tipo)
                            <option value="{{ $id }}">{{ $tipo }}</option>
                        @endforeach
                    </select>
                @elseif($categoriaNuovo === 'Porta')
                    <select wire:model.defer="nuovoPresidio.porta_tipo_id" class="w-full border-gray-300 rounded px-2 py-1">
                        <option value="">Seleziona tipo</option>
                        @foreach($tipiPorte as $id => $tipo)
                            <option value="{{ $id }}">{{ $tipo }}</option>
                        @endforeach
                    </select>
                @else
                    <select wire:model="nuovoPresidio.tipo_estintore_id" wire:change="aggiornaPreviewNuovo" class="w-full border-gray-300 rounded px-2 py-1">
                        <option value="">Seleziona tipo</option>
                        @foreach($tipiEstintori as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->sigla }} – {{ $tipo->descrizione }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            @if($categoriaNuovo === 'Estintore')

                <div>
                    <label class="block text-sm font-medium text-gray-700">Data Serbatoio</label>
                    <input type="date" wire:model="nuovoPresidio.data_serbatoio" wire:change="aggiornaPreviewNuovo" class="w-full border-gray-300 rounded px-2 py-1">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Marca Serbatoio</label>
                    @php $nuovoMarcaMb = strtoupper(trim((string)($nuovoPresidio['marca_serbatoio'] ?? ''))) === 'MB'; @endphp
                    <div class="flex items-center gap-2">
                        <input type="text" list="marca-serbatoio-opzioni" wire:model.blur="nuovoPresidio.marca_serbatoio" class="w-full border-gray-300 rounded px-2 py-1" placeholder="MB / altro">
                        <button type="button"
                                wire:click.prevent="setMarcaMbNuovo"
                                class="px-2 py-1 text-xs rounded border {{ $nuovoMarcaMb ? 'bg-red-600 text-white border-red-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                            MB
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Ultima Revisione</label>
                    <input type="date" wire:model="nuovoPresidio.data_ultima_revisione" wire:change="aggiornaPreviewNuovo" class="w-full border-gray-300 rounded px-2 py-1">
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
                                <span class="inline-flex items-center px-2 py-1 rounded text-[11px] font-bold shadow-sm"
                                      style="background-color:#000 !important; color:#fff !important;">
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
                                                $anomMap = $anomList->mapWithKeys(fn($row) => [
                                                    (int) $row->id => [
                                                        'etichetta' => (string) $row->etichetta,
                                                        'prezzo' => (float) $this->prezzoAnomaliaPerPresidio($pi->id, (int) $row->id),
                                                    ],
                                                ])->toArray();
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
                                                            @php $prezzoAnomalia = (float)($anomMap[(int)$anomalia->id]['prezzo'] ?? 0); @endphp
                                                            @if($prezzoAnomalia > 0)
                                                                <span class="text-[11px] text-gray-500">(+€ {{ number_format($prezzoAnomalia, 2, ',', '.') }})</span>
                                                            @endif
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
                                                                    $meta = $anomMap[$anomId] ?? null;
                                                                    $label = is_array($meta) ? ($meta['etichetta'] ?? ('Anomalia #'.$anomId)) : ('Anomalia #'.$anomId);
                                                                    $prezzo = is_array($meta) ? (float)($meta['prezzo'] ?? 0) : 0;
                                                                @endphp
                                                                <div class="flex items-center justify-between gap-2 text-xs">
                                                                    <span class="truncate">
                                                                        {{ $label }}
                                                                        @if($prezzo > 0)
                                                                            <span class="text-[11px] text-gray-500">(+€ {{ number_format($prezzo, 2, ',', '.') }})</span>
                                                                        @endif
                                                                    </span>
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
                                                            @php $sostMarcaMb = strtoupper(trim((string)($input[$pi->id]['nuova_marca_serbatoio'] ?? ''))) === 'MB'; @endphp
                                                            <div>
                                                                <label class="block text-xs text-gray-600 mb-1">Tipo estintore sostitutivo</label>
                                                                <select wire:model="input.{{ $pi->id }}.nuovo_tipo_estintore_id" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full border-gray-300 rounded px-2 py-1">
                                                                    <option value="">Tipo Estintore</option>
                                                                    @foreach ($tipiEstintori as $tipo)
                                                                        <option value="{{ $tipo->id }}">{{ $tipo->sigla }} – {{ $tipo->descrizione }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs text-gray-600 mb-1">Data serbatoio sostitutivo</label>
                                                                <input type="date" wire:model="input.{{ $pi->id }}.nuova_data_serbatoio" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full border-gray-300 rounded px-2 py-1">
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs text-gray-600 mb-1">Marca serbatoio sostitutivo</label>
                                                                <div class="flex items-center gap-2">
                                                                    <input type="text" list="marca-serbatoio-opzioni" wire:model.blur="input.{{ $pi->id }}.nuova_marca_serbatoio" class="w-full border-gray-300 rounded px-2 py-1" placeholder="Marca serbatoio (MB / altro)">
                                                                    <button type="button"
                                                                            wire:click.prevent="setMarcaMbSostituzione({{ $pi->id }})"
                                                                            class="px-2 py-1 text-xs rounded border {{ $sostMarcaMb ? 'bg-red-600 text-white border-red-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                                                        MB
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label class="block text-xs text-gray-600 mb-1">Data ultima revisione sostitutiva</label>
                                                                <input type="date" wire:model="input.{{ $pi->id }}.nuova_data_ultima_revisione" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full border-gray-300 rounded px-2 py-1" placeholder="Ultima revisione">
                                                            </div>
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
                                                            <div>
                                                                <label class="block text-xs text-gray-600 mb-1">Tipo idrante sostitutivo</label>
                                                                <select wire:model="input.{{ $pi->id }}.nuovo_idrante_tipo_id" class="w-full border-gray-300 rounded px-2 py-1">
                                                                    <option value="">Tipo Idrante</option>
                                                                    @foreach ($tipiIdranti as $id => $tipo)
                                                                        <option value="{{ $id }}">{{ $tipo }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        @elseif($cat === 'Porta')
                                                            <div>
                                                                <label class="block text-xs text-gray-600 mb-1">Tipo porta sostitutiva</label>
                                                                <select wire:model="input.{{ $pi->id }}.nuovo_porta_tipo_id" class="w-full border-gray-300 rounded px-2 py-1">
                                                                    <option value="">Tipo Porta</option>
                                                                    @foreach ($tipiPorte as $id => $tipo)
                                                                        <option value="{{ $id }}">{{ $tipo }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
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
                        <span class="inline-flex items-center px-2 py-1 rounded text-[11px] font-bold shadow-sm"
                              style="background-color:#000 !important; color:#fff !important;">
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
                            $anomMap = $anomList->mapWithKeys(fn($row) => [
                                                    (int) $row->id => [
                                                        'etichetta' => (string) $row->etichetta,
                                                        'prezzo' => (float) $this->prezzoAnomaliaPerPresidio($pi->id, (int) $row->id),
                                                    ],
                                                ])->toArray();
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
                                        @php $prezzoAnomalia = (float)($anomMap[(int)$anomalia->id]['prezzo'] ?? 0); @endphp
                                        @if($prezzoAnomalia > 0)
                                            <span class="text-[11px] text-gray-500">(+€ {{ number_format($prezzoAnomalia, 2, ',', '.') }})</span>
                                        @endif
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
                                                $meta = $anomMap[$anomId] ?? null;
                                                $label = is_array($meta) ? ($meta['etichetta'] ?? ('Anomalia #'.$anomId)) : ('Anomalia #'.$anomId);
                                                $prezzo = is_array($meta) ? (float)($meta['prezzo'] ?? 0) : 0;
                                            @endphp
                                            <div class="flex items-center justify-between gap-2 text-sm">
                                                <span class="truncate">
                                                    {{ $label }}
                                                    @if($prezzo > 0)
                                                        <span class="text-[11px] text-gray-500">(+€ {{ number_format($prezzo, 2, ',', '.') }})</span>
                                                    @endif
                                                </span>
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
                                        @php $sostMarcaMb = strtoupper(trim((string)($input[$pi->id]['nuova_marca_serbatoio'] ?? ''))) === 'MB'; @endphp
                                        <div>
                                            <label class="text-xs text-gray-600 mb-1 block">Tipo estintore sostitutivo</label>
                                            <select wire:model="input.{{ $pi->id }}.nuovo_tipo_estintore_id" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                                <option value="">Tipo Estintore</option>
                                                @foreach ($tipiEstintori as $tipo)
                                                    <option value="{{ $tipo->id }}">{{ $tipo->sigla }} – {{ $tipo->descrizione }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-600 mb-1 block">Data serbatoio sostitutivo</label>
                                            <input type="date" wire:model="input.{{ $pi->id }}.nuova_data_serbatoio" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="text-xs text-gray-600 mb-1 block">Marca serbatoio sostitutivo</label>
                                            <div class="flex items-center gap-2">
                                                <input type="text" list="marca-serbatoio-opzioni" wire:model.blur="input.{{ $pi->id }}.nuova_marca_serbatoio" class="w-full text-sm border-gray-300 rounded px-2 py-1" placeholder="Marca serbatoio (MB / altro)">
                                                <button type="button"
                                                        wire:click.prevent="setMarcaMbSostituzione({{ $pi->id }})"
                                                        class="px-2 py-1 text-xs rounded border {{ $sostMarcaMb ? 'bg-red-600 text-white border-red-700' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                                    MB
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-600 mb-1 block">Data ultima revisione sostitutiva</label>
                                            <input type="date" wire:model="input.{{ $pi->id }}.nuova_data_ultima_revisione" wire:change="aggiornaPreviewSostituzione({{ $pi->id }})" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                        </div>
                                    @elseif($cat === 'Idrante')
                                        <div>
                                            <label class="text-xs text-gray-600 mb-1 block">Tipo idrante sostitutivo</label>
                                            <select wire:model="input.{{ $pi->id }}.nuovo_idrante_tipo_id" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                                <option value="">Tipo Idrante</option>
                                                @foreach ($tipiIdranti as $id => $tipo)
                                                    <option value="{{ $id }}">{{ $tipo }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @elseif($cat === 'Porta')
                                        <div>
                                            <label class="text-xs text-gray-600 mb-1 block">Tipo porta sostitutiva</label>
                                            <select wire:model="input.{{ $pi->id }}.nuovo_porta_tipo_id" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                                <option value="">Tipo Porta</option>
                                                @foreach ($tipiPorte as $id => $tipo)
                                                    <option value="{{ $id }}">{{ $tipo }}</option>
                                                @endforeach
                                            </select>
                                        </div>
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
        $anomalieRiep = $riepilogoOrdine['anomalie'] ?? [
            'totale' => 0,
            'riparate' => 0,
            'preventivo' => 0,
            'importo_riparate' => 0,
            'importo_preventivo' => 0,
            'importo_totale' => 0,
            'dettaglio' => [],
        ];
        $extraPresidiRiep = $riepilogoOrdine['extra_presidi'] ?? [
            'rows' => [],
            'pending_manual_prices' => [],
            'has_pending_manual_prices' => false,
            'totale_extra' => 0,
        ];
        $riepilogoEconomico = $riepilogoOrdine['riepilogo_economico'] ?? [
            'totale_ordine_business' => 0,
            'extra_presidi' => 0,
            'extra_anomalie_riparate' => 0,
            'totale_aggiornato' => 0,
        ];
        $senzaCodice = $riepilogoOrdine['presidi_senza_codice'] ?? [];
        $ordineTrovato = (bool)($ordinePreventivo['found'] ?? false);
        $chiusuraSoloTotaleSenzaOrdine = !$ordineTrovato && $richiedePagamentoManutentore;
        $importoIncasso = is_numeric($pagamentoImporto ?? null) ? (float)$pagamentoImporto : null;
        $totaleInterventoMostrato = $chiusuraSoloTotaleSenzaOrdine && $importoIncasso !== null
            ? $importoIncasso
            : (float)($riepilogoEconomico['totale_aggiornato'] ?? 0);
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
            <div class="text-[11px] text-gray-500 mt-1">Prezzi da ordine Business; per codici extra non presenti in ordine inserire prezzo manuale.</div>
        </div>

        @if(!$chiusuraSoloTotaleSenzaOrdine)
            <div>
                <div class="text-sm font-semibold mb-1">Extra presidi da aggiungere all'ordine</div>
                <div class="overflow-auto border rounded">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-100 text-gray-600">
                            <tr>
                                <th class="p-2 text-left">Cod. Art.</th>
                                <th class="p-2 text-left">Descrizione</th>
                                <th class="p-2 text-right">Q.tà extra</th>
                                <th class="p-2 text-right">Prezzo unit.</th>
                                <th class="p-2 text-right">Importo extra</th>
                                <th class="p-2 text-left">Fonte prezzo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(($extraPresidiRiep['rows'] ?? []) as $row)
                                @php
                                    $price = $row['prezzo_unitario'] ?? null;
                                    $manualRequired = (bool) ($row['manual_required'] ?? false);
                                    $isPendingManual = $manualRequired && $price === null;
                                    $priceFormatted = $price !== null ? number_format((float) $price, 2, ',', '.') : '';
                                    $source = (string) ($row['prezzo_source'] ?? '');
                                @endphp
                                <tr class="border-t {{ $isPendingManual ? 'bg-amber-50' : '' }}">
                                    <td class="p-2 font-mono">{{ $row['codice_articolo'] }}</td>
                                    <td class="p-2">{{ $row['descrizione'] ?: '—' }}</td>
                                    <td class="p-2 text-right">{{ number_format((float)($row['quantita_extra'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="p-2 text-right">
                                        @if($manualRequired)
                                            <div class="inline-flex items-center gap-2">
                                                <input type="text"
                                                    value="{{ $priceFormatted }}"
                                                    wire:change="setPrezzoExtra({{ \Illuminate\Support\Js::from($row['codice_articolo']) }}, $event.target.value)"
                                                    class="w-24 border {{ $isPendingManual ? 'border-amber-500 bg-amber-100' : 'border-gray-300' }} rounded px-2 py-1 text-right"
                                                    placeholder="0,00">
                                            </div>
                                        @elseif($price !== null)
                                            € {{ $priceFormatted }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="p-2 text-right">
                                        @if(($row['importo_extra'] ?? null) !== null)
                                            € {{ number_format((float) $row['importo_extra'], 2, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="p-2">
                                        @if($source === 'ordine')
                                            Ordine Business
                                        @elseif($source === 'manuale')
                                            Inserito tecnico
                                        @else
                                            Prezzo richiesto
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="p-2 text-gray-500">Nessun presidio extra rispetto all'ordine.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(($extraPresidiRiep['has_pending_manual_prices'] ?? false) === true)
                    <div class="mt-2 rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800">
                        Inserisci i prezzi manuali dei codici articolo evidenziati prima della chiusura intervento.
                    </div>
                @endif
            </div>
        @endif

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
                @if($chiusuraSoloTotaleSenzaOrdine)
                    <div class="mt-1 font-semibold">
                        Modalità ALLA CONSEGNA: puoi chiudere l’intervento inserendo solo il totale incassato.
                    </div>
                @endif
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

        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
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
            <div class="rounded border border-green-200 bg-green-50 p-2">
                <div class="text-xs text-green-700">Extra anomalie riparate</div>
                <div class="text-lg font-semibold text-green-700">€ {{ number_format((float)($riepilogoEconomico['extra_anomalie_riparate'] ?? 0), 2, ',', '.') }}</div>
            </div>
            <div class="rounded border border-gray-300 bg-gray-50 p-2">
                <div class="text-xs text-gray-700">Extra presidi</div>
                <div class="text-lg font-semibold text-gray-800">€ {{ number_format((float)($riepilogoEconomico['extra_presidi'] ?? 0), 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="rounded border border-gray-300 bg-gray-50 p-3">
            <div class="text-xs text-gray-700">Totale intervento aggiornato</div>
            <div class="text-xl font-semibold text-gray-800">
                € {{ number_format((float)$totaleInterventoMostrato, 2, ',', '.') }}
            </div>
            @if($chiusuraSoloTotaleSenzaOrdine)
                <div class="mt-1 text-[11px] text-gray-600">
                    Totale manuale incassato (assenza ordine Business).
                </div>
            @else
                <div class="mt-1 text-[11px] text-gray-600">
                    Ordine Business € {{ number_format((float)($riepilogoEconomico['totale_ordine_business'] ?? 0), 2, ',', '.') }}
                    + Extra presidi € {{ number_format((float)($riepilogoEconomico['extra_presidi'] ?? 0), 2, ',', '.') }}
                    + Anomalie riparate € {{ number_format((float)($riepilogoEconomico['extra_anomalie_riparate'] ?? 0), 2, ',', '.') }}
                </div>
            @endif
        </div>

        <div class="bg-white border-2 border-red-200 rounded p-3 shadow-sm text-sm text-gray-800">
            <div class="flex items-center justify-between gap-2">
                <div class="font-extrabold tracking-wide">FORMA PAGAMENTO BUSINESS</div>
                <button wire:click="ricaricaFormaPagamentoBusiness"
                        type="button"
                        class="px-2 py-1 rounded border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-100">
                    Ricarica da Business
                </button>
            </div>
            @if($richiedePagamentoManutentore)
                <div class="mt-1 text-sm font-bold text-red-700">
                    ALLA CONSEGNA (cod. 40): incasso da manutentore obbligatorio
                </div>
                @if($chiusuraSoloTotaleSenzaOrdine)
                    <div class="mt-2 text-xs text-gray-700">
                        Nessun ordine trovato: per la chiusura è richiesto solo il totale incassato.
                    </div>
                    <div class="mt-3">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Totale incassato (€)</label>
                        <input type="number"
                            step="0.01"
                            min="0"
                            wire:model.blur="pagamentoImporto"
                            placeholder="0,00"
                            class="w-full md:w-64 border-gray-300 rounded px-2 py-2 text-sm">
                    </div>
                @else
                    <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Metodo incasso</label>
                            <select wire:model.live="pagamentoMetodo" class="w-full border-gray-300 rounded px-2 py-2 text-sm">
                                <option value="">Seleziona metodo</option>
                                <option value="POS">POS</option>
                                <option value="ASSEGNO">ASSEGNO</option>
                                <option value="CONTANTI">CONTANTI</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Importo incassato (€)</label>
                            <input type="number"
                                step="0.01"
                                min="0"
                                wire:model.blur="pagamentoImporto"
                                placeholder="0,00"
                                class="w-full border-gray-300 rounded px-2 py-2 text-sm">
                        </div>
                    </div>
                @endif
            @else
                <div class="mt-1 text-sm font-bold text-gray-900">
                    {{ $formaPagamentoDescrizione ?: 'Non disponibile' }}
                </div>
            @endif
        </div>

        @if(!empty($anomalieRiep['dettaglio']))
            <div class="overflow-auto border rounded">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-100 text-gray-600">
                        <tr>
                            <th class="p-2 text-left">Anomalia</th>
                            <th class="p-2 text-right">Prezzo</th>
                            <th class="p-2 text-right">Totale</th>
                            <th class="p-2 text-right">Riparate</th>
                            <th class="p-2 text-right">Preventivo</th>
                            <th class="p-2 text-right">Importo riparate</th>
                            <th class="p-2 text-right">Importo preventivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($anomalieRiep['dettaglio'] as $row)
                            <tr class="border-t">
                                <td class="p-2">{{ $row['etichetta'] }}</td>
                                <td class="p-2 text-right">€ {{ number_format((float)($row['prezzo'] ?? 0), 2, ',', '.') }}</td>
                                <td class="p-2 text-right">{{ $row['totale'] }}</td>
                                <td class="p-2 text-right text-green-700">{{ $row['riparate'] }}</td>
                                <td class="p-2 text-right text-amber-700">{{ $row['preventivo'] }}</td>
                                <td class="p-2 text-right text-green-700">€ {{ number_format((float)($row['importo_riparate'] ?? 0), 2, ',', '.') }}</td>
                                <td class="p-2 text-right text-amber-700">€ {{ number_format((float)($row['importo_preventivo'] ?? 0), 2, ',', '.') }}</td>
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
        <input type="number"
               wire:model="durataEffettiva"
               @readonly($timerSessioniEnabled)
               class="input input-sm input-bordered w-full max-w-xs {{ $timerSessioniEnabled ? 'bg-gray-100 text-gray-700 cursor-not-allowed' : '' }}">
        @if($timerSessioniEnabled)
            <div class="text-xs text-gray-500 mt-1">
                Valore calcolato automaticamente dalla somma delle sessioni timer dei tecnici.
            </div>
        @endif
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
            <a href="{{ route('rapportino.pdf', ['id' => $intervento->id, 'kind' => 'cliente', 'download' => 1]) }}" target="_blank"
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
        Livewire.on('intervento-completato', (rawPayload) => {
            const payload = Array.isArray(rawPayload) ? (rawPayload[0] ?? {}) : (rawPayload ?? {});
            const pdfUrl = payload.pdfUrl ?? null;
            const pdfDownloadUrl = payload.pdfDownloadUrl ?? null;

            // Metodo cross-platform (Android / iPad / PC):
            // 1) prova apertura in nuova scheda
            // 2) se bloccata dal browser, apri nella stessa scheda
            if (pdfUrl) {
                const w = window.open(pdfUrl, '_blank', 'noopener');
                if (!w) {
                    window.location.assign(pdfUrl);
                }
                return;
            }

            if (pdfDownloadUrl) {
                window.location.assign(pdfDownloadUrl);
                return;
            }

            alert('Intervento completato, ma non è stato possibile aprire il rapportino PDF.');
        });
    }

    initOfflineSync();
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
    const root = document.getElementById('evadi-intervento-root');
    const componentId = root?.getAttribute('wire:id');
    const component = componentId && window.Livewire ? window.Livewire.find(componentId) : null;

    if (component) {
        component.call('salvaFirmaCliente', base64);
        return;
    }

    Livewire.dispatch('firmaClienteAcquisita', { base64: base64 });
}

function initOfflineSync() {
    if (window.__evadiOfflineSyncInit) return;
    window.__evadiOfflineSyncInit = true;

    const root = document.getElementById('evadi-intervento-root');
    if (!root) return;

    const banner = document.getElementById('offline-sync-banner');
    const interventoId = root.dataset.interventoId;
    const storageKey = `evadi-intervento-offline-${interventoId}`;
    let debounceId = null;
    let syncing = false;

    const setBanner = (mode, message, autoHideMs = 0) => {
        if (!banner) return;

        const map = {
            offline: 'bg-amber-50 border-amber-300 text-amber-800',
            syncing: 'bg-blue-50 border-blue-300 text-blue-800',
            success: 'bg-green-50 border-green-300 text-green-800',
            error: 'bg-red-50 border-red-300 text-red-800',
        };

        banner.className = `rounded border px-3 py-2 text-sm ${map[mode] ?? map.offline}`;
        banner.textContent = message;
        banner.classList.remove('hidden');

        if (autoHideMs > 0) {
            setTimeout(() => {
                if (banner.textContent === message && navigator.onLine) {
                    banner.classList.add('hidden');
                }
            }, autoHideMs);
        }
    };

    const getWireModel = (el) => {
        for (const attr of Array.from(el.attributes || [])) {
            if (attr.name.startsWith('wire:model')) {
                return attr.value;
            }
        }
        return null;
    };

    const setByPath = (obj, path, value) => {
        const keys = path.split('.');
        let cur = obj;
        for (let i = 0; i < keys.length; i++) {
            const k = keys[i];
            if (i === keys.length - 1) {
                cur[k] = value;
                return;
            }
            if (!Object.prototype.hasOwnProperty.call(cur, k) || typeof cur[k] !== 'object' || cur[k] === null) {
                cur[k] = {};
            }
            cur = cur[k];
        }
    };

    const getElementValue = (el) => {
        if (el.type === 'checkbox') return !!el.checked;
        if (el.type === 'number') {
            const raw = String(el.value ?? '').trim();
            return raw === '' ? null : Number(raw);
        }
        return el.value ?? null;
    };

    const isEsitoModel = (model) => /^input\.\d+\.esito$/.test(model);

    const collectDraftPayload = () => {
        const payload = { input: {} };
        const controls = root.querySelectorAll('input, select, textarea');

        controls.forEach((el) => {
            const model = getWireModel(el);
            if (!model) return;

            if (model === 'durataEffettiva') {
                payload.durataEffettiva = getElementValue(el);
                return;
            }
            if (model === 'pagamentoMetodo') {
                payload.pagamentoMetodo = getElementValue(el);
                return;
            }
            if (model === 'pagamentoImporto') {
                payload.pagamentoImporto = getElementValue(el);
                return;
            }
            if (model === 'noteInterventoGenerali') {
                payload.noteInterventoGenerali = getElementValue(el);
                return;
            }
            if (model === 'noteClienteAnagrafica') {
                payload.noteClienteAnagrafica = getElementValue(el);
                return;
            }

            if (!model.startsWith('input.')) return;

            setByPath(payload, model, getElementValue(el));
        });

        const anomalyMap = {};
        const ripMap = {};

        root.querySelectorAll('input[type="checkbox"]').forEach((el) => {
            const expr = el.getAttribute('wire:change') || '';
            let m = expr.match(/toggleAnomalia\((\d+),\s*(\d+),/);
            if (m) {
                const piId = m[1];
                const anomId = Number(m[2]);
                if (!anomalyMap[piId]) anomalyMap[piId] = new Set();
                if (el.checked) anomalyMap[piId].add(anomId);
                return;
            }

            m = expr.match(/toggleAnomaliaRiparata\((\d+),\s*(\d+),/);
            if (m) {
                const piId = m[1];
                const anomId = m[2];
                if (!ripMap[piId]) ripMap[piId] = {};
                ripMap[piId][anomId] = !!el.checked;
            }
        });

        Object.keys(anomalyMap).forEach((piId) => {
            if (!payload.input[piId]) payload.input[piId] = {};
            payload.input[piId].anomalie = Array.from(anomalyMap[piId]).sort((a, b) => a - b);
        });

        Object.keys(ripMap).forEach((piId) => {
            if (!payload.input[piId]) payload.input[piId] = {};
            payload.input[piId].anomalie_riparate = ripMap[piId];
        });

        return payload;
    };

    const readDraft = () => {
        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return null;
            const decoded = JSON.parse(raw);
            if (!decoded || typeof decoded !== 'object') return null;
            return decoded;
        } catch {
            return null;
        }
    };

    const mergePayload = (basePayload, partialPayload) => {
        const baseInput = (basePayload && typeof basePayload === 'object' && basePayload.input && typeof basePayload.input === 'object')
            ? basePayload.input
            : {};
        const partialInput = (partialPayload && typeof partialPayload === 'object' && partialPayload.input && typeof partialPayload.input === 'object')
            ? partialPayload.input
            : {};

        const merged = { input: { ...baseInput } };
        Object.keys(partialInput).forEach((piId) => {
            merged.input[piId] = {
                ...(merged.input[piId] || {}),
                ...(partialInput[piId] || {}),
            };
        });

        if (basePayload && Object.prototype.hasOwnProperty.call(basePayload, 'durataEffettiva')) {
            merged.durataEffettiva = basePayload.durataEffettiva;
        }
        if (partialPayload && Object.prototype.hasOwnProperty.call(partialPayload, 'durataEffettiva')) {
            merged.durataEffettiva = partialPayload.durataEffettiva;
        }

        return merged;
    };

    const writeDraft = (payload, mergeWithExisting = true) => {
        const current = readDraft();
        const finalPayload = mergeWithExisting && current?.payload
            ? mergePayload(current.payload, payload)
            : payload;
        const draft = {
            pendingSync: true,
            savedAt: new Date().toISOString(),
            payload: finalPayload,
        };
        try {
            localStorage.setItem(storageKey, JSON.stringify(draft));
        } catch (e) {
            setBanner('error', 'Memoria locale piena: impossibile salvare la bozza offline.');
        }
    };

    const getComponent = () => {
        const componentId = root.getAttribute('wire:id');
        if (!componentId || !window.Livewire) return null;
        return window.Livewire.find(componentId);
    };

    const flushDraft = async () => {
        if (!navigator.onLine || syncing) return;

        const draft = readDraft();
        if (!draft || !draft.pendingSync || !draft.payload) return;

        const component = getComponent();
        if (!component) return;

        syncing = true;
        setBanner('syncing', 'Internet ripristinato: sincronizzazione modifiche offline in corso...');

        try {
            await component.call('syncOfflineDraft', draft.payload);
            localStorage.removeItem(storageKey);
            setBanner('success', 'Modifiche offline sincronizzate.', 4000);
        } catch (e) {
            setBanner('error', 'Sincronizzazione non riuscita. Riprovo automaticamente.');
        } finally {
            syncing = false;
        }
    };

    const queueDraftIfOffline = () => {
        if (navigator.onLine) return;
        const payload = collectDraftPayload();
        writeDraft(payload);
        setBanner('offline', 'Sei offline: modifiche salvate in locale. Verranno inviate appena torna internet.');
    };

    const saveEsitoImmediatelyIfOffline = (event) => {
        if (navigator.onLine) return;
        const el = event.target;
        if (!(el instanceof HTMLSelectElement)) return;

        const model = getWireModel(el);
        if (!model || !isEsitoModel(model)) return;

        const payload = { input: {} };
        setByPath(payload, model, getElementValue(el));
        writeDraft(payload, true);
        setBanner('offline', 'Sei offline: stato presidio salvato in locale.');
    };

    const scheduleOfflineCapture = () => {
        clearTimeout(debounceId);
        debounceId = setTimeout(queueDraftIfOffline, 250);
    };

    root.addEventListener('input', scheduleOfflineCapture, true);
    root.addEventListener('change', scheduleOfflineCapture, true);
    root.addEventListener('change', saveEsitoImmediatelyIfOffline, true);
    root.addEventListener('click', (event) => {
        const el = event.target.closest('[wire\\:click]');
        if (!el || navigator.onLine) return;

        const action = (el.getAttribute('wire:click') || '').trim();
        if (action !== 'salva') return;

        const payload = collectDraftPayload();
        writeDraft(payload);
        setBanner('offline', 'Sei offline: bozza salvata in locale. Il completamento intervento verrà possibile quando torna internet.');
        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);

    window.addEventListener('offline', () => {
        setBanner('offline', 'Connessione assente: da ora le modifiche vengono salvate in locale.');
    });

    window.addEventListener('online', () => {
        flushDraft();
    });

    const currentDraft = readDraft();
    if (!navigator.onLine) {
        setBanner('offline', 'Connessione assente: da ora le modifiche vengono salvate in locale.');
    } else if (currentDraft?.pendingSync) {
        flushDraft();
    }

    setInterval(flushDraft, 15000);
}
</script>
