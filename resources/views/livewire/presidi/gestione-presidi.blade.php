<div class="p-6 max-w-7xl mx-auto bg-white shadow rounded-lg">

    {{-- =================== DATI CLIENTE =================== --}}
    <div class="mb-6 space-y-2">
        <h2 class="text-xl font-semibold text-red-600">
            <i class="fa fa-info-circle mr-2"></i>Dati Cliente
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-gray-700">
            <div><strong>Cliente:</strong> {{ $cliente->nome }}</div>
            <div><strong>Sede:</strong> {{ $sede->nome ?? 'Sede Principale' }}</div>
            <div><strong>Contatti:</strong> {{ $cliente->email }} - {{ $cliente->telefono }}</div>
            <div>
                <strong>Mesi di Controllo:</strong>
                @php
                    $raw = $sede->mesi_visita ?? $cliente->mesi_visita;
                    $mesi = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
                @endphp
                {{ $mesi ? implode(', ', $mesi) : '—' }}
            </div>
        </div>
        <div class="mt-3">
            <a href="{{ route('presidi.import.massivo') }}"
               class="inline-flex items-center px-3 py-2 rounded bg-gray-800 text-white text-xs hover:bg-gray-900">
                <i class="fa fa-layer-group mr-1"></i> Import massivo (multi‑file)
            </a>
        </div>
    </div>

    <hr class="my-8 border-t">

    {{-- =================== IMPORT DA DOCX =================== --}}
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-xl font-semibold text-red-600">
                <i class="fa fa-upload mr-1"></i> Importa Presidi da File Word (.docx)
            </h2>
            <span class="text-xs text-gray-500">Step 1: carica → Step 2: verifica → Step 3: importa</span>
        </div>
        @livewire('presidi.importa-presidi', ['clienteId' => $cliente->id, 'sedeId' => $sede->id ?? null])
    </div>

    <hr class="my-8 p-2 border-t">

    {{-- =================== TABS CATEGORIA =================== --}}
    <div class="mb-4 overflow-x-auto">
        <div class="flex flex-wrap gap-2">
            @foreach(['Estintore', 'Idrante', 'Porta'] as $cat)
                <button
                    wire:click="selezionaCategoria('{{ $cat }}')"
                    class="px-4 py-2 rounded {{ $categoriaAttiva === $cat ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    {{ $cat }}{{ $categoriaAttiva === $cat ? ' (attiva)' : '' }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-gray-600">
        <span class="px-2 py-1 rounded bg-gray-100">Categoria: {{ $categoriaAttiva }}</span>
        <span class="px-2 py-1 rounded bg-gray-100">Totale: {{ $presidi->count() }}</span>
        <span class="px-2 py-1 rounded bg-gray-100">Legenda: <span class="font-semibold">MB</span> = marca serbatoio</span>
    </div>

    {{-- =================== LISTA PRESIDI =================== --}}
    @if($presidi->isEmpty())
        <div class="text-gray-500 italic">Nessun presidio registrato per questa categoria.</div>
    @else
        <div class="mb-6 border border-gray-200 rounded-lg shadow-sm bg-white">
            <div class="flex items-center justify-between px-4 py-3 border-b bg-gray-50">
                <div class="text-sm font-semibold text-gray-700">Lista presidi</div>
                <div class="text-xs text-gray-500">Suggerimento: clicca su modifica per aggiornare una riga</div>
            </div>
            <div class="overflow-x-auto max-h-[70vh]">
            @php
                $isEst = ($categoriaAttiva === 'Estintore');
            @endphp

            <table class="w-full table-auto text-sm text-gray-800">
                <colgroup>
                    <col style="width:70px">      {{-- # --}}
                    <col>                         {{-- Ubicazione --}}
                    <col style="width:160px">     {{-- Tipo Contratto --}}
                    @if($isEst)
                        <col style="width:200px"> {{-- Tipo Estintore --}}
                        <col style="width:220px"> {{-- Note --}}
                        <col style="width:130px"> {{-- Acquisto --}}
                        <col style="width:150px"> {{-- Scadenza Presidio --}}
                        <col style="width:130px"> {{-- Serbatoio --}}
                        <col style="width:110px"> {{-- Marca Serbatoio --}}
                        <col style="width:130px"> {{-- Ultima Revisione --}}
                        <col style="width:130px"> {{-- Revisione --}}
                        <col style="width:130px"> {{-- Collaudo --}}
                        <col style="width:130px"> {{-- Fine Vita --}}
                        <col style="width:140px"> {{-- Sostituzione --}}
                        <col style="width:110px"> {{-- Preventivo --}}
                        <col style="width:110px"> {{-- Azioni --}}
                    @else
                        <col style="width:200px"> {{-- Tipo/Dettagli --}}
                        <col style="width:220px"> {{-- Note --}}
                        <col style="width:110px"> {{-- Azioni --}}
                    @endif
                </colgroup>

                <thead class="bg-red-600 text-white text-left sticky top-0 z-10">
                    <tr class="whitespace-nowrap">
                        <th class="px-2 py-1">#</th>
                        <th class="px-2 py-1">Ubicazione</th>
                        <th class="px-2 py-1">Tipo Contratto</th>

                        @if($isEst)
                            <th class="px-2 py-1">Tipo Estintore</th>
                            <th class="px-2 py-1">Note</th>
                            <th class="px-2 py-1">Acquisto</th>
                            <th class="px-2 py-1">Scadenza Presidio</th>
                            <th class="px-2 py-1">Serbatoio</th>
                            <th class="px-2 py-1">Marca Serb.</th>
                            <th class="px-2 py-1">Ultima Revisione</th>
                            <th class="px-2 py-1">Revisione</th>
                            <th class="px-2 py-1">Collaudo</th>
                            <th class="px-2 py-1">Fine Vita</th>
                            <th class="px-2 py-1">Sostituzione</th>
                            <th class="px-2 py-1">Preventivo</th>
                            <th class="px-2 py-1 text-center">Azioni</th>
                        @else
                            <th class="px-2 py-1">Tipo/Dettagli</th>
                            <th class="px-2 py-1">Note</th>
                            <th class="px-2 py-1 text-center">Azioni</th>
                        @endif
                    </tr>
                </thead>

                <tbody>
                    @foreach($presidi as $index => $presidio)
                        @php
                            $rowHex = $presidio->tipoEstintore?->colore?->hex ?? null;
                            $rowRgba = null;
                            if ($rowHex) {
                                $hex = ltrim($rowHex, '#');
                                if (strlen($hex) === 6) {
                                    $r = hexdec(substr($hex, 0, 2));
                                    $g = hexdec(substr($hex, 2, 2));
                                    $b = hexdec(substr($hex, 4, 2));
                                    $rowRgba = "rgba($r, $g, $b, 0.06)";
                                }
                            }
                            $isEditing = $presidio->id === $presidioInModifica;
                        @endphp
                        <tr wire:key="presidio-{{ $presidio->id }}"
                            class="even:bg-gray-50 align-middle {{ $isEditing ? 'bg-yellow-50' : '' }}"
                            style="{{ (!$isEditing && $rowRgba) ? 'background-color: '.$rowRgba.';' : '' }}">
                            {{-- # --}}
                            <td class="px-2 py-1 font-semibold text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @if($isEst && $rowHex)
                                        <span class="inline-block w-2.5 h-2.5 rounded-full ring-1 ring-black/10"
                                              style="background-color: {{ $rowHex }}"></span>
                                    @endif
                                    <span>{{ $presidio->progressivo }}</span>
                                </div>
                            </td>

                            {{-- Ubicazione --}}
                            <td class="px-2 py-1">
                                @if($presidio->id === $presidioInModifica)
                                    <input type="text"
                                        wire:model.defer="presidiData.{{ $presidio->id }}.ubicazione"
                                        class="form-input w-full rounded-md border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 text-sm" />
                                @else
                                    @if($categoriaAttiva !== 'Estintore')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-700 mr-2">{{ strtoupper($presidio->categoria) }}</span>
                                    @endif
                                    {{ $presidio->ubicazione }}
                                @endif
                            </td>

                            {{-- Tipo Contratto --}}
                            <td class="px-2 py-1">
                                @if($presidio->id === $presidioInModifica)
                                    <input type="text"
                                        wire:model.defer="presidiData.{{ $presidio->id }}.tipo_contratto"
                                        class="form-input w-full rounded-md border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 text-sm" />
                                @else
                                    {{ $presidio->tipo_contratto }}
                                @endif
                            </td>

                            @if($isEst)
                                {{-- Tipo Estintore --}}
                                <td class="px-2 py-1">
                                    @if($presidio->id === $presidioInModifica)
                                        <select
                                            wire:model.defer="presidiData.{{ $presidio->id }}.tipo_estintore_id"
                                            wire:change="ricalcolaDate({{ $presidio->id }})"
                                            class="form-select text-xs w-full">
                                            <option value="">-- scegli --</option>
                                            @foreach($tipiEstintori as $tipo)
                                                <option value="{{ $tipo->id }}">{{ $tipo->sigla }} - {{ $tipo->descrizione }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        @php
                                            $hex   = $presidio->tipoEstintore?->colore?->hex ?? '#9CA3AF';
                                            $sigla = $presidio->tipoEstintore?->sigla ?? '';
                                            $isCarrellato = \App\Livewire\Presidi\ImportaPresidi::isCarrellatoText($presidio->tipo_estintore ?? ($presidio->tipoEstintore?->descrizione ?? ''));
                                        @endphp
                                        <div class="flex items-center gap-2">
                                            <span class="inline-block w-3 h-3 rounded-full ring-1 ring-black/10"
                                                  style="background-color: {{ $hex }}"></span>
                                            <span class="text-xs text-gray-600">{{ $sigla }}</span>
                                            @if($isCarrellato)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-semibold">Carrellato</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                {{-- Note --}}
                                <td class="px-2 py-1">
                                    @if($presidio->id === $presidioInModifica)
                                        <input type="text"
                                            wire:model.defer="presidiData.{{ $presidio->id }}.note"
                                            class="form-input w-full rounded-md border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 text-sm" />
                                    @else
                                        {{ $presidio->note }}
                                    @endif
                                </td>

                                {{-- Acquisto --}}
                                <td class="px-2 py-1">
                                    @if($presidio->id === $presidioInModifica)
                                        <input type="date"
                                            wire:model.defer="presidiData.{{ $presidio->id }}.data_acquisto"
                                            wire:change="ricalcolaDate({{ $presidio->id }})"
                                            class="form-input w-full rounded-md border-gray-300 text-sm" />
                                    @else
                                        <span class="inline-block min-w-[110px]">
                                            {{ $presidio->data_acquisto ? \Carbon\Carbon::parse($presidio->data_acquisto)->format('d.m.Y') : '' }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Scadenza Presidio --}}
                                <td class="px-2 py-1">
                                    @if($presidio->id === $presidioInModifica)
                                        <input type="date"
                                            wire:model.defer="presidiData.{{ $presidio->id }}.scadenza_presidio"
                                            class="form-input w-full rounded-md border-gray-300 text-sm" />
                                    @else
                                        <span class="inline-block min-w-[120px]">
                                            {{ $presidio->scadenza_presidio ? \Carbon\Carbon::parse($presidio->scadenza_presidio)->format('d.m.Y') : '' }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Serbatoio --}}
                                <td class="px-2 py-1">
                                    @if($presidio->id === $presidioInModifica)
                                        <input type="date"
                                            wire:model.defer="presidiData.{{ $presidio->id }}.data_serbatoio"
                                            wire:change="ricalcolaDate({{ $presidio->id }})"
                                            class="form-input w-full rounded-md border-gray-300 text-sm" />
                                    @else
                                        <span class="inline-block min-w-[110px]">
                                            {{ $presidio->data_serbatoio ? \Carbon\Carbon::parse($presidio->data_serbatoio)->format('d.m.Y') : '' }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Marca Serbatoio --}}
                                <td class="px-2 py-1">
                                    @if($presidio->id === $presidioInModifica)
                                        <div class="flex items-center gap-2">
                                            <input type="text"
                                                wire:model.defer="presidiData.{{ $presidio->id }}.marca_serbatoio"
                                                wire:change="ricalcolaDate({{ $presidio->id }})"
                                                class="form-input w-full rounded-md border-gray-300 text-sm" />
                                            <button type="button"
                                                wire:click="$set('presidiData.{{ $presidio->id }}.marca_serbatoio','MB')"
                                                class="px-2 py-1 text-xs rounded bg-gray-200 hover:bg-gray-300">
                                                MB
                                            </button>
                                        </div>
                                    @else
                                        @php $marca = $presidio->marca_serbatoio ?? null; @endphp
                                        @if($marca === 'MB')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">
                                                MB
                                            </span>
                                        @else
                                            <span class="inline-block min-w-[80px]">
                                                {{ $marca ?? '' }}
                                            </span>
                                        @endif
                                    @endif
                                </td>

                                {{-- Ultima Revisione --}}
                                <td class="px-2 py-1">
                                    @if($presidio->id === $presidioInModifica)
                                        <input type="date"
                                            wire:model.defer="presidiData.{{ $presidio->id }}.data_ultima_revisione"
                                            wire:change="ricalcolaDate({{ $presidio->id }})"
                                            class="form-input w-full rounded-md border-gray-300 text-sm" />
                                    @else
                                        <span class="inline-block min-w-[110px]">
                                            {{ $presidio->data_ultima_revisione ? \Carbon\Carbon::parse($presidio->data_ultima_revisione)->format('d.m.Y') : '' }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Derivate --}}
                                @foreach(['data_revisione','data_collaudo','data_fine_vita','data_sostituzione'] as $campo)
                                    <td class="px-2 py-1">
                                        @if($presidio->id === $presidioInModifica)
                                            <input type="date"
                                                wire:model.defer="presidiData.{{ $presidio->id }}.{{ $campo }}"
                                                class="form-input w-full rounded-md border-gray-300 text-sm" />
                                        @else
                                            <span class="inline-block min-w-[110px]">
                                                {{ $presidio->$campo ? \Carbon\Carbon::parse($presidio->$campo)->format('d.m.Y') : '' }}
                                            </span>
                                        @endif
                                    </td>
                                @endforeach

                                {{-- Preventivo --}}
                                <td class="px-2 py-1 text-center">
                                    @if($presidio->id === $presidioInModifica)
                                        <input type="checkbox"
                                            wire:model.defer="presidiData.{{ $presidio->id }}.flag_preventivo"
                                            class="rounded border-gray-300 focus:ring-red-500 text-red-600" />
                                    @else
                                        {{ $presidio->flag_preventivo ? '✓' : '' }}
                                    @endif
                                </td>

                                {{-- Azioni --}}
                                <td class="px-2 py-1 text-center">
                                    @if($presidio->id === $presidioInModifica)
                                        <button wire:click="salvaRiga({{ $presidio->id }})"
                                                class="text-green-600 hover:text-green-800 transition" title="Salva">
                                            <i class="fa fa-save"></i>
                                        </button>
                                    @else
                                        <button wire:click="abilitaModifica({{ $presidio->id }})"
                                                class="text-blue-600 hover:text-blue-800 transition" title="Modifica">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <button wire:click="disattiva({{ $presidio->id }})"
                                                class="text-blue-600 hover:text-blue-800 transition" title="Disattiva">
                                            <i class="fa fa-eye-slash"></i>
                                        </button>
                                        <button
                                            onclick="if(!confirm('Eliminare definitivamente questo presidio?')){ event.stopImmediatePropagation(); event.preventDefault(); }"
                                            wire:click="elimina({{ $presidio->id }})"
                                            class="text-red-600 hover:text-red-800 transition" title="Elimina definitivamente">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    @endif
                                </td>
                            @else
                            {{-- Tipo/Dettagli (Idrante/Porta) --}}
                            <td class="px-2 py-1">
                                @if($presidio->id === $presidioInModifica)
                                    @if($presidio->categoria === 'Idrante')
                                        <div class="flex flex-wrap items-center gap-2">
                                            <select wire:model.defer="presidiData.{{ $presidio->id }}.idrante_tipo_id"
                                                    class="form-select text-xs">
                                                <option value="">-- tipo --</option>
                                                @foreach($tipiIdranti as $id => $tipo)
                                                    <option value="{{ $id }}">{{ $tipo }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text"
                                                   wire:model.defer="presidiData.{{ $presidio->id }}.idrante_lunghezza"
                                                   class="form-input w-24 text-xs"
                                                   placeholder="es. 20 Mt">
                                            <label class="inline-flex items-center text-xs gap-1">
                                                <input type="checkbox"
                                                       wire:model.defer="presidiData.{{ $presidio->id }}.idrante_sopra_suolo">
                                                Sopra
                                            </label>
                                            <label class="inline-flex items-center text-xs gap-1">
                                                <input type="checkbox"
                                                       wire:model.defer="presidiData.{{ $presidio->id }}.idrante_sotto_suolo">
                                                Sotto
                                            </label>
                                        </div>
                                    @else
                                        <select wire:model.defer="presidiData.{{ $presidio->id }}.porta_tipo_id"
                                                class="form-select text-xs w-full">
                                            <option value="">-- tipo porta --</option>
                                            @foreach($tipiPorte as $id => $tipo)
                                                <option value="{{ $id }}">{{ $tipo }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                @else
                                    @if($presidio->categoria === 'Idrante')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700 mr-2">IDRANTE</span>
                                        <span class="text-xs text-gray-700">
                                            {{ $presidio->idranteTipoRef?->nome ?? $presidio->idrante_tipo }} {{ $presidio->idrante_lunghezza }}
                                            @if($presidio->idrante_sopra_suolo) · sopra @endif
                                            @if($presidio->idrante_sotto_suolo) · sotto @endif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700 mr-2">PORTA</span>
                                        <span class="text-xs text-gray-700">{{ $presidio->portaTipoRef?->nome ?? $presidio->porta_tipo }}</span>
                                    @endif
                                @endif
                            </td>

                            {{-- Note (Idrante/Porta) --}}
                            <td class="px-2 py-1">
                                @if($presidio->id === $presidioInModifica)
                                    <input type="text"
                                        wire:model.defer="presidiData.{{ $presidio->id }}.note"
                                        class="form-input w-full rounded-md border-gray-300 text-sm" />
                                @else
                                    {{ $presidio->note }}
                                @endif
                            </td>

                                {{-- Azioni --}}
                                <td class="px-2 py-1 text-center">
                                    @if($presidio->id === $presidioInModifica)
                                        <button wire:click="salvaRiga({{ $presidio->id }})"
                                                class="text-green-600 hover:text-green-800 transition" title="Salva">
                                            <i class="fa fa-save"></i>
                                        </button>
                                    @else
                                        <button wire:click="abilitaModifica({{ $presidio->id }})"
                                                class="text-blue-600 hover:text-blue-800 transition" title="Modifica">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <button wire:click="disattiva({{ $presidio->id }})"
                                                class="text-blue-600 hover:text-blue-800 transition" title="Nascondi (disattiva)">
                                            <i class="fa fa-eye-slash"></i>
                                        </button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif

    <div class="text-right mt-4">
        <button wire:click="salvaModifichePresidi"
                class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">
            <i class="fa fa-save mr-1"></i> Salva modifiche presidi
        </button>
    </div>

    {{-- Riepilogo tipi (Estintori) --}}
    @if($this->riepilogoTipiEstintori->count())
        <hr class="my-6">
        <div class="bg-white rounded shadow p-4 max-w-xl mx-auto">
            <h3 class="text-lg font-semibold mb-3 text-gray-700">
                <i class="fa fa-list-alt text-red-600 mr-1"></i> Riepilogo Estintori per Tipologia
            </h3>
            <ul class="text-sm text-gray-800 space-y-1">
                @foreach($this->riepilogoTipiEstintori as $etichetta => $quantita)
                    <li class="flex justify-between border-b py-1">
                        <span>{{ $etichetta }}</span>
                        <strong>{{ $quantita }}</strong>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- =================== NUOVO PRESIDIO =================== --}}
    <h2 class="text-xl font-semibold text-red-600 mb-4">
        <i class="fa fa-fire-extinguisher mr-1"></i> Nuovo Presidio
    </h2>

    @if (session()->has('message'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
            <i class="fa fa-check-circle mr-1"></i> {{ session('message') }}
        </div>
    @endif

    <form wire:submit.prevent="salvaPresidio" class="space-y-4">
        {{-- CAMPI COMUNI --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Ubicazione</label>
                <input type="text" wire:model="ubicazione" class="mt-1 w-full rounded border-gray-300 shadow-sm">
                @error('ubicazione') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tipo contratto</label>
                <input type="text" wire:model="tipoContratto" class="mt-1 w-full rounded border-gray-300 shadow-sm">
                @error('tipoContratto') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- ESTINTORE --}}
        @if ($categoria === 'Estintore')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo Estintore</label>
                    <select wire:model="tipoEstintore"
                            wire:change="aggiornaScadenzaPresidio"
                            class="mt-1 w-full rounded border-gray-300 shadow-sm">
                        <option value="">Seleziona tipo</option>
                        @foreach($tipiEstintori as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->sigla }} - {{ $tipo->descrizione }}</option>
                        @endforeach
                    </select>
                    @error('tipoEstintore') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 mt-6">
                        <input type="checkbox" wire:model="isAcquisto" wire:change="aggiornaScadenzaPresidio"
                            class="rounded border-gray-300">
                        <span class="text-sm text-gray-700">Estintore acquistato (già di proprietà del cliente)</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Note</label>
                <textarea wire:model="note" class="mt-1 w-full rounded border-gray-300 shadow-sm"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- Data Serbatoio: guida revisioni/collaudi/fine vita --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Data Serbatoio</label>
                    <input type="date" wire:model="dataSerbatoio"
                        class="mt-1 w-full rounded border-gray-300 shadow-sm">
                    @error('dataSerbatoio') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Marca Serbatoio</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="text" wire:model="marcaSerbatoio"
                            class="w-full rounded border-gray-300 shadow-sm">
                        <button type="button"
                            wire:click="$set('marcaSerbatoio','MB')"
                            class="px-3 py-2 text-xs rounded bg-gray-200 hover:bg-gray-300">
                            MB
                        </button>
                    </div>
                    @error('marcaSerbatoio') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Data Ultima Revisione</label>
                    <input type="date" wire:model="dataUltimaRevisione"
                        class="mt-1 w-full rounded border-gray-300 shadow-sm">
                    @error('dataUltimaRevisione') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                {{-- Acquisto + Scadenza Presidio: visibili solo se isAcquisto --}}
                @if($isAcquisto)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Data Acquisto</label>
                        <input type="date" wire:model="dataAcquisto" wire:change="aggiornaScadenzaPresidio"
                            class="mt-1 w-full rounded border-gray-300 shadow-sm">
                        @error('dataAcquisto') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Scadenza Presidio</label>
                        <input type="date" wire:model="scadenzaPresidio"
                            class="mt-1 w-full rounded border-gray-300 shadow-sm"
                            readonly>
                    </div>
                @endif

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 mt-6">
                        <input type="checkbox" wire:model="flagPreventivo" class="rounded border-gray-300">
                        <span class="text-sm text-gray-700">Flag Preventivo</span>
                    </label>
                </div>
            </div>
        @endif

        {{-- IDRANTE / PORTA --}}
        @if ($categoria === 'Idrante')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo Idrante</label>
                    <select wire:model="idranteTipo" class="mt-1 w-full rounded border-gray-300 shadow-sm">
                        <option value="">Seleziona tipo</option>
                        @foreach($tipiIdranti as $id => $tipo)
                            <option value="{{ $id }}">{{ $tipo }}</option>
                        @endforeach
                    </select>
                    @error('idranteTipo') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Lunghezza</label>
                    <input type="text" wire:model="idranteLunghezza" class="mt-1 w-full rounded border-gray-300 shadow-sm" placeholder="es. 20 Mt">
                </div>
                <div class="flex items-center gap-4">
                    <label class="inline-flex items-center gap-2 mt-2">
                        <input type="checkbox" wire:model="idranteSopraSuolo" class="rounded border-gray-300">
                        <span class="text-sm text-gray-700">Sopra suolo</span>
                    </label>
                    <label class="inline-flex items-center gap-2 mt-2">
                        <input type="checkbox" wire:model="idranteSottoSuolo" class="rounded border-gray-300">
                        <span class="text-sm text-gray-700">Sotto suolo</span>
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Note</label>
                <textarea wire:model="note" class="mt-1 w-full rounded border-gray-300 shadow-sm"></textarea>
            </div>
        @elseif($categoria === 'Porta')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo Porta</label>
                    <select wire:model="portaTipo" class="mt-1 w-full rounded border-gray-300 shadow-sm">
                        <option value="">Seleziona tipo</option>
                        @foreach($tipiPorte as $id => $tipo)
                            <option value="{{ $id }}">{{ $tipo }}</option>
                        @endforeach
                    </select>
                    @error('portaTipo') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Note</label>
                <textarea wire:model="note" class="mt-1 w-full rounded border-gray-300 shadow-sm"></textarea>
            </div>
        @endif

        <div class="text-right">
            <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded shadow hover:bg-red-700 transition">
                <i class="fa fa-save mr-1"></i> Salva Presidio
            </button>
        </div>
    </form>
</div>
