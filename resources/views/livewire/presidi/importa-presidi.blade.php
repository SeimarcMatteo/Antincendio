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

    {{-- Nota mesi preferiti --}}
    <p class="text-xs text-gray-600">
        Le date di <strong>revisione</strong>, <strong>collaudo</strong> e <strong>sostituzione</strong> sono
        allineate ai mesi preferiti del cliente (se configurati). Se non presenti, si usa
        il mese precedente la scadenza ma non prima del mese attuale.
    </p>

    {{-- ============ ANTEPRIMA LETTA ============ --}}
    @if($anteprima)
        @php
            $showAcq = collect($anteprima)->contains(function($r){
                return !empty($r['data_acquisto'] ?? null);
            });
            $showScadP = collect($anteprima)->contains(function($r){
                return !empty($r['scadenza_presidio'] ?? null);
            });
            $hasMissingAnteprima = collect($anteprima)->contains(function($r){
                return empty($r['data_serbatoio'] ?? null) || empty($r['tipo_estintore_id'] ?? null);
            });
        @endphp

        <div class="mt-4">
            <h3 class="text-lg font-semibold mb-2">Anteprima presidi rilevati</h3>

            <div class="overflow-x-auto border rounded shadow-sm">
                <table class="min-w-full table-fixed text-sm text-left text-gray-800">
                    <colgroup>
                        <col style="width: 70px">   {{-- Prog. --}}
                        <col style="width: 100px">  {{-- Categoria --}}
                        <col>                        {{-- Ubicazione (elastica) --}}
                        <col style="width: 170px">  {{-- Tipo --}}
                        <col style="width: 110px">  {{-- Contratto --}}
                        <col style="width: 130px">  {{-- Serbatoio --}}
                        <col style="width: 130px">  {{-- Revisione --}}
                        <col style="width: 130px">  {{-- Collaudo --}}
                        <col style="width: 130px">  {{-- Fine vita --}}
                        @if($showAcq)<col style="width: 130px">@endif
                        @if($showScadP)<col style="width: 150px">@endif
                        <col style="width: 150px">  {{-- Sostituzione --}}
                        <col style="width: 60px">   {{-- Azioni/Esito --}}
                    </colgroup>
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-2 py-1 w-20">Prog.</th>
                            <th class="px-2 py-1 w-28">Categoria</th>
                            <th class="px-2 py-1">Ubicazione</th>
                            <th class="px-2 py-1">Tipo</th>
                            <th class="px-2 py-1 w-32">Contratto</th>
                            <th class="px-2 py-1 w-40">Serbatoio&nbsp;<span class="text-red-600">*</span></th>
                            <th class="px-2 py-1 w-40">Revisione (all.)</th>
                            <th class="px-2 py-1 w-40">Collaudo (all.)</th>
                            <th class="px-2 py-1 w-40">Fine vita</th>
                            @if($showAcq)
                                <th class="px-2 py-1 w-40">Acquisto</th>
                            @endif
                            @if($showScadP)
                                <th class="px-2 py-1 w-40">Scadenza Presidio</th>
                            @endif
                            <th class="px-2 py-1 w-40">Sostituzione (operativa)</th>
                            <th class="px-2 py-1 w-16 text-center">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($anteprima as $r => $row)
                            @php
                                $missing = empty($row['data_serbatoio']) || empty($row['tipo_estintore_id']);
                            @endphp
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
                                        <option>Estintore</option>
                                        <option>Idrante</option>
                                        <option>Porta</option>
                                    </select>
                                </td>

                                {{-- ubicazione --}}
                                <td class="px-2 py-1">
                                    <input wire:model.defer="anteprima.{{ $r }}.ubicazione"
                                           class="form-input w-full text-xs">
                                </td>

                                {{-- tipo estintore --}}
                                <td class="px-2 py-1 whitespace-nowrap">
                                    <select
                                        wire:model.defer="anteprima.{{ $r }}.tipo_estintore_id"
                                        wire:change="ricalcola('anteprima', {{ $r }})"
                                        class="form-select w-40 text-xs {{ empty($row['tipo_estintore_id']) ? 'bg-yellow-100' : '' }}">
                                        <option value=""> -- scegli -- </option>
                                        @foreach($tipiEstintore as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error("anteprima.$r.tipo_estintore_id")
                                        <span class="text-red-600 text-xs">{{ $message }}</span>
                                    @enderror
                                </td>

                 

                                {{-- contratto --}}
                                <td class="px-2 py-1">
                                    <input wire:model.defer="anteprima.{{ $r }}.tipo_contratto"
                                           class="form-input w-full text-xs">
                                </td>

                                {{-- serbatoio --}}
                                <td class="px-2 py-1">
                                    <input type="date"
                                        wire:model.defer="anteprima.{{ $r }}.data_serbatoio"
                                        wire:change="ricalcola('anteprima', {{ $r }})"
                                        class="form-input w-36 text-xs">
                                    @error("anteprima.$r.data_serbatoio")
                                        <span class="text-red-600 text-xs">{{ $message }}</span>
                                    @enderror
                                </td>


                                {{-- revisione (allineata) --}}
                                <td class="px-2 py-1">
                                    <input type="date"
                                           wire:model.defer="anteprima.{{ $r }}.data_revisione"
                                           class="form-input w-full text-xs">
                                </td>

                                {{-- collaudo (allineato) --}}
                                <td class="px-2 py-1">
                                    <input type="date"
                                           wire:model.defer="anteprima.{{ $r }}.data_collaudo"
                                           class="form-input w-full text-xs">
                                </td>

                                {{-- fine vita --}}
                                <td class="px-2 py-1">
                                    <input type="date"
                                           wire:model.defer="anteprima.{{ $r }}.data_fine_vita"
                                           class="form-input w-full text-xs">
                                </td>

                                {{-- acquisto opzionale --}}
                                @if($showAcq)
                                    <td class="px-2 py-1">
                                        <input type="date"
                                               wire:model.defer="anteprima.{{ $r }}.data_acquisto"
                                               class="form-input w-full text-xs">
                                    </td>
                                @endif

                                {{-- scadenza presidio opzionale --}}
                                @if($showScadP)
                                    <td class="px-2 py-1">
                                        <input type="date"
                                               wire:model.defer="anteprima.{{ $r }}.scadenza_presidio"
                                               class="form-input w-full text-xs">
                                    </td>
                                @endif

                                {{-- sostituzione operativa --}}
                                <td class="px-2 py-1">
                                    <input type="date"
                                           wire:model.defer="anteprima.{{ $r }}.data_sostituzione"
                                           class="form-input w-full text-xs">
                                </td>

                                {{-- azioni --}}
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

                @if($hasMissingAnteprima)
                    <p class="text-xs text-red-600">
                        Completa <strong>Tipo estintore</strong> e <strong>Data serbatoio</strong> prima di salvare.
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

        @php
            // PREPARA RIGHE PRIMA DI USARLE OVUNQUE
            $rows = collect($presidiSalvati);
            if (!empty($filtroCategoria)) {
                $rows = $rows->where('categoria', $filtroCategoria);
            }
            $showAcqS = $rows->contains(function($r){
                return !empty($r['data_acquisto'] ?? null);
            });
            $showScadPS = $rows->contains(function($r){
                return !empty($r['scadenza_presidio'] ?? null);
            });
        @endphp

        <div class="overflow-x-auto border rounded shadow-sm">
            <table class="min-w-full table-fixed text-sm text-left text-gray-800">
                <colgroup>
                    <col style="width: 70px">   {{-- Prog. --}}
                    <col style="width: 100px">  {{-- Categoria --}}
                    <col>                        {{-- Ubicazione (elastica) --}}
                    <col style="width: 170px">  {{-- Tipo --}}
                    <col style="width: 110px">  {{-- Contratto --}}
                    <col style="width: 130px">  {{-- Serbatoio --}}
                    <col style="width: 130px">  {{-- Revisione --}}
                    <col style="width: 130px">  {{-- Collaudo --}}
                    <col style="width: 130px">  {{-- Fine vita --}}
                    @if($showAcqS)<col style="width: 130px">@endif
                    @if($showScadPS)<col style="width: 150px">@endif
                    <col style="width: 150px">  {{-- Sostituzione --}}
                    <col style="width: 60px">   {{-- Azioni/Esito --}}
                </colgroup>
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-2 py-1 w-8"></th>
                        <th class="px-2 py-1 w-28">Categoria</th>
                        <th class="px-2 py-1">Ubicazione</th>
                        <th class="px-2 py-1">Tipo</th>
                        <th class="px-2 py-1 w-20">Prog.</th>
                        <th class="px-2 py-1 w-32">Contratto</th>
                        <th class="px-2 py-1 w-36">Serbatoio</th>
                        <th class="px-2 py-1 w-36">Revisione</th>
                        <th class="px-2 py-1 w-36">Collaudo</th>
                        <th class="px-2 py-1 w-36">Fine vita</th>
                        @if($showAcqS)
                            <th class="px-2 py-1 w-36">Acquisto</th>
                        @endif
                        @if($showScadPS)
                            <th class="px-2 py-1 w-40">Scadenza Presidio</th>
                        @endif
                        <th class="px-2 py-1 w-40">Sostituzione</th>
                        <th class="px-2 py-1 w-24 text-center">Esito</th>
                        <th class="px-2 py-1 w-8 text-center"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $p)
                        @php
                            $missing = empty($p['data_serbatoio']) || empty($p['tipo_estintore_id']);
                        @endphp
                        <tr wire:key="row-{{ $p['id'] }}"
                            class="{{ $loop->even ? 'bg-gray-50' : '' }} {{ $missing ? 'bg-yellow-100' : '' }}">

                            {{-- checkbox singolo --}}
                            <td class="px-2 py-1 text-center">
                                <input type="checkbox" wire:click="seleziona({{ $p['id'] }})">
                            </td>

                            {{-- campi editabili --}}
                            <td class="px-2 py-1">
                                <select wire:model.defer="presidiSalvati.{{ $i }}.categoria"
                                        class="form-select text-xs w-full">
                                    <option>Estintore</option>
                                    <option>Idrante</option>
                                    <option>Porta</option>
                                </select>
                            </td>

                            <td class="px-2 py-1">
                                <input wire:model.defer="presidiSalvati.{{ $i }}.ubicazione"
                                       class="form-input w-full text-xs">
                            </td>

                            {{-- tipo estintore --}}
                            <td class="px-2 py-1 whitespace-nowrap">
                                <select
                                    wire:model.defer="presidiSalvati.{{ $i }}.tipo_estintore_id"
                                    wire:change="ricalcola('salvati', {{ $i }})"
                                    class="form-select text-xs w-40 {{ empty($p['tipo_estintore_id']) ? 'bg-yellow-100' : '' }}">
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
                                <input wire:model.defer="presidiSalvati.{{ $i }}.tipo_contratto"
                                       class="form-input w-full text-xs">
                            </td>

                          
                            {{-- serbatoio --}}
                            <td class="px-2 py-1">
                                <input type="date"
                                    wire:model.defer="presidiSalvati.{{ $i }}.data_serbatoio"
                                    wire:change="ricalcola('salvati', {{ $i }})"
                                    class="form-input w-36 text-xs">
                            </td>


                            <td class="px-2 py-1">
                                <input type="date"
                                       wire:model.defer="presidiSalvati.{{ $i }}.data_revisione"
                                       class="form-input w-full text-xs">
                            </td>

                            <td class="px-2 py-1">
                                <input type="date"
                                       wire:model.defer="presidiSalvati.{{ $i }}.data_collaudo"
                                       class="form-input w-full text-xs">
                            </td>

                            <td class="px-2 py-1">
                                <input type="date"
                                       wire:model.defer="presidiSalvati.{{ $i }}.data_fine_vita"
                                       class="form-input w-full text-xs">
                            </td>

                            @if($showAcqS)
                                <td class="px-2 py-1">
                                    <input type="date"
                                           wire:model.defer="presidiSalvati.{{ $i }}.data_acquisto"
                                           class="form-input w-full text-xs">
                                </td>
                            @endif

                            @if($showScadPS)
                                <td class="px-2 py-1">
                                    <input type="date"
                                           wire:model.defer="presidiSalvati.{{ $i }}.scadenza_presidio"
                                           class="form-input w-full text-xs">
                                </td>
                            @endif

                            <td class="px-2 py-1">
                                <input type="date"
                                       wire:model.defer="presidiSalvati.{{ $i }}.data_sostituzione"
                                       class="form-input w-full text-xs">
                            </td>

                            <td class="px-2 py-1 text-center">
                                {!! $missing ? '<span class="text-red-600">⚠️</span>' : '<span class="text-green-600">✓</span>' !!}
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
