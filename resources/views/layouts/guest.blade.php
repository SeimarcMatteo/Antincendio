<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Login | Antincendio Lughese</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gradient-to-br from-red-800 to-yellow-500 text-white min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-md bg-white text-gray-800 rounded-xl shadow-xl p-6">
        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
