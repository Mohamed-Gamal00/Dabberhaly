<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Strips dangerous HTML (scripts, event handlers, iframes, ...) from
 * admin-authored rich text before it is stored or rendered with v-html.
 * Only formatting/content tags survive.
 */
class HtmlSanitizer
{
    public static function purify(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', implode(',', [
            'p', 'br', 'hr',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'b', 'strong', 'i', 'em', 'u', 'small',
            'a[href|title|target]',
            'ul', 'ol', 'li',
            'blockquote', 'pre', 'code',
            'img[src|alt|title|width|height]',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'td[colspan|rowspan]', 'th[colspan|rowspan]',
            'span', 'div',
        ]));
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('AutoFormat.RemoveEmpty', true);

        return (new HTMLPurifier($config))->purify($html);
    }
}
