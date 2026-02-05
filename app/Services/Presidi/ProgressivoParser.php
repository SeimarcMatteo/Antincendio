<?php

namespace App\Services\Presidi;

class ProgressivoParser
{
    /**
     * Normalizza un progressivo testuale.
     * Ritorna: ['label' => '13 BIS', 'num' => 13, 'suffix' => 'BIS']
     */
    public static function parse(?string $value): ?array
    {
        $raw = trim((string) $value);
        if ($raw === '') return null;

        $raw = preg_replace('/\s+/', ' ', $raw);
        $rawUp = mb_strtoupper($raw);

        if (!preg_match('/^(\d+)/', $rawUp, $m)) {
            return null;
        }

        $numStr = $m[1];
        $num = (int) $numStr;
        $rest = trim(mb_substr($rawUp, strlen($numStr)));
        $suffix = $rest !== '' ? preg_replace('/[^A-Z0-9]+/u', '', $rest) : '';

        $label = $suffix !== '' ? ($numStr . ' ' . $suffix) : (string) $numStr;

        return [
            'label'  => $label,
            'num'    => $num,
            'suffix' => $suffix !== '' ? $suffix : null,
        ];
    }
}
