<div class="p-6 max-w-6xl mx-auto space-y-6">
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold text-red-600">Import massivo presidi</h2>
            <a href="{{ route('clienti.index') }}"
               class="text-sm text-gray-600 hover:text-red-600">
                <i class="fa fa-arrow-left mr-1"></i> Torna alla lista clienti
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700">Seleziona file .docx (multipli)</label>
                <input type="file" multiple
                       wire:model="files"
                       class="mt-1 w-full border border-gray-300 rounded p-2 text-sm">
                @error('files.*') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div class="text-xs text-gray-600">
                I file devono contenere il codice cliente (ultime 4 cifre del codice esterno).
                Esempio: <code>2378 Nome cliente.docx</code>
            </div>
        </div>
    </div>

    @if($fileErrors)
        <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded">
            <div class="font-semibold mb-1">Problemi riscontrati:</div>
            <ul class="list-disc pl-5 text-sm">
                @foreach($fileErrors as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($fileRows)
        <div class="bg-white shadow rounded-lg p-4">
            <h3 class="text-md font-semibold text-gray-700 mb-2">File rilevati</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-2 py-1">File</th>
                            <th class="px-2 py-1">Codice</th>
                            <th class="px-2 py-1">Cliente</th>
                            <th class="px-2 py-1">Dati cliente</th>
                            <th class="px-2 py-1">Sede</th>
                            <th class="px-2 py-1">Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fileRows as $r)
                            <tr class="border-b">
                                <td class="px-2 py-1">{{ $r['name'] }}</td>
                                <td class="px-2 py-1">{{ $r['code4'] ?? '—' }}</td>
                                <td class="px-2 py-1">{{ $r['cliente_nome'] ?? '—' }}</td>
                                <td class="px-2 py-1 text-xs">
                                    @php
                                        $cid = $r['cliente_id'] ?? null;
                                        $cdata = $cid ? ($clientiInput[$cid] ?? null) : null;
                                        $mesiOk = !empty($cdata['mesi_visita'] ?? []);
                                        $tempiOk = !empty($cdata['minuti_intervento_mese1'] ?? null) && !empty($cdata['minuti_intervento_mese2'] ?? null);
                                        $zonaOk = !empty($cdata['zona'] ?? null);
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded {{ $mesiOk ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">Mesi</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded {{ $tempiOk ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">Tempi</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded {{ $zonaOk ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">Zona</span>
                                </td>
                                <td class="px-2 py-1">
                                    @if(($r['status'] ?? '') === 'ok')
                                        <select wire:model.defer="fileRows.{{ $r['index'] }}.sede_id"
                                                class="input input-bordered text-xs">
                                            <option value="principal">Sede principale (nessuna sede)</option>
                                            @if(!empty($r['sedi']))
                                                @foreach($r['sedi'] as $s)
                                                    <option value="{{ $s['id'] }}">{{ $s['nome'] }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-2 py-1">
                                    @if($r['status'] === 'ok')
                                        <span class="text-green-700">OK</span>
                                    @else
                                        <span class="text-red-600">Da correggere</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($clientiInput)
        <div class="bg-white shadow rounded-lg p-4 space-y-4">
            <h3 class="text-md font-semibold text-gray-700">Dati mancanti clienti</h3>
            @foreach($clientiInput as $id => $c)
                @php
                    $mesi = $c['mesi_visita'] ?? [];
                @endphp
                <div class="border rounded p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-semibold text-gray-800">{{ $c['nome'] }}</div>
                        <div class="text-xs text-gray-500">ID: {{ $id }}</div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Mesi controllo</label>
                            <div class="grid grid-cols-6 gap-2 mt-1 text-xs">
                                @for($m = 1; $m <= 12; $m++)
                                    <label class="inline-flex items-center">
                                        <input type="checkbox"
                                               wire:model.defer="clientiInput.{{ $id }}.mesi_visita.{{ $m }}"
                                               class="mr-1">
                                        {{ Date::create()->month($m)->format('M') }}
                                    </label>
                                @endfor
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Zona</label>
                                <input type="text"
                                       wire:model.defer="clientiInput.{{ $id }}.zona"
                                       class="input input-bordered w-full mt-1">
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-600">Base</label>
                                    <input type="number" min="0" max="1440"
                                           wire:model.defer="clientiInput.{{ $id }}.minuti_intervento"
                                           class="input input-bordered w-full">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600">Mese 1</label>
                                    <input type="number" min="0" max="1440"
                                           wire:model.defer="clientiInput.{{ $id }}.minuti_intervento_mese1"
                                           class="input input-bordered w-full">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600">Mese 2</label>
                                    <input type="number" min="0" max="1440"
                                           wire:model.defer="clientiInput.{{ $id }}.minuti_intervento_mese2"
                                           class="input input-bordered w-full">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="flex items-center gap-3">
                <button wire:click="saveClientiMissing" class="btn btn-primary btn-sm">💾 Salva dati clienti</button>
                <button wire:click="confermaImportMassivo"
                        class="btn btn-success btn-sm"
                        @if(!$this->canImport()) disabled @endif>
                    🚀 Avvia import massivo
                </button>
            </div>
        </div>
    @endif
</div>
