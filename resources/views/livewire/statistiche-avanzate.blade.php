<div class="max-w-7xl mx-auto p-4 space-y-6">
<livewire:statistiche.filtri-statistiche-avanzate />

<div>
    <label class="block text-sm font-medium mb-1">Seleziona grafico</label>
    <select wire:model="graficoSelezionato" class="select select-bordered w-full max-w-xs">
        <option value="tecnici">Interventi per Tecnico</option>
        <option value="clienti">Interventi per Cliente</option>
        <option value="durata">Durata Media per Tecnico</option>
        <option value="categoria">Presidi per Categoria</option>
        <option value="trend">Trend Mensile Interventi</option>
        <option value="esiti">Esiti Interventi</option>
    </select>
</div>

@switch($graficoSelezionato)
    @case('clienti')
        <livewire:statistiche.grafico-interventi-clienti />
        @break

    @case('durata')
        <livewire:statistiche.grafico-durata-media-tecnici />
        @break

    @case('categoria')
        <livewire:statistiche.grafico-presidi-categoria />
        @break

    @case('trend')
        <livewire:statistiche.grafico-trend-interventi />
        @break

    @case('esiti')
        <livewire:statistiche.grafico-esiti-interventi />
        @break

    @default
        <livewire:statistiche.grafico-statistiche-tecnici />
@endswitch
</div>
