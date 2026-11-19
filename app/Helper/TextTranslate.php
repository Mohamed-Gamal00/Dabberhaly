<?php

use Illuminate\Support\Facades\App;
use Stichoza\GoogleTranslate\GoogleTranslate;

/**
 * Function to translate HTML content while preserving tags.
 *
 * @param string $html The HTML content to translate.
 * @param string $targetLang The target language code.
 * @return string The translated HTML content.
 */
function translateWithHTMLTags($html, $targetLang = null)
{

    $targetLang = $targetLang ?? App::getLocale();

    // All callers translate Arabic-origin content (pages, FAQ,
    // product descriptions, SMS messages). Translating Arabic to
    // Arabic is a no-op — and the client would still make a live
    // Google request for it (its source is 'auto', so it never
    // short-circuits on its own), so return the original instead.
    if ($targetLang === 'ar') {
        return $html;
    }

    $translate = new GoogleTranslate($targetLang);

    if (strip_tags($html) !== $html) {
        $translatedHtml = preg_replace_callback(
            '/>([^<]+)</',
            function ($matches) use ($translate) {
                $text = $matches[1];
                $translatedText = $translate->translate(trim($text));
                return '>' . $translatedText . '<';
            },
            $html
        );
    } else {
        $translatedHtml = $translate->translate($html);
    }

    return $translatedHtml;
}
