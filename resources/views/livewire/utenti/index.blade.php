<div class="p-4">
    <h1 class="text-2xl font-bold mb-4 text-red-700">Gestione Utenti</h1>

    <div class="flex justify-between items-center mb-4">
        <input type="text" wire:model.debounce.500ms="search" placeholder="Cerca utenti..." class="input input-bordered w-full max-w-xs" />

        <a href="{{ route('utenti.form') }}" class="btn btn-primary">+ Nuovo Utente</a>
    </div>

    <table class="table w-full bg-white rounded shadow">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Email</th>
                <th>Ruolo</th>
                <th>Colore</th>
                <th>Profilo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($utenti as $utente)
                <tr>
                    <td>{{ $utente->name }}</td>
                    <td>{{ $utente->email }}</td>
                    <td>{{ $utente->ruoli->pluck('nome')->join(', ') }}</td>
                    <td>
                        <span class="inline-block w-6 h-6 rounded-full" style="background-color: {{ $utente->colore_ruolo }}"></span>
                    </td>
                    <td>
                        <img src="{{ $utente->immagineProfiloUrl() }}" class="w-10 h-10 rounded-full object-cover" />
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">
        {{ $utenti->links() }}
    </div>
</div>
