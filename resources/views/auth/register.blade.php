<x-guest-layout>
    <h2 class="text-3xl font-bold text-center text-red-700 mb-6">🔥 Crea un account</h2>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label class="label">Nome</label>
            <input id="name" class="input input-bordered w-full" type="text" name="name" required autofocus />
        </div>

        <div>
            <label class="label">Email</label>
            <input id="email" class="input input-bordered w-full" type="email" name="email" required />
        </div>

        <div>
            <label class="label">Password</label>
            <input id="password" class="input input-bordered w-full" type="password" name="password" required />
        </div>

        <div>
            <label class="label">Conferma Password</label>
            <input id="password_confirmation" class="input input-bordered w-full" type="password" name="password_confirmation" required />
        </div>

        <div>
            <button class="btn bg-red-700 hover:bg-red-800 text-white w-full">Registrati</button>
        </div>
    </form>
</x-guest-layout>
