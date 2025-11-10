<div
  x-data="{ open:false, tab:'colori' }"
  x-on:open-admin-settings.window="open = true; $nextTick(()=> $refs.first?.focus())"
  x-on:keydown.escape.window="open=false"
  x-effect="document.documentElement.classList.toggle('overflow-hidden', open)"
  x-show="open"
  x-cloak
  class="fixed inset-0 z-50"
  role="dialog"
  aria-modal="true"
  :aria-labelledby="'admin-settings-title'"
>
  <!-- Backdrop -->
  <div class="absolute inset-0 bg-black/40" @click="open=false" aria-hidden="true"></div>

  <!-- Wrapper responsivo -->
  <div class="relative mx-auto h-full w-full sm:my-8 sm:h-auto sm:w-auto sm:max-w-5xl">
    <!-- Dialog -->
    <div class="flex h-full sm:h-auto flex-col bg-white shadow-xl ring-1 ring-black/10 sm:rounded-2xl sm:max-h-[90vh]">
      <!-- Header sticky -->
      <header class="sticky top-0 z-10 flex items-center justify-between gap-2 px-4 py-3 border-b bg-white">
        <h3 id="admin-settings-title" class="text-lg font-semibold">Impostazioni tecniche</h3>
        <div class="flex items-center gap-2">
          <a href="{{ route('admin.impostazioni') }}" class="text-sm text-gray-600 hover:underline">
            Vai alla pagina
          </a>
          <button x-ref="first" class="p-2 hover:bg-gray-100 rounded" @click="open=false" title="Chiudi">
            <i class="fa fa-times"></i>
          </button>
        </div>
      </header>

      <!-- Corpo: layout adattivo -->
      <div class="flex min-h-0 flex-1 sm:flex-row flex-col">
        <!-- Tabs verticali (desktop) -->
        <aside class="hidden sm:block w-56 border-r overflow-y-auto">
          <nav class="p-3 space-y-1">
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
          </nav>
        </aside>

        <!-- Tabs orizzontali (mobile) -->
        <nav class="sm:hidden border-b px-3 py-2 flex gap-2 overflow-x-auto">
          <button class="px-3 py-1.5 rounded text-sm whitespace-nowrap"
                  :class="tab==='colori' ? 'bg-red-600 text-white' : 'bg-gray-100'"
                  @click="tab='colori'">
            Colori Estintori
          </button>
          <button class="px-3 py-1.5 rounded text-sm whitespace-nowrap"
                  :class="tab==='utenti' ? 'bg-red-600 text-white' : 'bg-gray-100'"
                  @click="tab='utenti'">
            Gestione Utenti
          </button>
        </nav>

        <!-- Contenuto scrollabile -->
        <section class="flex-1 min-h-0 overflow-y-auto p-4">
          <div x-show="tab==='colori'" x-transition>
            @livewire('tipi-estintori.imposta-colore', [], key('settings-colori'))
          </div>
          <div x-show="tab==='utenti'" x-transition>
            @livewire('utenti.index', [], key('settings-utenti'))
          </div>
        </section>
      </div>

      <!-- Footer sticky -->
      <footer class="sticky bottom-0 px-4 py-2 border-t bg-white flex justify-end">
        <button class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300" @click="open=false">Chiudi</button>
      </footer>
    </div>
  </div>
</div>
