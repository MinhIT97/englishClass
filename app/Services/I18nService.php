<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Tiny i18n helper for strings that are NOT stored as Laravel
 * translations (e.g. dynamic emails, push notifications, AI tutor
 * prompts). Loads JSON dictionaries on demand and caches them in
 * memory.
 *
 * For UI strings, prefer Laravel's built-in __() with files in
 * lang/{locale}/. This service is for code-generated copy.
 */
class I18nService
{
    private const SUPPORTED = ['vi', 'en'];

    public function get(string $key, string $locale = 'vi', array $replace = []): string
    {
        $locale = in_array($locale, self::SUPPORTED, true) ? $locale : 'vi';
        $dict = $this->loadDictionary($locale);

        $text = $dict[$key] ?? $key;

        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string) $v, $text);
        }
        return $text;
    }

    private function loadDictionary(string $locale): array
    {
        static $cache = [];
        if (isset($cache[$locale])) return $cache[$locale];

        $path = resource_path("i18n/{$locale}.json");
        if (! File::exists($path)) {
            return $cache[$locale] = [];
        }

        return $cache[$locale] = json_decode(File::get($path), true) ?: [];
    }
}