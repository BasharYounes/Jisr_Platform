<?php

namespace App\Services\MarketAnalysis;

use Illuminate\Support\Str;

final class MarketTextNormalizer
{
    /**
     * Technical names containing punctuation must be converted
     * before general punctuation is removed.
     */
    private const TECHNICAL_TOKEN_PATTERNS = [
        '/(?<![\p{L}\p{N}])asp\s*\.?\s*net(?![\p{L}\p{N}])/iu' => ' aspnet ',

        '/(?<![\p{L}\p{N}])\.?\s*net(?![\p{L}\p{N}])/iu' => ' dotnet ',

        '/(?<![\p{L}\p{N}])c\s*\+\s*\+(?![\p{L}\p{N}])/iu' => ' cpp ',

        '/(?<![\p{L}\p{N}])c\s*#(?![\p{L}\p{N}])/iu' => ' csharp ',

        '/(?<![\p{L}\p{N}])node\s*\.?\s*js(?![\p{L}\p{N}])/iu' => ' nodejs ',

        '/(?<![\p{L}\p{N}])next\s*\.?\s*js(?![\p{L}\p{N}])/iu' => ' nextjs ',

        '/(?<![\p{L}\p{N}])vue\s*\.?\s*js(?![\p{L}\p{N}])/iu' => ' vuejs ',

        '/(?<![\p{L}\p{N}])react\s*\.?\s*js(?![\p{L}\p{N}])/iu' => ' reactjs ',

        '/(?<![\p{L}\p{N}])express\s*\.?\s*js(?![\p{L}\p{N}])/iu' => ' expressjs ',
    ];

    public function normalize(string $text): string
    {
        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $text = Str::lower($text);

        foreach (
            self::TECHNICAL_TOKEN_PATTERNS as $pattern => $replacement
        ) {
            $text = preg_replace(
                $pattern,
                $replacement,
                $text
            );
        }

        // Normalize Arabic letter variants.
        $text = str_replace(
            ['أ', 'إ', 'آ', 'ٱ', 'ى', 'ة'],
            ['ا', 'ا', 'ا', 'ا', 'ي', 'ه'],
            $text
        );

        // Normalize separators after protecting technical tokens.
        $text = str_replace(
            [
                '/',
                '\\',
                '|',
                '+',
                '#',
                '.',
                ',',
                ';',
                ':',
                '(',
                ')',
                '[',
                ']',
            ],
            ' ',
            $text
        );

        $text = preg_replace(
            '/[^\p{Arabic}\p{L}\p{N}\s]/u',
            ' ',
            $text
        );

        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
