{{-- login.blade.php --}}
<x-guest-layout>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-tr from-red-800 to-yellow-500 px-4">
        <div class="w-full max-w-md bg-white p-8 shadow-2xl rounded-xl">
            <h2 class="text-3xl font-bold text-center text-red-700 mb-6">🔥 Antincendio Lughese</h2>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <input type="email" name="email" required class="input input-bordered w-full my-2" />
                <input type="password" name="password" required class="input input-bordered w-full my-2" />
                <button class="btn btn-primary w-full">Accedi</button>
            </form>
        </div>
    </div>
</x-guest-layout>
