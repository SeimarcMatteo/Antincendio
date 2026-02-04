<x-guest-layout>
    <h2 class="text-3xl font-bold text-center text-red-700 mb-6">🔥 Antincendio Lughese</h2>

    @if (session('status'))
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label class="label">Email</label>
            <input id="email" class="input input-bordered w-full" type="email" name="email" required autofocus />
        </div>

        <div>
            <label class="label">Password</label>
            <input id="password" class="input input-bordered w-full" type="password" name="password" required />
        </div>

        <div class="flex items-center justify-between">
            <label class="label cursor-pointer">
                <input type="checkbox" class="checkbox checkbox-sm" name="remember">
                <span class="ml-2 text-sm">Ricordami</span>
            </label>

            <a href="{{ route('password.request') }}" class="text-sm text-red-700 hover:underline">Password dimenticata?</a>
        </div>

        <div>
            <button class="btn bg-red-700 hover:bg-red-800 text-white w-full">Accedi</button>
        </div>
    </form>
</x-guest-layout>
