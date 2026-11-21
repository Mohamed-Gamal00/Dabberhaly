<?php

namespace App\Helper;

/**
 * Arabic letter normalization for product search.
 *
 * Why a REGEXP pattern instead of normalizing the query and using LIKE:
 * normalizing only the query (e.g. "احمد") can never match the stored
 * text ("أحمد") with LIKE, because أ and ا are different characters
 * under the utf8mb4_unicode_ci collation. Instead the query is turned
 * into a REGEXP pattern where every variant letter becomes a character
 * class ([اأإآ], [ةه], [ىي]), so the pattern matches the stored text
 * whichever variant it uses. No database or migration change is needed:
 * LIKE '%...%' already scans the whole table, so REGEXP costs the same
 * (no index is used either way).
 *
 * Diacritics (tashkeel) and tatweel are stripped from the query;
 * stored product names essentially never contain them, and the _ci
 * collation already ignores diacritics for comparison purposes.
 * The pattern is passed to MySQL as a bound parameter, so user input
 * can never break out of the pattern.
 */
final class ArabicSearch
{
    /** Letters users interchange, mapped to the class matching any of them. */
    private const VARIANT_CLASSES = [
        'ا' => '[اأإآٱ]',
        'أ' => '[اأإآٱ]',
        'إ' => '[اأإآٱ]',
        'آ' => '[اأإآٱ]',
        'ٱ' => '[اأإآٱ]',
        'ة' => '[ةه]',
        'ه' => '[ةه]',
        'ى' => '[ىي]',
        'ي' => '[ىي]',
    ];

    /** Regex metacharacters that must be escaped when they come from user input. */
    private const REGEX_META = ['\\', '.', '^', '$', '|', '?', '*', '+', '(', ')', '[', ']', '{', '}'];

    /**
     * Build a MySQL REGEXP pattern matching $term regardless of which
     * Arabic letter variant the stored text uses. Returns null when
     * nothing searchable is left (e.g. the term was only diacritics).
     */
    public static function toRegExpPattern(string $term): ?string
    {
        // Strip tatweel (U+0640) and diacritics (U+064B–U+0655, U+0670).
        $term = preg_replace('/[\x{0640}\x{064B}-\x{0655}\x{0670}]/u', '', $term);

        if ($term === null || trim($term) === '') {
            return null;
        }

        $pattern = '';

        $chars = preg_split('//u', $term, -1, PREG_SPLIT_NO_EMPTY);

        if ($chars === false) {
            return null;
        }

        foreach ($chars as $char) {
            if (isset(self::VARIANT_CLASSES[$char])) {
                $pattern .= self::VARIANT_CLASSES[$char];
            } elseif (in_array($char, self::REGEX_META, true)) {
                $pattern .= '\\' . $char;
            } else {
                $pattern .= $char;
            }
        }

        return $pattern;
    }
}
