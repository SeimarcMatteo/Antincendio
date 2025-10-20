<div class="space-y-4">
    {{-- ============ UPLOAD ============ --}}
    <form wire:submit.prevent="importa"
          class="flex flex-col sm:flex-row items-center gap-4">
        <input type="file" wire:model="file" accept=".docx"
               class="form-input border-gray-300 rounded w-full sm:w-auto" />
        <button type="submit"
                class="bg-red-600 text-white px-4 py-2 rounded shadow hover:bg-red-700 transition">
            <i class="fa fa-upload mr-1"></i> Carica Documento
        </button>
        @error('file') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
    </form>

    {{-- ============ ANTEPRIMA LETTA ============ --}}
    @if($anteprima)
        <div class="mt-4">
            <h3 class="text-lg font-semibold mb-2">Anteprima presidi rilevati</h3>

            <div class="overflow-x-auto border rounded shadow-sm">
                <table class="min-w-full text-sm text-left text-gray-800">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-2 py-1 w-20">Prog.</th>
                            <th class="px-2 py-1 w-32">Categoria</th>
                            <th class="px-2 py-1">Ubicazione</th>
                            <th class="px-2 py-1">Tipo</th>
                            <th class="px-2 py-1 w-40">Serbatoio&nbsp;<span class="text-red-600">*</span></th>
                            <th class="px-2 py-1 w-40">Revisione</th>
                            <th class="px-2 py-1 w-40">Collaudo</th>
                            <th class="px-2 py-1 w-32">Anomalie</th>

                            <th class="px-2 py-1 w-32">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($anteprima as $r => $row)
                            @php($missing = !$row['data_serbatoio'])
                            <tr wire:key="preview-{{ $r }}"
                                class="{{ $loop->even ? 'bg-gray-50' : '' }} {{ $missing ? 'bg-yellow-100' : '' }}">

                                {{-- progressivo --}}
                                <td class="px-2 py-1">
                                    <input type="number" min="1"
                                           wire:model.defer="anteprima.{{ $r }}.progressivo"
                                           class="form-input w-full text-xs">
                                </td>

                                {{-- categoria --}}
                                <td class="px-2 py-1">
                                    <select wire:model.defer="anteprima.{{ $r }}.categoria"
                                            class="form-select w-full text-xs">
                                        <option>Estintore</option><option>Idrante</option><option>Porta</option>
                                    </select>
                                </td>

                                {{-- ubicazione --}}
                                <td class="px-2 py-1">
                                    <input wire:model.defer="anteprima.{{ $r }}.ubicazione"
                                           class="form-input w-full text-xs">
                                </td>

                                {{-- tipo estintore --}}
                                <td class="px-2 py-1">
                                    <select wire:model.defer="anteprima.{{ $r }}.tipo_estintore_id"
                                            class="form-select w-full text-xs {{ !$row['tipo_estintore_id'] ? 'bg-yellow-100' : '' }}">
                                        <option value=""> -- scegli -- </option>
                                        @foreach($tipiEstintore as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error("anteprima.$r.tipo_estintore_id")
                                        <span class="text-red-600 text-xs">{{ $message }}</span>
                                    @enderror
                                </td>

                                {{-- serbatoio --}}
                                <td class="px-2 py-1">
                                    <input type="date"
                                           wire:model.defer="anteprima.{{ $r }}.data_serbatoio"
                                           class="form-input w-full text-xs">
                                    @error("anteprima.$r.data_serbatoio")
                                        <span class="text-red-600 text-xs">{{ $message }}</span>
                                    @enderror
                                </td>

                                <td class="px-2 py-1">
                                    <input type="date"
                                           wire:model.defer="anteprima.{{ $r }}.data_revisione"
                                           class="form-input w-full text-xs">
                                </td>

                                <td class="px-2 py-1">
                                    <input type="date"
                                           wire:model.defer="anteprima.{{ $r }}.data_collaudo"
                                           class="form-input w-full text-xs">
                                </td>

                                {{-- anomalie --}}
                                <td class="px-2 py-1 text-center space-x-1">
                                    @for($f = 1; $f <= 3; $f++)
                                        <input type="checkbox" class="form-checkbox"
                                               wire:model.defer="anteprima.{{ $r }}.flag_anomalia{{ $f }}">
                                    @endfor
                                </td>
                                <td class="px-2 py-1 text-center">
                                    <button wire:click="eliminaRigaAnteprima({{ $r }})"
                                            class="text-red-600 hover:text-red-800">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center gap-4 mt-4">
                <button wire:click="conferma"
                        class="bg-green-600 text-white px-6 py-2 rounded shadow hover:bg-green-700 transition">
                    <i class="fa fa-check mr-1"></i> Salva per conferma successiva
                </button>

                @if(collect($anteprima)->contains(fn($r) => !$r['data_serbatoio']))
                    <p class="text-xs text-red-600">
                        Completa le <strong>date serbatoio</strong> evidenziate prima di salvare.
                    </p>
                @endif
            </div>
        </div>
    @endif

    {{-- ============ PRESIDI TEMPORANEI ============ --}}
    @if(count($presidiSalvati))
        <hr class="my-6">

        <h3 class="text-lg font-semibold mb-2 text-red-600">
            <i class="fa fa-hourglass-half mr-1"></i> Presidi da confermare
        </h3>

        {{-- filtro categoria --}}
        <div class="mb-2 flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700">Filtra per categoria:</label>
            <select wire:model="filtroCategoria"
                    class="form-select border-gray-300 text-sm rounded">
                <option value="">Tutte</option>
                <option value="Estintore">Estintori</option>
                <option value="Idrante">Idranti</option>
                <option value="Porta">Porte</option>
            </select>
        </div>

        {{-- tabella --}}
        <div class="overflow-x-auto border rounded shadow-sm">
            <table class="min-w-full text-sm text-left text-gray-800">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-2 py-1 w-8"></th>
                        <th class="px-2 py-1 w-32">Categoria</th>
                        <th class="px-2 py-1">Ubicazione</th>
                        <th class="px-2 py-1">Tipo</th>
                        <th class="px-2 py-1 w-20">Prog.</th>
                        <th class="px-2 py-1 w-40">Serbatoio</th>
                        <th class="px-2 py-1 w-24 text-center">Esito</th>
                        <th class="px-2 py-1 w-8 text-center"></th>  <!-- nuova colonna icona delete -->

                    </tr>
                </thead>
                <tbody>
                    @foreach(collect($presidiSalvati)
                            ->when($filtroCategoria,
                                   fn($c)=>$c->where('categoria',$filtroCategoria)) as $i => $p)
                        @php($missing = !$p['data_serbatoio'])
                        <tr wire:key="row-{{ $p['id'] }}"
                            class="{{ $loop->even ? 'bg-gray-50' : '' }} {{ $missing ? 'bg-yellow-100' : '' }}">

                            {{-- checkbox singolo --}}
                            <td class="px-2 py-1 text-center">
                                <input  type="checkbox"
                                        wire:click="seleziona({{$presidiSalvati[$i]['id']}})">
                            </td>

                            {{-- campi editabili --}}
                            <td class="px-2 py-1">
                                <select wire:model.defer="presidiSalvati.{{ $i }}.categoria"
                                        class="form-select text-xs w-full">
                                    <option>Estintore</option><option>Idrante</option><option>Porta</option>
                                </select>
                            </td>

                            <td class="px-2 py-1">
                                <input wire:model.defer="presidiSalvati.{{ $i }}.ubicazione"
                                       class="form-input w-full text-xs">
                            </td>

                            <td class="px-2 py-1">
                                <select
                                    wire:model.defer="presidiSalvati.{{ $i }}.tipo_estintore_id"
                                    class="form-select text-xs w-full {{ !$p['tipo_estintore_id'] ? 'bg-yellow-100' : '' }}">
                                    <option value="">-- scegli --</option>
                                    @foreach($tipiEstintore as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>

                            <td class="px-2 py-1">
                                <input type="number" min="1"
                                       wire:model.defer="presidiSalvati.{{ $i }}.progressivo"
                                       class="form-input w-full text-xs">
                            </td>

                            <td class="px-2 py-1">
                                <input type="date"
                                       wire:model.defer="presidiSalvati.{{ $i }}.data_serbatoio"
                                       class="form-input w-full text-xs">
                            </td>

                            <td class="px-2 py-1 text-center">
                                {!! $missing ? '<span class="text-red-600">⚠️</span>'
                                             : '<span class="text-green-600">✓</span>' !!}
                            </td>
                            <td class="px-2 py-1 text-center">
                                <button wire:click="eliminaImportato({{ $p['id'] }})"
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- azioni --}}
        <div class="flex flex-wrap items-center gap-3 mt-4">
            <button wire:click="confermaImportazione(true)"
                    class="bg-green-600 text-white px-4 py-2 rounded shadow hover:bg-green-700 transition">
                <i class="fa fa-check mr-1"></i> Importa tutti
            </button>

            <button wire:click="confermaImportazione"
                    class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">
                <i class="fa fa-check-double mr-1"></i> Importa selezionati
            </button>

            <button wire:click="salvaModifiche"
                    class="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700 transition">
                <i class="fa fa-save mr-1"></i> Salva modifiche
            </button>

            <button wire:click="eliminaSelezionati"
                    class="bg-red-600 text-white px-4 py-2 rounded shadow hover:bg-red-700 transition">
                <i class="fa fa-trash-alt mr-1"></i> Cancella selezionati
            </button>

        </div>
    @endif
</div>
