@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto bg-white shadow rounded-lg">
  <div class="flex items-center justify-between px-4 py-3 border-b">
    <h1 class="text-xl font-semibold">Impostazioni tecniche</h1>
    <a href="{{ url()->previous() }}" class="text-sm text-gray-600 hover:underline">
      <i class="fa fa-arrow-left mr-1"></i> Torna
    </a>
  </div>

  <div x-data="{ tab: 'colori' }" class="flex">
    <aside class="w-56 border-r p-4 space-y-2">
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

    <main class="flex-1 p-4">
      <div x-show="tab==='colori'">
        @livewire('tipi-estintori.imposta-colore')
      </div>
      <div x-show="tab==='utenti'">
        @livewire('utenti.index')
      </div>
    </main>
  </div>
</div>
@endsection
