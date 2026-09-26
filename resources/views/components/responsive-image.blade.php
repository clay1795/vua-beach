@props(['src', 'alt', 'fallback' => null])
@php
    $urlPath = parse_url($src, PHP_URL_PATH);
    $extension = is_string($urlPath) ? strtolower(pathinfo($urlPath, PATHINFO_EXTENSION)) : '';
    $basePath = $extension !== '' ? substr($urlPath, 0, -strlen($extension) - 1) : null;
    $alternative = function (string $format) use ($basePath, $src): ?string {
        if (! $basePath || ! is_file(public_path(ltrim(rawurldecode($basePath.'.'.$format), '/')))) {
            return null;
        }

        return preg_replace('/\.[^.?\/]+(?=\?|$)/', '.'.$format, $src);
    };
    $avifSrc = $alternative('avif');
    $webpSrc = $extension === 'webp' ? null : $alternative('webp');
    $fallbackScript = $fallback
        ? "this.onerror=null;this.src='".e($fallback)."'"
        : null;
@endphp
<picture>
    @if($avifSrc)<source srcset="{{ $avifSrc }}" type="image/avif">@endif
    @if($webpSrc)<source srcset="{{ $webpSrc }}" type="image/webp">@endif
    <img src="{{ $src }}" alt="{{ $alt }}" @if($fallbackScript) onerror="{{ $fallbackScript }}" @endif {{ $attributes }}>
</picture>
