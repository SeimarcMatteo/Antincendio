<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    <h1 class="text-2xl font-bold text-red-700">
        🛠 Evadi Intervento: <span class="text-gray-800">{{ $intervento->cliente->nome }}</span>
    </h1>

    {{-- Switch Vista --}}
    <div class="flex items-center gap-3">
        <label class="font-medium text-sm">Vista:</label>
        <button wire:click="$set('vistaSchede', false)"
            class="text-sm px-3 py-1 rounded border {{ $vistaSchede ? 'bg-white text-gray-700 border-gray-300' : 'bg-red-600 text-white border-red-600' }}">
            Tabella
        </button>
        <button wire:click="$set('vistaSchede', true)"
            class="text-sm px-3 py-1 rounded border {{ $vistaSchede ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-700 border-gray-300' }}">
            Schede
        </button>
    </div>
    <button wire:click="apriFormNuovoPresidio" class="text-sm px-3 py-1 bg-green-600 text-white rounded shadow">
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
                <label class="block text-sm font-medium text-gray-700">Tipo Estintore</label>
                <select wire:model.defer="nuovoPresidio.tipo_estintore_id" class="w-full border-gray-300 rounded px-2 py-1">
                    <option value="">Seleziona tipo</option>
                    @foreach($tipiEstintori as $tipo)
                        <option value="{{ $tipo->id }}">{{ $tipo->sigla }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Data Serbatoio</label>
                <input type="date" wire:model.defer="nuovoPresidio.data_serbatoio" class="w-full border-gray-300 rounded px-2 py-1">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Categoria</label>
                <select wire:model.defer="nuovoPresidio.categoria" class="w-full border-gray-300 rounded px-2 py-1">
                    <option value="Estintore">Estintore</option>
                    <option value="Idrante">Idrante</option>
                    <option value="Porta">Porta</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Note</label>
                <textarea wire:model.defer="nuovoPresidio.note" rows="2" class="w-full border-gray-300 rounded px-2 py-1"></textarea>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm mt-1">
                    <input type="checkbox" wire:model.defer="nuovoPresidio.usa_ritiro" class="border-gray-300">
                    Usa presidio da ritiri disponibili
                </label>
            </div>
    </div>
    <div class="flex justify-end mt-2">
        <button wire:click="salvaNuovoPresidio" class="px-4 py-2 bg-blue-600 text-white rounded shadow text-sm hover:bg-blue-700">
            💾 Salva nuovo presidio
        </button>
    </div>
    @endif
    {{-- VISTA TABELLARE --}}
    @if (!$vistaSchede)
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-gray-800 border bg-white shadow rounded">
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
                @foreach ($intervento->presidiIntervento as $pi)
                    @php $d = $input[$pi->id]; @endphp
                    <tr class="{{ empty($d['esito']) ? 'bg-red-50' : '' }}">
                        <td class="p-2 font-mono">{{ $pi->presidio->progressivo }}</td>
                        <td class="p-2"><input type="text" wire:model="input.{{ $pi->id }}.ubicazione" class="w-full border-gray-300 rounded px-2 py-1"></td>
                        <td class="p-2">
                            <select wire:model="input.{{ $pi->id }}.esito" class="w-full border-gray-300 rounded px-2 py-1">
                                <option value="verificato">✅ Verificato</option>
                                <option value="non_verificato">❌ Non Verificato</option>
                                <option value="anomalie">⚠️ Anomalie</option>
                                <option value="sostituito">🔁 Sostituito</option>
                            </select>
                            @if($pi->usa_ritiro)
                                <div class="text-xs mt-1 text-blue-500 font-semibold">Presidio ritirato utilizzato</div>
                            @endif
                        </td>
                        <td class="p-2">
                            <select multiple wire:model="input.{{ $pi->id }}.anomalie" class="w-full border-gray-300 rounded px-2 py-1">
                                @foreach(($anomalie[$pi->presidio->categoria] ?? []) as $anomalia)
                                    <option value="{{ $anomalia->id }}">{{ $anomalia->etichetta }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="p-2">
                            @if ($d['sostituito_con'] ?? false)
                                <div class="text-green-500 font-semibold">Sostituito con presidio: {{ $d['sostituito_con']->id }}</div>
                            @else
                                <button wire:click="toggleSostituzione({{ $pi->id }})" class="bg-blue-500 text-white px-2 py-1 rounded">
                                    {{ $d['sostituzione'] ? 'Annulla' : 'Sostituisci' }}
                                </button>
                                @if ($d['sostituzione'])
                                    <div class="mt-2 space-y-1">
                                        <select wire:model="input.{{ $pi->id }}.nuovo_tipo_estintore_id" class="w-full border-gray-300 rounded px-2 py-1">
                                            <option value="">Tipo Estintore</option>
                                            @foreach ($tipiEstintori as $tipo)
                                                <option value="{{ $tipo->id }}">{{ $tipo->sigla }}</option>
                                            @endforeach
                                        </select>
                                        <input type="date" wire:model="input.{{ $pi->id }}.nuova_data_serbatoio" class="w-full border-gray-300 rounded px-2 py-1">
                                        <label class="flex gap-1 items-center text-sm">
                                            <input type="checkbox" wire:model="input.{{ $pi->id }}.usa_ritiro" class="border-gray-300">
                                            Usa presidio da ritiri
                                        </label>
                                        <button wire:click="sostituisciPresidio({{ $pi->id }})" class="bg-blue-600 text-white px-3 py-1 rounded text-sm mt-2">
                                            Conferma Sostituzione
                                        </button>
                                    </div>
                                @endif
                            @endif
                        </td>
                        <td class="p-2">
                            <textarea wire:model="input.{{ $pi->id }}.note" class="w-full border-gray-300 rounded px-2 py-1" rows="2"></textarea>
                        </td>
                        <td class="p-2">{{ $d['tipo_estintore_sigla'] }}</td>
                        <td class="p-2">
                            @if($d['deve_ritirare'])
                                <span class="text-red-600 font-semibold">Sì</span>
                            @else
                                <span class="text-gray-500">—</span>
                            @endif
                        </td>
                        <td class="p-2 text-center">
                            <button wire:click="rimuoviPresidioIntervento({{ $pi->id }})"
                                class="text-red-600 hover:text-red-800 text-sm"
                                onclick="return confirm('Rimuovere questo presidio dall\'intervento?')">
                                ❌
                            </button>
                        </td>

                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    {{-- Vista a Schede --}}
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($intervento->presidiIntervento as $pi)
            @php $d = $input[$pi->id]; @endphp
            @php $nonVerificato = ($d['esito'] ?? 'non_verificato') === 'non_verificato'; @endphp
        <div class="border rounded shadow p-4 {{ $nonVerificato ? 'border-red-500 bg-red-50' : 'bg-white' }}">
            <h3 class="text-md font-semibold mb-3 text-gray-800">🧯 Presidio #{{ $pi->presidio->progressivo }} ({{ $pi->presidio->categoria }})</h3>

                <div class="space-y-2">
                    <div>
                        <label class="text-sm">Ubicazione</label>
                        <input type="text" wire:model="input.{{ $pi->id }}.ubicazione" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                    </div>
                    <div class="text-sm text-gray-700">
                        <strong>Tipo estintore:</strong> {{ $d['tipo_estintore_sigla'] }}
                    </div>

                    @if($d['deve_ritirare'])
                        <div class="text-sm font-semibold text-red-600">
                            ⚠️ Da ritirare questo mese (revisione, collaudo o fine vita)
                        </div>
                    @endif

                    <div>
                        <label class="text-sm">Esito</label>
                        <select wire:model="input.{{ $pi->id }}.esito" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                            <option value="verificato">✅ Verificato</option>
                            <option value="non_verificato">❌ Non Verificato</option>
                            <option value="anomalie">⚠️ Anomalie</option>
                            <option value="sostituito">🔁 Sostituito</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-sm">Anomalie</label>
                        <select multiple wire:model="input.{{ $pi->id }}.anomalie" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                            @foreach(($anomalie[$pi->presidio->categoria] ?? []) as $anomalia)
                                <option value="{{ $anomalia->id }}">{{ $anomalia->etichetta }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm">Note</label>
                        <textarea wire:model="input.{{ $pi->id }}.note" rows="2" class="w-full text-sm border-gray-300 rounded px-2 py-1"></textarea>
                    </div>

                    {{-- Sostituzione --}}
                    <div>
                        @if ($d['sostituito_con'] ?? false)
                            <div class="text-green-600 text-sm font-semibold flex items-center gap-2">
                                <i class="fa fa-check-circle text-green-500"></i>
                                Sostituito con presidio: {{ $d['sostituito_con']->id }}

                                @if ($d['usa_ritiro'] ?? false)
                                    <span class="ml-2 px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded">
                                        Presidio ritirato usato
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
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-3">
                                    <select wire:model="input.{{ $pi->id }}.nuovo_tipo_estintore_id" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                        <option value="">Tipo Estintore</option>
                                        @foreach ($tipiEstintori as $tipo)
                                            <option value="{{ $tipo->id }}">{{ $tipo->sigla }}</option>
                                        @endforeach
                                    </select>

                                    <input type="date" wire:model="input.{{ $pi->id }}.nuova_data_serbatoio" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                </div>

                                <div class="mt-2">
                                    <label class="text-sm">Stato del presidio ritirato</label>
                                    <select wire:model="input.{{ $pi->id }}.stato_presidio_ritirato" class="w-full text-sm border-gray-300 rounded px-2 py-1">
                                        <option value="Disponibile">Disponibile</option>
                                        <option value="Rottamato">Rottamato</option>
                                        <option value="Da Revisionare">Da Revisionare</option>
                                    </select>
                                </div>

                                <div class="mt-2">
                                    <label class="text-sm flex items-center gap-2">
                                        <input type="checkbox" wire:model="input.{{ $pi->id }}.usa_ritiro" class="border-gray-300">
                                        Usa presidio da ritiri
                                    </label>
                                </div>

                                <div class="mt-2">
                                    <button wire:click="sostituisciPresidio({{ $pi->id }})" class="bg-green-600 text-white py-2 px-4 rounded text-sm">
                                        ✅ Conferma Sostituzione
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
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




    <div class="text-end">
        <button wire:click="salva" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
        💾 Salva e completa intervento
        </button>
        <div class="mt-6 flex justify-end">
            <a href="{{ route('rapportino.pdf', $intervento->id) }}" target="_blank"
            class="btn btn-outline btn-success">
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
