<div class="space-y-4">
    <div class="flex items-center gap-2">
        <button class="px-3 py-1 rounded text-sm border {{ $categoria === 'Idrante' ? 'bg-red-600 text-white border-red-600' : 'hover:bg-gray-100' }}"
                wire:click="$set('categoria','Idrante')">
            Idranti
        </button>
        <button class="px-3 py-1 rounded text-sm border {{ $categoria === 'Porta' ? 'bg-red-600 text-white border-red-600' : 'hover:bg-gray-100' }}"
                wire:click="$set('categoria','Porta')">
            Porte
        </button>
    </div>

    <div class="flex items-end gap-2">
        <div class="flex-1">
            <label class="text-sm font-medium">Nuova tipologia {{ strtolower($categoria) }}</label>
            <input type="text" wire:model.defer="nuovoNome" class="input input-bordered w-full" placeholder="Es. UNI 45, UNI 70, NASPO...">
        </div>
        <button class="btn btn-primary btn-sm" wire:click="salva">💾 Salva</button>
    </div>

    <div class="border rounded bg-white">
        <div class="grid grid-cols-2 gap-4 border-b p-2 text-sm font-semibold text-gray-600">
            <div>Nome</div>
            <div class="text-right">Azioni</div>
        </div>
        @forelse($tipi as $t)
            <div class="grid grid-cols-2 gap-4 items-center p-2 border-b last:border-b-0 text-sm">
                <div>{{ $t->nome }}</div>
                <div class="text-right">
                    <button class="text-red-600 hover:text-red-800" wire:click="elimina({{ $t->id }})">Elimina</button>
                </div>
            </div>
        @empty
            <div class="p-3 text-sm text-gray-500">Nessuna tipologia configurata.</div>
        @endforelse
    </div>
</div>
