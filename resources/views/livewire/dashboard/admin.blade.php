
@php
            use Illuminate\Support\Facades\Date;
            use Illuminate\Support\Facades\Auth;

            $user = Auth::user()->loadMissing('ruoli'); // evita query duplicate
            // elenco ruoli dell'utente come array di stringhe
            $ruoliNomi = $user->ruoli->pluck('nome')->map(fn($n) => trim((string)$n))->all();

            // ruolo "principale" (se ti serve ancora)
            $ruolo = $ruoliNomi[0] ?? null;

            // check robusto per Amministrazione (case-insensitive + trim)
            $haAmministrazione = collect($ruoliNomi)->contains(function ($r) {
                return strcasecmp($r, 'Amministrazione') === 0;
            });
        @endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">
    {{-- Titolo + azioni utente --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-red-700">Benvenuto {{ Auth::user()->name }}</h1>
            <p class="text-gray-500 mt-1">Controlla lo stato degli interventi e presidi</p>
        </div>

        {{-- Azioni amministrative --}}
        @if ($ruolo === 'Admin')
            <div class="flex gap-2">
                <a href="{{ route('utenti.index') }}"
                   class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">
                    👥 Gestione Utenti
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition">
                        🚪 Esci
                    </button>
                </form>
            </div>
        @endif
    </div>

    {{-- Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
        @if ($ruolo === 'Admin')
            <x-dashboard.card title="Utenti Registrati" value="{{ $numUtenti }}" icon="👥" color="from-red-600 to-red-400" />
            <x-dashboard.card title="Presidi Attivi" value="{{ $numPresidi }}" icon="🧯" color="from-yellow-600 to-yellow-400" />
            <x-dashboard.card title="Revisioni in Scadenza" value="{{ $inScadenza }}" icon="⏰" color="from-orange-600 to-orange-400" />
        @elseif ($ruolo === 'Tecnico')
            <x-dashboard.card title="Interventi di Oggi" value="5" icon="🛠️" color="from-blue-600 to-blue-400" />
            <x-dashboard.card title="Presidi da Controllare" value="38" icon="🧪" color="from-purple-600 to-purple-400" />
            <x-dashboard.card title="Note Tecniche" value="3" icon="📋" color="from-gray-600 to-gray-400" />
        @else
            <div class="col-span-full bg-gray-100 p-6 rounded-lg text-center text-gray-500">
                Nessun contenuto disponibile per il tuo ruolo.
            </div>
        @endif
    </div>

    {{-- 🔴 Fatturazione (solo per ruolo "Amministrazione") --}}
        @if($haAmministrazione)
            <div class="bg-white p-4 rounded-lg shadow border">
                <h2 class="font-bold text-lg mb-3">Fatturazione</h2>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('fatturazione.da_fatturare') }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 shadow">
                        📄 Interventi da fatturare (mese)
                    </a>
                    <a href="{{ route('fatturazione.genera') }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg bg-amber-600 text-white hover:bg-amber-700 shadow">
                        🧾 Genera fattura (singolo cliente)
                    </a>
                </div>
            </div>
        @endif

    {{-- Clienti senza mesi impostati --}}
    @if($this->clientiSenzaMesi->isNotEmpty())
        <div class="bg-yellow-100 p-4 rounded shadow border mt-8">
            <h2 class="font-bold text-lg mb-2">⚠️ Clienti con presidi ma senza mesi impostati</h2>

            @foreach($this->clientiSenzaMesi as $cliente)
                <div class="mb-4 border-b pb-2">
                    <h3 class="font-semibold">{{ $cliente->nome }}</h3>
                    <div class="grid grid-cols-6 gap-2 mt-1">
                        @for($i = 1; $i <= 12; $i++)
                            <label class="inline-flex items-center">
                                <input type="checkbox" wire:model.defer="modificaMesi.{{ $cliente->id }}.{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}" class="mr-1">
                                {{ Date::create()->month($i)->format('M') }}
                            </label>
                        @endfor
                    </div>
                    <button wire:click="salvaMesi({{ $cliente->id }})" class="btn btn-xs btn-primary mt-2">
                        💾 Salva mesi
                    </button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Interventi assegnati --}}
    <div class="bg-white shadow border p-4 rounded">
        <h2 class="text-lg font-bold mb-4">📅 Interventi assegnati</h2>
        <livewire:interventi.evadi-interventi />
    </div>

    {{-- Magazzino presidi --}}
    <div class="mt-6">
        <livewire:magazzino-presidi />
    </div>

    {{-- Statistiche --}}
    <details class="mb-4 bg-white shadow rounded-lg p-4">
        <summary class="font-semibold text-red-700 cursor-pointer">📊 Statistiche Avanzate</summary>
        <div class="mt-4">
            <livewire:statistiche-avanzate />
        </div>
    </details>
</div>
