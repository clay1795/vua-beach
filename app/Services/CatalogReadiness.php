<?php

namespace App\Services;

use App\Models\Product;

class CatalogReadiness
{
    private const PLACEHOLDER_SLUGS = ['test', 'demo', 'sample', 'san-pham-test'];

    public function looksLikePlaceholder(Product $product): bool
    {
        return in_array(mb_strtolower(trim((string) $product->slug)), self::PLACEHOLDER_SLUGS, true);
    }

    public function hasReadableLocalImage(Product $product): bool
    {
        $url = trim((string) $product->image_url);
        if ($url === '' || str_contains($url, 'product-placeholder.svg')) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '' || ! str_starts_with($path, '/')) {
            return false;
        }

        $resolved = realpath(public_path(ltrim($path, '/')));
        if ($resolved === false || ! is_file($resolved)) {
            return false;
        }

        $allowedRoots = array_filter([
            realpath(public_path()),
            realpath(storage_path('app/public')),
        ]);

        return collect($allowedRoots)->contains(
            fn (string $root) => $resolved === $root || str_starts_with($resolved, $root.DIRECTORY_SEPARATOR),
        );
    }
}
