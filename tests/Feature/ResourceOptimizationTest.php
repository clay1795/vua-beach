<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ResourceOptimizationTest extends TestCase
{
    public function test_responsive_image_prefers_local_avif_then_webp(): void
    {
        $html = Blade::render(
            '<x-responsive-image :src="asset(\'images/catalog/products/bo-boi-xanh-phoi-trang.jpg\')" alt="Sản phẩm" width="600" height="900" loading="lazy" />',
        );

        $this->assertStringContainsString('type="image/avif"', $html);
        $this->assertStringContainsString('/images/catalog/products/bo-boi-xanh-phoi-trang.avif', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('/images/catalog/products/bo-boi-xanh-phoi-trang.webp', $html);
        $this->assertStringContainsString('src="http://localhost/images/catalog/products/bo-boi-xanh-phoi-trang.jpg"', $html);
    }

    public function test_user_facing_views_do_not_embed_remote_assets(): void
    {
        foreach ($this->viewFiles() as $path => $contents) {
            preg_match_all('/\b(?:src|href)\s*=\s*["\']https?:\/\//i', $contents, $matches);
            $this->assertSame([], $matches[0], "View {$path} đang phụ thuộc tài nguyên từ máy chủ ngoài.");
        }
    }

    public function test_responsive_images_reserve_space_and_defer_non_critical_loading(): void
    {
        foreach ($this->viewFiles() as $path => $contents) {
            preg_match_all('/<x-responsive-image\b.*?\/>/is', $contents, $matches);
            foreach ($matches[0] as $tag) {
                $this->assertMatchesRegularExpression('/\bwidth="\d+"/', $tag, "Ảnh trong {$path} thiếu width.");
                $this->assertMatchesRegularExpression('/\bheight="\d+"/', $tag, "Ảnh trong {$path} thiếu height.");
                $this->assertMatchesRegularExpression('/\b(?:loading="lazy"|fetchpriority="high")/', $tag, "Ảnh trong {$path} chưa khai báo chiến lược tải.");
            }
        }
    }

    /** @return array<string, string> */
    private function viewFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $path = $file->getPathname();
            if (str_contains($path, DIRECTORY_SEPARATOR.'emails'.DIRECTORY_SEPARATOR)
                || str_contains($path, DIRECTORY_SEPARATOR.'seo'.DIRECTORY_SEPARATOR)
                || $file->getFilename() === 'welcome.blade.php') {
                continue;
            }

            $files[$path] = file_get_contents($path);
        }

        return $files;
    }
}
