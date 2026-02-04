<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer" />

    <!-- Chart.js da CDN (funzionante) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js"></script>

   
</head>
<body class="bg-gray-100 text-gray-900 font-sans">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-red-600 text-white shadow-md p-4">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-xl font-bold">Antincendio Lughese</h1>
                <nav>
                    <a href="/dashboard" class="hover:underline">Dashboard</a>
                    <a href="/clienti" class="ml-4 hover:underline">Clienti</a>
                    <a href="{{ route('interventi.planning') }}" class="ml-4 hover:underline">
                        📅 Planning Tecnici
                    </a>
                    <a href="{{ route('interventi.pianificazione') }}" class="ml-4 hover:underline">
                        🛠 Pianifica interventi
                    </a>
                    @auth
                        @if(auth()->user()->ruoli()->where('nome','Admin')->exists())
                            <button x-data @click="$dispatch('open-admin-settings')"
                                    class="p-2 rounded hover:bg-gray-100" title="Impostazioni">
                            <i class="fa fa-gear text-gray-600"></i>
                            </button>
                        @endif
                    @endauth


                </nav>
            </div>
        </header>
            
        <main class="flex-1 container mx-auto px-4 py-6">
            {{-- Se la variabile $slot è disponibile, usiamo Livewire layout --}}
            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset


        </main>

        {{-- Footer --}}
        <footer class="bg-white shadow-inner p-4 text-center text-sm text-gray-500">
            &copy; {{ date('Y') }} Antincendio Lughese. Tutti i diritti riservati.
        </footer>
<!-- Toast (Livewire + Alpine)  -------------------------------------------->
<div
    x-data="{
        show: false,
        msg: '',
        type: 'info',
        // colore dinamico in base al tipo
        bg() {
            return {
                info:    'bg-slate-600',
                success: 'bg-green-600',
                warning: 'bg-amber-600',
                error:   'bg-red-600',
            }[this.type] ?? 'bg-slate-600';
        }
    }"
    x-show="show"
    x-transition.opacity.scale.duration.300
    x-cloak
    class="fixed inset-0 flex items-center justify-center pointer-events-none"
    @toast.window="
        msg  = $event.detail.message;
        let t = ($event.detail.type || 'info').toLowerCase();
        type = ['info', 'success', 'warning', 'error'].includes(t) ? t : 'info';
        show = true;
        setTimeout(() => show = false, 3000);
    "
>
    <div
        class="pointer-events-auto text-white font-semibold px-6 py-3 rounded-lg shadow-lg"
        :class="bg()"
        x-text="msg">
    </div>
</div>


<!-- /Toast ---------------------------------------------------------------->
        @auth
        @if(auth()->user()->ruoli()->where('nome','Admin')->exists())
            @include('admin.partials.settings-modal')
        @endif
        @endauth

    @livewireScripts
    @stack('scripts')
    </body>
</html>