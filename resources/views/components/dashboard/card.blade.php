@props(['title', 'value', 'icon' => '📊', 'color' => 'from-gray-600 to-gray-400'])

<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="bg-gradient-to-br {{ $color }} p-4 text-white flex items-center justify-between">
        <div class="text-4xl">{{ $icon }}</div>
        <div class="text-right">
            <div class="text-lg font-semibold">{{ $title }}</div>
            <div class="text-3xl font-bold">{{ $value }}</div>
        </div>
    </div>
</div>
