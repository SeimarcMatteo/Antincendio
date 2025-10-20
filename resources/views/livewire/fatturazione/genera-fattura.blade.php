<x-app-layout>
<div class="space-y-4">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
    <div>
      <label class="text-sm font-medium">Cliente</label>
      <select class="mt-1 border rounded p-2 w-full" wire:model="clienteId">
        <option value="">— Seleziona —</option>
        @foreach($clienti as $c)
          <option value="{{ $c->id }}">{{ $c->nome }}</option>
        @endforeach
      </select>
      @error('clienteId') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
    </div>
    <div>
      <label class="text-sm font-medium">Mese</label>
      <select class="mt-1 border rounded p-2" wire:model="mese">
        @for($m=1;$m<=12;$m++)
          <option value="{{ $m }}">{{ \Carbon\Carbon::create(null,$m)->translatedFormat('F') }}</option>
        @endfor
      </select>
      @error('mese') <div class="text-xs text-red-600">{{ $message }}</div> @enderror
    </div>
    <div>
      <label class="text-sm font-medium">Anno</label>
      <input type="number" class="mt-1 border rounded p-2 w-full" wire:model="anno">
    </div>
    <div>
      <label class="text-sm font-medium">Data documento</label>
      <input type="date" class="mt-1 border rounded p-2 w-full" wire:model="dataDocumento">
    </div>
    <div class="md:col-span-4">
      <button class="px-4 py-2 rounded bg-red-600 text-white" wire:click="generaPreview">
        Genera anteprima
      </button>
    </div>
  </div>

  @if($preview)
    <div class="rounded-2xl border p-4">
      <div class="flex justify-between items-center mb-3">
        <div class="font-semibold text-lg">{{ $preview['cliente']['nome'] ?? '' }}</div>
        <div class="text-right font-semibold">
          Totale: € {{ number_format($preview['totale'] ?? 0, 2, ',', '.') }}
        </div>
      </div>

      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-gray-600">
            <th class="py-1">Sigla</th>
            <th class="py-1">Descrizione</th>
            <th class="py-1">Q.tà</th>
            <th class="py-1">Prezzo</th>
            <th class="py-1 text-right">Totale</th>
          </tr>
        </thead>
        <tbody>
          @foreach($preview['righe'] as $r)
            <tr class="border-t">
              <td class="py-1">{{ $r['sigla'] }}</td>
              <td class="py-1">{{ $r['descrizione'] }}</td>
              <td class="py-1">{{ $r['qty'] }}</td>
              <td class="py-1">
                @if($r['missing_price'])
                  <span class="px-2 py-0.5 text-xs rounded bg-yellow-100 text-yellow-800">Prezzo mancante</span>
                @else
                  € {{ number_format($r['unit_price'],2,',','.') }}
                @endif
              </td>
              <td class="py-1 text-right">€ {{ number_format($r['total'],2,',','.') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <div class="mt-4 text-right space-y-2">
        @error('creazione')
            <div class="text-red-600 text-sm">{{ $message }}</div>
        @enderror
        @if (session()->has('ok'))
            <div class="text-emerald-700 text-sm">{{ session('ok') }}</div>
        @endif

        <button
            class="px-4 py-2 rounded bg-emerald-600 text-white disabled:opacity-50"
            wire:click="creaFattura"
            @disabled($preview['blocking_missing_price'] ?? true)
            title="{{ ($preview['blocking_missing_price'] ?? true)
                ? 'Completa i prezzi prima di creare la fattura'
                : 'Crea il documento in Business' }}">
            Crea fattura in Business
        </button>
        </div>

    </div>
  @endif
</div>

</x-app-layout>