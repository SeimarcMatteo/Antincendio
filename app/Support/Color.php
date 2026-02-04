<?php
namespace App\Support;
class Color {
    public static function contrast(string $hex, string $dark='#111827', string $light='#FFFFFF'): string {
        $hex = ltrim($hex, '#');
        [$r,$g,$b] = [hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2))];
        $luma = (0.2126*$r + 0.7152*$g + 0.0722*$b);
        return $luma > 160 ? $dark : $light;
    }
}