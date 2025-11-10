<div x-data="{ open:false, tab:'colori' }"
     x-on:open-admin-settings.window="open = true"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center">

  <div class="absolute inset-0 bg-black/40" @click="open=false"></div>

  <div class="relative bg-white w-full max-w-5xl rounded-xl shadow-xl overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b">
      <h3 class="text-lg font-semibold">Impostazioni tecniche</h3>
      <div class="space-x-2">
        <a href="{{ route('admin.impostazioni') }}" class="text-sm text-gray-600 hover:underline">
          Vai alla pagina
        </a>
        <button class="p-2 hover:bg-gray-100 rounded" @click="open=false">
          <i class="fa fa-times"></i>
        </button>
      </div>
    </div>

    <div class="flex">
      <aside class="w-52 border-r p-3 space-y-1">
        <button class="w-full text-left px-3 py-2 rounded"
                :class="tab==='colori' ? 'bg-red-600 text-white' : 'hover:bg-gray-100'"
                @click="tab='colori'">
          <i class="fa fa-palette mr-1"></i> Colori Estintori
        </button>
        <button class="w-full text-left px-3 py-2 rounded"
                :class="tab==='utenti' ? 'bg-red-600 text-white' : 'hover:bg-gray-100'"
                @click="tab='utenti'">
          <i class="fa fa-users mr-1"></i> Gestione Utenti
        </button>
      </aside>

      <section class="flex-1 p-4">
        <div x-show="tab==='colori'">
          @livewire('tipi-estintori.imposta-colore')
        </div>
        <div x-show="tab==='utenti'">
          @livewire('utenti.index')
        </div>
      </section>
    </div>
  </div>
</div>
