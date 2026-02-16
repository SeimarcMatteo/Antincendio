@extends('layouts.app')

@section('content')
@php
  $tabs = [
    'colori' => ['label' => 'Colori Estintori', 'icon' => 'fa-palette'],
    'codici-articolo' => ['label' => 'Codici Articolo', 'icon' => 'fa-barcode'],
    'tipi-presidio' => ['label' => 'Tipi Idranti/Porte', 'icon' => 'fa-list'],
    'anomalie-prezzi' => ['label' => 'Prezzi Anomalie', 'icon' => 'fa-exclamation-triangle'],
    'utenti' => ['label' => 'Gestione Utenti', 'icon' => 'fa-users'],
  ];
  $tab = request()->query('tab', 'colori');
  if (!array_key_exists($tab, $tabs)) {
    $tab = 'colori';
  }
@endphp
<div class="max-w-6xl mx-auto bg-white shadow rounded-lg">
  <div class="flex items-center justify-between px-4 py-3 border-b">
    <h1 class="text-xl font-semibold">Impostazioni tecniche</h1>
    <a href="{{ url()->previous() }}" class="text-sm text-gray-600 hover:underline">
      <i class="fa fa-arrow-left mr-1"></i> Torna
    </a>
  </div>

  <div class="flex">
    <aside class="w-56 border-r p-4 space-y-2">
      @foreach($tabs as $key => $cfg)
        <a href="{{ route('admin.impostazioni', ['tab' => $key]) }}"
           class="block w-full text-left px-3 py-2 rounded {{ $tab === $key ? 'bg-red-600 text-white' : 'hover:bg-gray-100 text-gray-800' }}">
          <i class="fa {{ $cfg['icon'] }} mr-1"></i> {{ $cfg['label'] }}
        </a>
      @endforeach
    </aside>

    <main class="flex-1 p-4">
      @if($tab === 'colori')
        @livewire('tipi-estintori.imposta-colore', [], key('settings-colori-page'))
      @elseif($tab === 'codici-articolo')
        @livewire('impostazioni.codici-articolo', [], key('settings-codici-articolo-page'))
      @elseif($tab === 'tipi-presidio')
        @livewire('tipi-presidio.gestione-tipi', [], key('settings-tipi-presidio-page'))
      @elseif($tab === 'anomalie-prezzi')
        @livewire('anomalie.imposta-prezzi', [], key('settings-anomalie-prezzi-page'))
      @elseif($tab === 'utenti')
        @livewire('utenti.index', [], key('settings-utenti-page'))
      @endif
    </main>
  </div>
</div>
@endsection
