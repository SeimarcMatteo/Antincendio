<div class="space-y-4">
  <h2 class="text-xl font-semibold">Colore per tipologia estintore</h2>

  <div class="overflow-x-auto rounded-xl border">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-3 py-2 text-left">Sigla</th>
          <th class="px-3 py-2 text-left">Descrizione</th>
          <th class="px-3 py-2 text-left">Tipo</th>
          <th class="px-3 py-2 text-right">Kg</th>
          <th class="px-3 py-2">Colore</th>
        </tr>
      </thead>

      <tbody class="divide-y">
        @foreach($tipi as $t)
          @php
            $coloreId = $selezioni[$t->id] ?? null;
            $hex  = $coloreId ? ($hexById[$coloreId]  ?? '#9CA3AF') : '#9CA3AF';
            $nome = $coloreId ? ($nomeById[$coloreId] ?? '—')       : '—';
          @endphp

          <tr wire:key="tipo-{{ $t->id }}" style="border-left: 6px solid {{ $hex }};">
            <td class="px-3 py-2 font-mono">{{ $t->sigla }}</td>
            <td class="px-3 py-2">{{ $t->descrizione }}</td>
            <td class="px-3 py-2">{{ $t->tipo }}</td>
            <td class="px-3 py-2 text-right">{{ $t->kg }}</td>

            <td class="px-3 py-2">
              <div class="relative" x-data="{ open:false }">
                <button type="button"
                        class="w-56 justify-start inline-flex items-center gap-2 px-3 py-1.5 rounded-md border border-gray-300 text-sm hover:bg-gray-50"
                        x-on:click="open = !open"
                        x-on:keydown.escape.window="open=false"
                        :aria-expanded="open"
                        aria-haspopup="listbox">
                  <span class="inline-block w-4 h-4 rounded-full ring-1 ring-black/10"
                        style="background-color: {{ $hex }}"></span>
                  <span>{{ $nome }}</span>
                </button>

                <div x-show="open" x-transition
                     x-cloak
                     x-on:click.outside="open=false"
                     class="absolute z-10 mt-1 w-56 max-h-64 overflow-auto rounded-md border bg-white shadow">

                  {{-- Nessuno --}}
                  <button type="button"
                          class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50"
                          wire:click="setColore({{ $t->id }}, null)"
                          x-on:click="open=false">
                    <span class="inline-block w-4 h-4 rounded-full ring-1 ring-black/10 bg-gray-300"></span>
                    <span>— nessuno —</span>
                  </button>

                  <div class="border-t my-1"></div>

                  @foreach($colori as $c)
                    <button type="button"
                            class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50"
                            wire:click="setColore({{ $t->id }}, {{ $c->id }})"
                            x-on:click="open=false">
                      <span class="inline-block w-4 h-4 rounded-full ring-1 ring-black/10"
                            style="background-color: {{ $c->hex ?? '#9CA3AF' }}"></span>
                      <span>{{ $c->nome }}</span>
                    </button>
                  @endforeach
                </div>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
