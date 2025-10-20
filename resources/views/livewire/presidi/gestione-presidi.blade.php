@php
    use Illuminate\Support\Str;
@endphp

<div class="p-6 max-w-7xl mx-auto bg-white shadow rounded-lg">
    <div class="mb-6 space-y-2">
        <h2 class="text-xl font-semibold text-red-600"><i class="fa fa-info-circle mr-2"></i>Dati Cliente</h2>
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
    </div>
    <hr class="my-8 border-t">

<h2 class="text-xl font-semibold text-red-600 mb-4">
    <i class="fa fa-upload mr-1"></i> Importa Presidi da File Word (.docx)
</h2>

@livewire('presidi.importa-presidi', ['clienteId' => $cliente->id, 'sedeId' => $sede->id ?? null])

<hr class="my-8 p-2 border-t">
    <div class="mb-4 overflow-x-auto">
        <div class="flex flex-wrap gap-2">
            @foreach(['Estintore', 'Idrante', 'Porta'] as $cat)
                <button wire:click="selezionaCategoria('{{ $cat }}')"
                        class="px-4 py-2 rounded {{ $categoriaAttiva === $cat ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    {{ $cat }}{{ $categoriaAttiva === $cat ? ' (attiva)' : '' }}
                </button>
            @endforeach
        </div>
    </div>

    @if($presidi->isEmpty())
        <div class="text-gray-500 italic">Nessun presidio registrato per questa categoria.</div>
    @else
        <div class="overflow-x-auto mb-6">
            <table class="w-full text-sm text-gray-800 border border-gray-300 shadow-sm rounded-lg table-auto">
                <thead class="bg-red-600 text-white text-left whitespace-nowrap">
                    <tr>
                        <th class="px-2 py-1">#</th>
                        <th class="px-2 py-1">Ubicazione</th>
                        <th class="px-2 py-1">Tipo Contratto</th>
                        @if ($categoriaAttiva === 'Estintore')
                            <th class="px-2 py-1">Tipo Estintore</th>
                            <th class="px-2 py-1">Note</th>
                            <th class="px-2 py-1">Serbatoio</th>
                            <th class="px-2 py-1">Revisione</th>
                            <th class="px-2 py-1">Collaudo</th>
                            <th class="px-2 py-1">Fine Vita</th>
                            <th class="px-2 py-1">Sostituzione</th>
                            <th class="px-2 py-1">Preventivo</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($presidi as $index => $presidio)
                        <tr class="even:bg-gray-50 whitespace-nowrap align-middle">
                            <td class="px-2 py-1 font-semibold text-center">{{ $presidio->progressivo }}</td>

                            {{-- Stato di modifica --}}
                            <td class="px-2 py-1">
                                @if($presidio->id === $presidioInModifica)
                                    <input type="text" wire:model.defer="presidiData.{{ $presidio->id }}.ubicazione"
                                        class="form-input w-full rounded-md border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 text-sm" />
                                @else
                                    {{ $presidio->ubicazione }}
                                @endif
                            </td>

                            <td class="px-2 py-1">
                                @if($presidio->id === $presidioInModifica)
                                    <input type="text" wire:model.defer="presidiData.{{ $presidio->id }}.tipo_contratto"
                                        class="form-input w-full rounded-md border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 text-sm" />
                                @else
                                    {{ $presidio->tipo_contratto }}
                                @endif
                            </td>

                            <td class="px-2 py-1">
                                @if($presidio->id === $presidioInModifica)
                                    <select wire:model.defer="presidiData.{{ $presidio->id }}.tipo_estintore_id"
                                            class="form-select text-xs w-full">
                                        <option value="">-- scegli --</option>
                                        @foreach($tipiEstintori as $tipo)
                                            <option value="{{ $tipo->id }}">
                                                {{ $tipo->sigla }} - {{ $tipo->descrizione }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    {{ optional($presidio->tipoEstintore)->sigla }}
                                @endif
                            </td>

                            {{-- Note --}}
                            <td class="px-2 py-1">
                                @if($presidio->id === $presidioInModifica)
                                    <input type="text" wire:model.defer="presidiData.{{ $presidio->id }}.note"
                                        class="form-input w-full rounded-md border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 text-sm" />
                                @else
                                    {{ $presidio->note }}
                                @endif
                            </td>

                            {{-- Data Serbatoio con ricalcolo automatico --}}
                            <td class="px-2 py-1">
                                @if($presidio->id === $presidioInModifica)
                                    <input type="date" wire:model="presidiData.{{ $presidio->id }}.data_serbatoio"
                                        wire:change="ricalcolaDate({{ $presidio->id }})"
                                        class="form-input w-full rounded-md border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 text-sm" />
                                @else
                                    {{ $presidio->data_serbatoio ? \Carbon\Carbon::parse($presidio->data_serbatoio)->format('d.m.Y') : '' }}
                                @endif
                            </td>

                            {{-- Date calcolate --}}
                            @foreach(['data_revisione', 'data_collaudo', 'data_fine_vita', 'data_sostituzione'] as $dataCampo)
                                <td class="px-2 py-1">
                                    {{ $presidio->$dataCampo ? \Carbon\Carbon::parse($presidio->$dataCampo)->format('d.m.Y') : '' }}
                                </td>
                            @endforeach

                            {{-- Preventivo --}}
                            <td class="px-2 py-1 text-center">
                                @if($presidio->id === $presidioInModifica)
                                    <input type="checkbox" wire:model.defer="presidiData.{{ $presidio->id }}.flag_preventivo"
                                        class="rounded border-gray-300 focus:ring-red-500 text-red-600" />
                                @else
                                    {{ $presidio->flag_preventivo ? '✓' : '' }}
                                @endif
                            </td>

                            {{-- Azioni --}}
                            <td class="px-2 py-1 text-center">
                                @if($presidio->id === $presidioInModifica)
                                    <button wire:click="salvaRiga({{ $presidio->id }})"
                                            class="text-green-600 hover:text-green-800 transition"><i class="fa fa-save"></i></button>
                                @else
                                    <button wire:click="abilitaModifica({{ $presidio->id }})"
                                            class="text-blue-600 hover:text-blue-800 transition"><i class="fa fa-edit"></i></button>
                                    <button wire:click="disattiva({{ $presidio->id }})"
                                            class="text-blue-600 hover:text-blue-800 transition"><i class="fa fa-eye-slash"></i></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>


            </table>
        </div>
    @endif
    <div class="text-right mt-4">
            <button wire:click="salvaModifichePresidi" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">
                <i class="fa fa-save mr-1"></i> Salva modifiche presidi
            </button>
        </div>
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
                    <select wire:model="tipoEstintore" class="mt-1 w-full rounded border-gray-300 shadow-sm">
                        <option value="">Seleziona tipo</option>
                        @foreach($tipiEstintori as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->sigla }} - {{ $tipo->descrizione }}</option>
                        @endforeach
                    </select>
                    @error('tipoEstintore') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1">
                <label class="block text-sm font-medium text-gray-700">Note</label>
                <textarea wire:model="note" class="mt-1 w-full rounded border-gray-300 shadow-sm"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Data Serbatoio</label>
                    <input type="date" wire:model="dataSerbatoio" class="mt-1 w-full rounde border-gray-300 shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Flag Preventivo</label>
                    <input type="checkbox" wire:model="flagPreventivo" class="mt-1">
                </div>
            </div>
        @endif
        

        {{-- IDRANTE --}}
        @if ($categoria === 'Idrante')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                    <input type="text" wire:model="descrizione" class="mt-1 w-full rounded border-gray-300 shadow-sm">
                </div>
            </div>

            <div class="grid grid-cols-1">
                <label class="block text-sm font-medium text-gray-700">Note</label>
                <textarea wire:model="note" class="mt-1 w-full rounded border-gray-300 shadow-sm"></textarea>
            </div>
        @endif

        {{-- PORTA --}}
        @if ($categoria === 'Porta')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Descrizione</label>
                    <input type="text" wire:model="descrizione" class="mt-1 w-full rounded border-gray-300 shadow-sm">
                </div>
            </div>

            <div class="grid grid-cols-1">
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
