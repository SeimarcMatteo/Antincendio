<div class="w-64 bg-red-700 text-white flex flex-col p-4 space-y-4 shadow-md">
    <h1 class="text-xl font-bold mb-6">🔥 Antincendio</h1>

    @if ($ruolo === 'Admin')
        <a href="{{ route('admin.dashboard') }}" class="hover:bg-red-800 p-2 rounded">Dashboard</a>
        <a href="#" class="hover:bg-red-800 p-2 rounded">Clienti</a>
        <a href="#" class="hover:bg-red-800 p-2 rounded">Sedi</a>
        <a href="#" class="hover:bg-red-800 p-2 rounded">Presidi</a>
        <a href="#" class="hover:bg-red-800 p-2 rounded">Utenti</a>
    @elseif ($ruolo === 'Tecnico')
        <a href="{{ route('tecnico.dashboard') }}" class="hover:bg-red-800 p-2 rounded">Dashboard</a>
        <a href="#" class="hover:bg-red-800 p-2 rounded">Interventi</a>
    @endif

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="mt-auto bg-red-800 hover:bg-red-900 p-2 rounded text-white w-full">Logout</button>
    </form>
</div>
