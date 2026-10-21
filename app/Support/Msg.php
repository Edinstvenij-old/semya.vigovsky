<?php

namespace App\Support;

/**
 * A translatable message: a Russian source key plus parameters, rendered later
 * in the language of whoever receives it (e.g. one notification goes out in
 * Russian to one relative and in Ukrainian to another).
 *
 * Parameters may themselves be Msg arrays (e.g. a check-in label) — they are
 * translated too; plain strings (names, titles typed by users) are kept as is.
 */
class Msg
{
    public static function t(string $key, array $params = []): array
    {
        return ['__key' => $key, '__params' => $params];
    }

    public static function render(string|array|null $message, ?string $locale = null): ?string
    {
        if (! is_array($message)) {
            return $message;
        }

        $params = array_map(
            fn ($value) => is_array($value) ? self::render($value, $locale) : $value,
            $message['__params'] ?? [],
        );

        return __($message['__key'], $params, $locale);
    }
}
