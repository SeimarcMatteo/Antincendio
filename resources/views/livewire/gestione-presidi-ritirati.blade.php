<div class="max-w-7xl mx-auto p-4 space-y-6">
    <h1 class="text-2xl font-bold text-red-700">🧾 Presidi Ritirati</h1>

    {{-- Filtri --}}
    <div class="flex flex-wrap gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium">Categoria</label>
            <select wire:model="categoriaFiltro" class="input input-sm input-bordered">
                <option value="">Tutte</option>
                @foreach($categorie as $categoria)
                    <option value="{{ $categoria }}">{{ $categoria }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Cliente</label>
            <select wire:model="clienteFiltro" class="input input-sm input-bordered">
                <option value="">Tutti</option>
                @foreach($clienti as $cliente)
                    <option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Stato</label>
            <select wire:model="statoFiltro" class="input input-sm input-bordered">
                <option value="">Tutti</option>
                <option value="disponibile">Disponibile</option>
                <option value="assegnato">Assegnato</option>
                <option value="rottamato">Rottamato</option>
            </select>
        </div>
    </div>

    {{-- Tabella --}}
    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="table-auto w-full text-sm">
        <thead>
            <tr class="bg-gray-100 text-left">
                <th class="p-2">ID</th>
                <th class="p-2">Categoria</th>
                <th class="p-2">Progressivo</th>
                <th class="p-2">Ubicazione</th>
                <th class="p-2">Tipo</th>
                <th class="p-2">Data Serbatoio</th>
                <th class="p-2">Cliente</th>
                <th class="p-2">Anomalie</th> {{-- NUOVA COLONNA --}}
                <th class="p-2">Stato</th>
                <th class="p-2 text-right">Azioni</th>
            </tr>
            </thead>
            <tbody>
            @forelse($presidi as $p)
                <tr class="border-b">
                    <td class="p-2">{{ $p->id }}</td>
                    <td class="p-2">{{ $p->presidio->categoria }}</td>
                    <td class="p-2">{{ $p->presidio->progressivo }}</td>
                    <td class="p-2">{{ $p->presidio->ubicazione }}</td>
                    <td class="p-2">{{ $p->presidio->tipoEstintore->sigla ?? '-' }}</td>
                    <td class="p-2">{{ \Illuminate\Support\Carbon::parse($p->presidio->data_serbatoio)->format('d/m/Y') }}</td>
                    <td class="p-2">{{ $p->cliente->nome ?? '-' }}</td>

                    {{-- ANOMALIE --}}
                    <td class="p-2">
                        @php
                            $anomalie = collect($p->presidioIntervento?->anomalie ?? [])
                                ->map(fn($id) => \App\Models\Anomalia::find($id)?->etichetta)
                                ->filter()
                                ->implode(', ');
                        @endphp
                        {{ $anomalie ?: '-' }}
                    </td>

                    <td class="p-2 font-semibold text-{{ $p->stato === 'disponibile' ? 'green' : ($p->stato === 'assegnato' ? 'blue' : 'red') }}-600">
                        {{ ucfirst($p->stato) }}
                    </td>
                    <td class="p-2 text-right">
                        <select wire:change="aggiornaStato({{ $p->id }}, $event.target.value)" class="input input-sm">
                            <option value="">Cambia</option>
                            <option value="disponibile">Disponibile</option>
                            <option value="assegnato">Assegnato</option>
                            <option value="rottamato">Rottamato</option>
                        </select>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="p-4 text-center text-gray-500">Nessun presidio trovato.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginazione --}}
    <div class="mt-4">
        {{ $presidi->links() }}
    </div>
</div>
