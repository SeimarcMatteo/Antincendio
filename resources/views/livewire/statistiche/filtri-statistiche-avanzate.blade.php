<div class="flex gap-4 mb-4 items-end">
    <div>
        <label class="text-sm">Da:</label>
        <input type="date" wire:model.lazy="dataDa" class="input input-bordered">
    </div>
    <div>
        <label class="text-sm">A:</label>
        <input type="date" wire:model.lazy="dataA" class="input input-bordered">
    </div>
    <div>
        <button wire:click="aggiorna" class="btn btn-primary">
            🔄 Aggiorna grafico
        </button>
    </div>
</div>
