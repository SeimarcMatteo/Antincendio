<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Benvenuto | Antincendio Lughese</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('layouts.partials.app-icons')
   
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />
</head>
<body class="bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 font-sans antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">
        <!-- Logo / Icona -->
        <div class="mb-6">
            <i class="fas fa-fire-extinguisher text-6xl text-red-600"></i>
        </div>

        <!-- Titolo -->
        <h1 class="text-3xl font-bold mb-2 text-center text-red-600">Antincendio Lughese</h1>
        <p class="text-gray-600 dark:text-gray-400 mb-8 text-center max-w-md">
            Gestione presidi, interventi e scadenze tecniche. Accedi per iniziare.
        </p>

        <!-- Pulsante Login unico -->
        <a href="{{ route('login') }}"
           class="bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-xl text-center shadow-md w-64">
            🔐 Accedi al portale
        </a>

        <!-- Footer -->
        <p class="mt-10 text-sm text-gray-400 dark:text-gray-500 text-center">
            Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})
        </p>
    </div>
</body>
</html>
