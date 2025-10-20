{{-- resources/views/livewire/fatturazione/elenco-da-fatturare.blade.php --}}
<x-app-layout>
<div class="space-y-4">
  <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
    <div>
      <label class="text-sm font-medium">Mese</label>
      <select class="mt-1 border rounded p-2 w-full" wire:model="mese">
        @for($m=1;$m<=12;$m++)
          <option value="{{ $m }}">{{ \Carbon\Carbon::create(null,$m)->translatedFormat('F') }}</option>
        @endfor
      </select>
    </div>
    <div>
      <label class="text-sm font-medium">Anno</label>
      <input type="number" class="mt-1 border rounded p-2 w-full" wire:model="anno">
    </div>
    <div>
      <label class="text-sm font-medium">Data documento</label>
      <input type="date" class="mt-1 border rounded p-2 w-full" wire:model="dataDocumento">
    </div>
    <div class="md:col-span-2">
      <button class="px-4 py-2 rounded bg-red-600 text-white" wire:click="genera">
        Genera elenco
      </button>
    </div>
  </div>

  @error('creazione')
    <div class="text-red-600 text-sm">{{ $message }}</div>
  @enderror

  @if (session()->has('ok'))
    <div class="text-emerald-700 text-sm">{{ session('ok') }}</div>
  @endif

  @if($previews)
    <div class="flex justify-between items-center">
      <div class="font-semibold text-lg">Totale generale: € {{ number_format($totaleGenerale,2,',','.') }}</div>
      <button
        class="px-4 py-2 rounded bg-emerald-600 text-white disabled:opacity-50"
        wire:click="creaTutte"
        @disabled(collect($previews)->every(fn($p)=>$p['blocking_missing_price'] ?? true))>
        Crea TUTTE le fatture senza prezzi mancanti
      </button>
    </div>

    <div class="space-y-5">
      @foreach($previews as $clienteId => $pv)
        <div class="rounded-2xl border p-4">
          <div class="flex justify-between items-center mb-2">
            <div>
              <div class="font-semibold text-lg">{{ $pv['cliente']['nome'] ?? ('Cliente #'.$clienteId) }}</div>
              <div class="text-xs text-gray-500">
                Tipo: {{ strtoupper($pv['cliente']['fatturazione_tipo'] ?? '-') }}
                @if(($pv['cliente']['fatturazione_tipo'] ?? '') === 'annuale' && !empty($pv['cliente']['mese_fatturazione']))
                  — Mese fatturazione: {{ $pv['cliente']['mese_fatturazione'] }}
                @endif
              </div>
            </div>
            <div class="text-right">
              <div class="font-semibold">Totale: € {{ number_format($pv['totale'] ?? 0,2,',','.') }}</div>
              @if($pv['blocking_missing_price'] ?? false)
                <div class="text-xs text-yellow-700">⚠️ Prezzi mancanti: completa i listini</div>
              @endif
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
              @foreach($pv['righe'] as $r)
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

          <div class="mt-3 text-right">
            <button
              class="px-3 py-1.5 rounded bg-emerald-600 text-white disabled:opacity-50"
              wire:click="creaFatturaCliente({{ $clienteId }})"
              @disabled($pv['blocking_missing_price'] ?? true)
            >
              Crea fattura per questo cliente
            </button>
            @if (session()->has('err_'.$clienteId))
              <div class="text-red-600 text-xs mt-1">{{ session('err_'.$clienteId) }}</div>
            @endif
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>
</x-app-layout>