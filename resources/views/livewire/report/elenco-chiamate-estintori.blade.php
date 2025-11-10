<div class="bg-white shadow rounded-xl p-4 space-y-4">
    <h2 class="text-lg font-semibold text-red-700">📅 Elenco Chiamate Estintori da Sostituire</h2>

    {{-- Filtri --}}
    <div class="flex flex-wrap gap-4 items-end">
    <div class="flex gap-2 items-end">
    <div>
        <label class="text-sm font-medium">Mese</label>
        <select wire:model="mese" class="select select-bordered">
            @for ($i = 1; $i <= 12; $i++)
                <option value="{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}">{{ Date::create()->month($i)->format('F') }}</option>
            @endfor
        </select>
    </div>
    <div>
        <label class="text-sm font-medium">Anno</label>
        <select wire:model="anno" class="select select-bordered">
            @for ($y = now()->year - 2; $y <= now()->year + 2; $y++)
                <option value="{{ $y }}">{{ $y }}</option>
            @endfor
        </select>
    </div>
</div>


        <div>
            <label class="text-sm">Zona</label>
            <select wire:model="zona" class="select select-bordered">
                <option value="">Tutte</option>
                @foreach(\App\Models\Cliente::select('zona')->distinct()->pluck('zona')->filter() as $z)
                    <option value="{{ $z }}">{{ $z }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button wire:click="caricaDati()" class="btn btn-sm btn-primary">
                🔍 Cerca
            </button>
        </div>
        <div>
            <button wire:click="esportaExcel" class="btn btn-sm btn-success">
                📥 Esporta Excel
            </button>
        </div>
    </div>

    {{-- Tabella --}}
    <div class="overflow-x-auto">

        <table class="table table-sm w-full mt-4">
            <thead>
                <tr class="bg-gray-100 text-gray-700 font-semibold text-sm">
                    <th class="px-4 py-2">📅 Data</th>
                    <th class="px-4 py-2">📍 Zona</th>
                    <th class="px-4 py-2">🏢 Cliente</th>
                    <th class="px-4 py-2">🧯 Tipo Estintore</th>
                    <th class="px-4 py-2 text-center">🛠 Revisioni</th>
                    <th class="px-4 py-2 text-center">⚙️ Collaudi</th>
                    <th class="px-4 py-2 text-center">⛔ Fine Vita</th>
                    <th class="px-4 py-2 text-center">📦 Totale</th>
                </tr>
            </thead>
                @forelse($dati as $riga)
                    <tr class="border-b">
                        <td class="px-4 py-2">{{ \Carbon\Carbon::parse($riga['data'])->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">{{ $riga['zona'] }}</td>
                        <td class="px-4 py-2">{{ $riga['cliente'] }}</td>
                        <td class="px-4 py-2">{{ $riga['tipo_estintore'] }}</td>
                        <td class="px-4 py-2 text-center text-orange-600 font-semibold">{{ $riga['revisione'] }}</td>
                        <td class="px-4 py-2 text-center text-blue-600 font-semibold">{{ $riga['collaudo'] }}</td>
                        <td class="px-4 py-2 text-center text-gray-600 font-semibold">{{ $riga['fine_vita'] }}</td>
                        <td class="px-4 py-2 text-center font-bold text-red-600">{{ $riga['totale'] }}</td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-sm text-gray-500 py-4">Nessun risultato trovato per il mese selezionato.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    </div>
</div>
