<?php

use Illuminate\Support\Str;

if (!function_exists('highlight')) {
    function highlight($text, $term)
    {
        if (!$term || strlen($term) < 3) return e($text);
        $pattern = '/' . preg_quote($term, '/') . '/i';
        return preg_replace_callback($pattern, function ($match) {
            return '<span class="bg-yellow-100 text-red-600 font-semibold">' . e($match[0]) . '</span>';
        }, e($text));
    }
}
