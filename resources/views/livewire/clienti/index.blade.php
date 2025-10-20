<div class="p-4 sm:p-6 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-4">
        <form wire:submit.prevent="aggiornaRicerca" class="flex-1 relative">
            <input
                type="text"
                wire:model.defer="search"
                placeholder="Cerca cliente..."
                class="w-full rounded-lg border-gray-300 shadow-sm pl-10 pr-4 py-2 focus:ring-2 focus:ring-red-400 focus:outline-none"
            />
            <i class="fa fa-search absolute left-3 top-2.5 text-gray-400"></i>
        </form>

        <div class="flex items-center gap-2">
            <label for="perPage" class="text-sm font-medium">Mostra</label>
            <select
                id="perPage"
                wire:model="perPage"
                class="rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-red-400 focus:outline-none"
            >
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
            <span class="text-sm">record</span>
        </div>
    </div>

    <div class="overflow-x-auto bg-white rounded-lg shadow-sm border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-red-600 text-white text-sm">
                <tr>
                    <th class="px-4 py-2 text-left">#</th>
                    <th class="px-4 py-2 text-left">Codice</th>
                    <th class="px-4 py-2 text-left">Nome</th>
                    <th class="px-4 py-2 text-left">P.IVA</th>
                    <th class="px-4 py-2 text-left">Email</th>
                    <th class="px-4 py-2 text-left">Telefono</th>
                    <th class="px-4 py-2 text-left">Città</th>
                    <th class="px-4 py-2 text-left">Azioni</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                @forelse ($clienti as $cliente)
                    <tr>
                        <td class="px-4 py-2">{{ $cliente->id }}</td>
                        <td class="px-4 py-2">{{ $cliente->codice_esterno }}</td>
                        <td class="px-4 py-2">
                            <span class="truncate block max-w-[200px]" title="{{ $cliente->nome }}">
                                {!! highlight($cliente->nome, $searchReady ? $search : '') !!}
                            </span>
                        </td>
                        <td class="px-4 py-2">{{ $cliente->p_iva }}</td>
                        <td class="px-4 py-2">
                            <span class="truncate block max-w-[200px]" title="{{ $cliente->email }}">
                                {!! highlight($cliente->email, $searchReady ? $search : '') !!}
                            </span>
                        </td>
                        <td class="px-4 py-2">{{ $cliente->telefono }}</td>
                        <td class="px-4 py-2">{{ $cliente->citta }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('clienti.mostra', $cliente->id) }}" class="text-red-600 hover:text-red-800 transition">
                                <i class="fa fa-eye"> APRI </i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-gray-400">
                            Nessun cliente trovato.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $clienti->links() }}
    </div>
</div>
