<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductImageService
{
    public function store(UploadedFile $file, string $directory = 'products', string $validationField = 'image', int $maxSide = 1800): string
    {
        $directory = trim($directory, '/');
        if ($directory === '' || str_contains($directory, '..') || preg_match('/^[a-z0-9_\/-]+$/i', $directory) !== 1) {
            throw new \InvalidArgumentException('Invalid image storage directory.');
        }
        if ($maxSide < 160 || $maxSide > 3000) {
            throw new \InvalidArgumentException('Invalid image size limit.');
        }

        $realPath = $file->getRealPath();
        $metadata = is_string($realPath) ? @getimagesize($realPath) : false;
        $mime = is_array($metadata) ? ($metadata['mime'] ?? null) : null;
        $width = is_array($metadata) ? (int) ($metadata[0] ?? 0) : 0;
        $height = is_array($metadata) ? (int) ($metadata[1] ?? 0) : 0;
        if (! $file->isValid()
            || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
            || $width < 1 || $height < 1
            || max($width, $height) > 6000
            || $width * $height > 12_000_000
            || ! is_int($file->getSize())
            || $file->getSize() > 5 * 1024 * 1024) {
            $this->reject($validationField);
        }

        $source = @file_get_contents($file->getRealPath());
        $image = $source === false ? false : @imagecreatefromstring($source);

        if ($image === false || ! function_exists('imagewebp')) {
            $this->reject($validationField);
        }

        $image = $this->orient($image, $file);
        $image = $this->resize($image, $maxSide);

        ob_start();
        $encoded = imagewebp($image, null, 82);
        $webp = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || ! is_string($webp) || $webp === '') {
            $this->reject($validationField);
        }

        $path = $directory.'/'.Str::uuid().'.webp';
        if (! Storage::disk('public')->put($path, $webp)) {
            throw new \RuntimeException('Không thể lưu ảnh đã xử lý.');
        }

        return Storage::disk('public')->url($path);
    }

    public function deleteLocal(?string $url): void
    {
        if (blank($url)) {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        if (! str_starts_with($path, '/storage/')) {
            return;
        }

        $relativePath = ltrim(substr($path, strlen('/storage/')), '/');
        // Never let a corrupted/legacy database value turn asset cleanup into
        // an arbitrary delete on the public disk. This service owns only these
        // namespaces and only raster image extensions.
        if (preg_match('#^(?:products|categories|site)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9._-]+\.(?:jpe?g|png|webp)$#i', $relativePath) !== 1) {
            return;
        }

        Storage::disk('public')->delete($relativePath);
    }

    private function orient(\GdImage $image, UploadedFile $file): \GdImage
    {
        if ($file->getMimeType() !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) ((@exif_read_data($file->getRealPath()) ?: [])['Orientation'] ?? 1);
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    private function resize(\GdImage $image, int $maxSide): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxSide / max($width, $height));
        if ($scale === 1) {
            return $image;
        }

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    private function reject(string $field): never
    {
        throw ValidationException::withMessages([
            $field => 'Ảnh không hợp lệ, quá lớn hoặc máy chủ không thể xử lý an toàn. Hãy dùng JPG, PNG hoặc WebP.',
        ]);
    }
}
