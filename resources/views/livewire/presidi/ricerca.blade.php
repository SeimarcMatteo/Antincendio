<div class="max-w-xl mx-auto bg-white shadow p-6 rounded-lg space-y-4">
    <h2 class="text-lg font-bold text-red-600">Ricerca Presidi</h2>

    <div class="space-y-2">
        <label class="block font-semibold">Cliente</label>
        <select wire:model="clienteId" class="w-full border-gray-300 rounded shadow-sm">
            <option value="">-- Seleziona Cliente --</option>
            @foreach($clienti as $cliente)
                <option value="{{ $cliente->id }}">{{ $cliente->nome }}</option>
            @endforeach
        </select>
    </div>

    @if ($sedi)
        <div class="space-y-2">
            <label class="block font-semibold">Sede</label>
            <select wire:model="sedeId" class="w-full border-gray-300 rounded shadow-sm">
                <option value="">-- Seleziona Sede --</option>
                @foreach($sedi as $sede)
                    <option value="{{ $sede->id }}">{{ $sede->nome }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="text-sm text-red-500">{{ session('error') }}</div>
    @endif

    <button wire:click="vaiAGestionePresidi"
        class="bg-red-600 text-white px-4 py-2 rounded shadow hover:bg-red-700 transition">
        Vai alla Gestione Presidi
    </button>
</div>
