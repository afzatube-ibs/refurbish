<?php

namespace App\Services\OpenCart;

class OpenCartImageContext
{
    public function __construct(
        public readonly string $storeUrl = '',
        public readonly ?string $imageBaseUrl = null,
    ) {}

    public static function fromStoreUrl(string $storeUrl): self
    {
        return new self(storeUrl: rtrim(trim($storeUrl), '/'));
    }

    /**
     * @param  array<string, mixed>|null  $connectionTestBody
     */
    public function withConnectionTest(?array $connectionTestBody): self
    {
        $base = self::extractBaseUrl($connectionTestBody ?? []);

        if ($base === null) {
            return $this;
        }

        return new self(
            storeUrl: $this->storeUrl,
            imageBaseUrl: $base,
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function mergeApiResponse(array $response): self
    {
        $base = self::extractBaseUrl($response);

        if ($base === null) {
            return $this;
        }

        return new self(
            storeUrl: $this->storeUrl,
            imageBaseUrl: $base,
        );
    }

    public function effectiveBaseUrl(): string
    {
        if (filled($this->imageBaseUrl)) {
            return rtrim((string) $this->imageBaseUrl, '/');
        }

        return $this->storeUrl;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function extractBaseUrl(array $payload): ?string
    {
        foreach (['image_base_url', 'media_base_url', 'store_image_base_url', 'catalog_base_url'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));

            if ($value !== '') {
                return rtrim($value, '/');
            }
        }

        return null;
    }
}
