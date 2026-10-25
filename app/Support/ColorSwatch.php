<?php

namespace App\Support;

class ColorSwatch
{
    public static function hex(?string $name): string
    {
        $color = mb_strtolower(trim((string) $name));

        return match (true) {
            str_contains($color, 'black'), str_contains($color, 'hitam') => '#202020',
            str_contains($color, 'navy') => '#26344c',
            str_contains($color, 'olive') => '#747452',
            str_contains($color, 'sage') => '#a8b19a',
            str_contains($color, 'mauve') => '#b58b96',
            str_contains($color, 'rose'), str_contains($color, 'pink') => '#d8a7ad',
            str_contains($color, 'plum') => '#70445d',
            str_contains($color, 'mocha') => '#8b6b59',
            str_contains($color, 'taupe') => '#a18d7d',
            str_contains($color, 'sand'), str_contains($color, 'beige') => '#d5c2a5',
            str_contains($color, 'champagne') => '#dfccb1',
            str_contains($color, 'ivory') => '#f1ebdd',
            str_contains($color, 'white'), str_contains($color, 'putih') => '#f7f7f3',
            str_contains($color, 'red'), str_contains($color, 'merah') => '#a83e43',
            str_contains($color, 'blue'), str_contains($color, 'biru') => '#557a9a',
            str_contains($color, 'green'), str_contains($color, 'hijau') => '#63806a',
            default => '#b6aca1',
        };
    }
}
