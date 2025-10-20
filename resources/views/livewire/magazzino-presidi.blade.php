<div class="max-w-6xl mx-auto p-4 space-y-6">
    <h1 class="text-2xl font-bold text-red-700">📦 Magazzino Presidi</h1>

    {{-- Filtri --}}
    <div class="flex flex-wrap gap-4 mb-4 items-end">
        <div>
            <label class="block text-sm font-medium">Categoria</label>
            <select wire:model.defer="categoriaFiltroInput" class="input input-sm input-bordered">
                <option value="">Tutte</option>
                @foreach($categorie as $cat)
                    <option value="{{ $cat }}">{{ $cat }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">Tipo Estintore</label>
            <select wire:model.defer="tipoFiltroInput" class="input input-sm input-bordered">
                <option value="">Tutti</option>
                @foreach($tipi as $tipo)
                    <option value="{{ $tipo->id }}">{{ $tipo->sigla }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2 mb-4">
            <button wire:click="applicaFiltri" class="btn btn-sm btn-neutral">
                🔍 Cerca
            </button>

            <button wire:click="esportaExcel" class="btn btn-sm btn-success">
                <i class="fa-solid fa-file-excel mr-1"></i> Esporta Excel
            </button>
            <button wire:click="esportaPdf" class="btn btn-sm btn-primary">
                <i class="fa-solid fa-file-pdf mr-1"></i> Esporta PDF
            </button>
        </div>
    </div>


    {{-- Tabella --}}
    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="table-auto w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">Categoria</th>
                    <th class="p-2 text-left">Tipo Estintore</th>
                    <th class="p-2 text-center">Quantità</th>
                    <th class="p-2 text-center">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($giacenze as $g)
                    <tr class="border-b">
                        <td class="p-2">{{ $g->categoria }}</td>
                        <td class="p-2">{{ $g->tipoEstintore->sigla ?? '-' }}</td>
                        <td class="p-2 text-center font-bold text-blue-700">{{ $g->quantita }}</td>
                        <td class="p-2 text-center space-x-2">
                            <button wire:click="decrementa({{ $g->id }})" class="btn btn-sm btn-outline">➖</button>
                            <button wire:click="incrementa({{ $g->id }})" class="btn btn-sm btn-outline">➕</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-4 text-center text-gray-500">Nessuna giacenza trovata.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
