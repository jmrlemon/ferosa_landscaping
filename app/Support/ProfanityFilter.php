<?php

namespace App\Support;

final class ProfanityFilter
{
    /**
     * Keep this list deliberately conservative. Whole-word matching prevents
     * short terms from corrupting ordinary words such as "classic" or
     * "assistant".
     *
     * @var list<string>
     */
    private const TERMS = [
        'motherfucker',
        'putang ina',
        'tang ina',
        'king ina',
        'putangina',
        'tangina',
        'kingina',
        'tarantado',
        'bullshit',
        'asshole',
        'dickhead',
        'fucking',
        'fucker',
        'fucked',
        'bitches',
        'bastards',
        'gagong',
        'gagang',
        'tangang',
        'bobong',
        'pakshet',
        'punyeta',
        'bwisit',
        'bwesit',
        'kantot',
        'bitch',
        'bastard',
        'shitty',
        'leche',
        'lintik',
        'gago',
        'gaga',
        'tanga',
        'bobo',
        'ulol',
        'yawa',
        'pucha',
        'puta',
        'fuck',
        'shit',
        'cunt',
    ];

    public static function censor(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $filtered = preg_replace(self::pattern(), '****', $text);

        return is_string($filtered) ? $filtered : $text;
    }

    private static function pattern(): string
    {
        static $pattern;

        if (is_string($pattern)) {
            return $pattern;
        }

        $terms = array_map(
            static fn (string $term): string => str_replace(' ', '\\s+', preg_quote($term, '~')),
            self::TERMS
        );

        return $pattern = '~(?<![\\p{L}\\p{N}])(?:'.implode('|', $terms).')(?![\\p{L}\\p{N}])~iu';
    }
}
