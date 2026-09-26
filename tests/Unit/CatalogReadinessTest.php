<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\CatalogReadiness;
use Tests\TestCase;

class CatalogReadinessTest extends TestCase
{
    public function test_it_identifies_placeholder_catalog_records(): void
    {
        $readiness = app(CatalogReadiness::class);

        $this->assertTrue($readiness->looksLikePlaceholder(new Product(['name' => 'Test', 'slug' => 'test'])));
        $this->assertTrue($readiness->looksLikePlaceholder(new Product(['name' => 'Sản phẩm demo', 'slug' => 'demo'])));
        $this->assertFalse($readiness->looksLikePlaceholder(new Product(['name' => 'Bikini thể thao', 'slug' => 'bikini-the-thao'])));
    }

    public function test_it_only_accepts_existing_local_product_images(): void
    {
        $readiness = app(CatalogReadiness::class);

        $this->assertTrue($readiness->hasReadableLocalImage(new Product(['image_url' => '/images/vua-beach-hero-v2.avif'])));
        $this->assertFalse($readiness->hasReadableLocalImage(new Product(['image_url' => '/images/product-placeholder.svg'])));
        $this->assertFalse($readiness->hasReadableLocalImage(new Product(['image_url' => '/images/does-not-exist.webp'])));
        $this->assertFalse($readiness->hasReadableLocalImage(new Product(['image_url' => 'https://images.example.com/product.webp'])));
    }
}
