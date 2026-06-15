<?php

namespace App\Services\OpenCart;

class OpenCartMediaUrlResolver
{
    /** @var array<int, string> */
    private const PRODUCT_URL_KEYS = ['image_url', 'thumb_url', 'product_image_url', 'image', 'thumb'];

    /** @var array<int, string> */
    private const OPTION_URL_KEYS = ['option_image_url', 'image_url', 'option_image', 'image', 'thumb'];

    public function resolveProductImage(array $product, OpenCartImageContext $context): ?string
    {
        foreach (self::PRODUCT_URL_KEYS as $key) {
            $url = $this->normalizeProvidedUrl($product[$key] ?? null);

            if ($url !== null) {
                return $url;
            }
        }

        $path = OpenCartOptionImageResolver::extractProductPath($product);

        return $this->buildFromPath($path, $context);
    }

    public function resolveOptionImage(array $option, OpenCartImageContext $context): ?string
    {
        foreach (self::OPTION_URL_KEYS as $key) {
            $url = $this->normalizeProvidedUrl($option[$key] ?? null);

            if ($url !== null) {
                return $url;
            }
        }

        $path = OpenCartOptionImageResolver::extractFromPayload($option);

        return $this->buildFromPath($path, $context);
    }

    protected function normalizeProvidedUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (str_starts_with($value, '//')) {
            $value = 'https:'.$value;
        }

        if (preg_match('#^https?://#i', $value)) {
            return $this->encodeAbsoluteUrlPath($value);
        }

        return null;
    }

    protected function buildFromPath(?string $path, OpenCartImageContext $context): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (str_starts_with($path, '//')) {
            return $this->encodeAbsoluteUrlPath('https:'.$path);
        }

        if (preg_match('#^https?://#i', $path)) {
            return $this->encodeAbsoluteUrlPath($path);
        }

        $base = $context->effectiveBaseUrl();

        if ($base === '') {
            return $this->encodePathSegments(ltrim($path, '/'));
        }

        return $this->buildStoreImageUrl($base, $path);
    }

    protected function buildStoreImageUrl(string $baseUrl, string $imagePath): string
    {
        $path = ltrim($imagePath, '/');

        if (! str_starts_with(strtolower($path), 'image/')) {
            $path = 'image/'.$path;
        }

        return rtrim($baseUrl, '/').'/'.$this->encodePathSegments($path);
    }

    protected function encodePathSegments(string $path): string
    {
        $segments = array_values(array_filter(explode('/', $path), fn (string $segment) => $segment !== ''));

        return implode('/', array_map(
            fn (string $segment) => rawurlencode(rawurldecode($segment)),
            $segments
        ));
    }

    protected function encodeAbsoluteUrlPath(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $path = ltrim((string) ($parts['path'] ?? ''), '/');
        $encodedPath = $path !== '' ? '/'.$this->encodePathSegments($path) : '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port.$encodedPath.$query.$fragment;
    }
}
