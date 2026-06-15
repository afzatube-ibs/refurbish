<?php

namespace App\Services\OpenCart;

/**
 * Resolve POIP / option image paths from connector API payloads (read-only).
 * Ported from IBS ERP — DB enrichment omitted; connector supplies POIP images.
 */
class OpenCartOptionImageResolver
{
    /** @var array<int, string> */
    public const PAYLOAD_KEYS = [
        'option_image_path',
        'option_image',
        'image',
        'optionimage',
        'optionImage',
        'thumb',
        'image_path',
    ];

    /**
     * @param  array<string, mixed>  $option
     */
    public static function extractFromPayload(array $option): ?string
    {
        foreach (self::PAYLOAD_KEYS as $key) {
            if (! array_key_exists($key, $option)) {
                continue;
            }

            $value = trim((string) $option[$key]);

            if ($value !== '' && ! self::looksLikeAbsoluteUrl($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    public static function extractProductPath(array $product): ?string
    {
        foreach (['image_path', 'image', 'thumb'] as $key) {
            if (! array_key_exists($key, $product)) {
                continue;
            }

            $value = trim((string) $product[$key]);

            if ($value !== '' && ! self::looksLikeAbsoluteUrl($value)) {
                return $value;
            }
        }

        return null;
    }

    protected static function looksLikeAbsoluteUrl(string $value): bool
    {
        return (bool) preg_match('#^https?://#i', $value) || str_starts_with($value, '//');
    }
}
