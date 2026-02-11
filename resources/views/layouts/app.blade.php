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
<style>
    @media (pointer: coarse), (max-width: 1024px) {
        .touch-ui button,
        .touch-ui [type='button'],
        .touch-ui [type='submit'],
        .touch-ui select,
        .touch-ui textarea,
        .touch-ui input[type='text'],
        .touch-ui input[type='number'],
        .touch-ui input[type='date'],
        .touch-ui input[type='datetime-local'],
        .touch-ui input[type='email'],
        .touch-ui input[type='tel'],
        .touch-ui input[type='search'] {
            min-height: 46px;
            font-size: 16px;
            line-height: 1.25;
        }

        .touch-ui input[type='checkbox'],
        .touch-ui input[type='radio'] {
            width: 1.25rem;
            height: 1.25rem;
        }

        .touch-ui input,
        .touch-ui select,
        .touch-ui textarea {
            border-radius: 0.65rem;
        }

        .touch-ui .tap-link {
            min-height: 44px;
            padding: 0.65rem 0.8rem;
        }

        .touch-ui table th,
        .touch-ui table td {
            padding-top: 0.65rem;
            padding-bottom: 0.65rem;
        }
    }
</style>

   
</head>
<body class="touch-ui bg-gray-100 text-gray-900 font-sans">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-gradient-to-r from-red-700 to-red-600 text-white shadow-md px-3 py-3 sm:px-4 sm:py-4">
            <div class="container mx-auto flex flex-col gap-3 lg:flex-row lg:justify-between lg:items-center">
                <h1 class="text-xl font-bold tracking-tight">Antincendio Lughese</h1>
                <nav class="flex flex-wrap items-center gap-2 text-sm">
                    <a href="/dashboard" class="tap-link inline-flex items-center rounded-lg bg-white/10 hover:bg-white/20 transition">
                        Dashboard
                    </a>
                    <a href="/clienti" class="tap-link inline-flex items-center rounded-lg bg-white/10 hover:bg-white/20 transition">
                        Clienti
                    </a>
                    <a href="{{ route('interventi.planning') }}" class="tap-link inline-flex items-center rounded-lg bg-white/10 hover:bg-white/20 transition">
                        📅 Planning Tecnici
                    </a>
                    <a href="{{ route('interventi.pianificazione') }}" class="tap-link inline-flex items-center rounded-lg bg-white/10 hover:bg-white/20 transition">
                        🛠 Pianifica interventi
                    </a>
                    @auth
                        @if(auth()->user()->ruoli()->where('nome','Admin')->exists())
                            <button x-data @click="$dispatch('open-admin-settings')"
                                    class="tap-link inline-flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 transition"
                                    title="Impostazioni">
                            <i class="fa fa-gear text-white"></i>
                            </button>
                        @endif
                    @endauth


                </nav>
            </div>
        </header>
            
        <main class="flex-1 container mx-auto px-3 py-4 sm:px-4 sm:py-6">
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
