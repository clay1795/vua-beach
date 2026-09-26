<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\ProductImageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductController extends AdminController
{
    /** @var array<int, string> */
    private array $storedAssets = [];

    public function index()
    {
        $this->authorizeAdmin();
        $products = Product::with('category')->latest()->paginate(12);

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.products.form', ['product' => new Product, 'categories' => Category::all()]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $this->storedAssets = [];
        try {
            $product = DB::transaction(function () use ($request) {
                $product = Product::create($this->data($request));
                $this->saveVariants($request, $product);
                $this->saveImages($request, $product);

                return $product;
            });
        } catch (Throwable $exception) {
            $this->cleanupStoredAssets();

            throw $exception;
        }
        $this->storedAssets = [];
        $this->audit('product.created', $product, 'Tạo sản phẩm: '.$product->name);

        return redirect()->route('admin.products.index')->with('success', 'Đã thêm sản phẩm.');
    }

    public function edit(Product $product)
    {
        $this->authorizeAdmin();

        return view('admin.products.form', ['product' => $product->load('variants', 'images'), 'categories' => Category::all()]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeAdmin();
        $oldCover = $product->image_url;
        $this->storedAssets = [];
        try {
            DB::transaction(function () use ($request, $product) {
                $product->update($this->data($request, $product));
                $this->saveVariants($request, $product);
                $this->saveImages($request, $product);
            });
        } catch (Throwable $exception) {
            $this->cleanupStoredAssets();

            throw $exception;
        }
        $this->storedAssets = [];
        $newCover = $product->fresh()->image_url;
        if ($oldCover !== $newCover) {
            $this->deleteLocalAsset($oldCover);
        }
        $this->audit('product.updated', $product, 'Cập nhật sản phẩm: '.$product->fresh()->name);

        return redirect()->route('admin.products.index')->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Product $product)
    {
        $this->authorizeAdmin();

        if ($product->orderItems()->exists() || $product->variants()->whereHas('inventoryMovements')->exists()) {
            $product->update(['status' => 'inactive']);
            $this->audit('product.deactivated', $product, 'Ngừng bán sản phẩm có lịch sử: '.$product->name);

            return back()->with('success', 'Sản phẩm đã có lịch sử bán hàng nên được ngừng bán để bảo toàn dữ liệu.');
        }
        $product->load('images');
        $productName = $product->name;
        $assets = collect([$product->image_url])->merge($product->images->pluck('path'));
        $product->delete();
        $assets->each(fn (?string $asset) => $this->deleteLocalAsset($asset));
        $this->audit('product.deleted', null, 'Xóa sản phẩm: '.$productName);

        return back()->with('success', 'Đã xóa sản phẩm.');
    }

    public function destroyImage(Product $product, ProductImage $image)
    {
        $this->authorizeAdmin();
        abort_unless($image->product_id === $product->id, 404);

        $this->deleteLocalAsset($image->path);
        $image->delete();
        $this->audit('product.image_deleted', $product, 'Xóa ảnh khỏi sản phẩm: '.$product->name, ['image_id' => $image->id]);

        return back()->with('success', 'Đã xóa ảnh khỏi thư viện sản phẩm.');
    }

    private function data(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['nullable', 'integer', 'min:0', 'lt:price'],
            'description' => ['required', 'string', 'max:4000'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=300,min_height=300,max_width=6000,max_height=6000', 'max:5120'],
            'gallery_images' => ['nullable', 'array', 'max:8'],
            'gallery_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=300,min_height=300,max_width=6000,max_height=6000', 'max:5120'],
            'status' => ['required', 'in:active,inactive'],
            'variants' => ['nullable', 'array', 'max:30'],
            'variants.*.color' => ['nullable', 'string', 'max:50'],
            'variants.*.size' => ['nullable', 'string', 'max:10'],
            'variants.*.id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variants.*.sku' => ['nullable', 'string', 'max:80', 'distinct'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['slug'] = Str::slug($data['name']);
        if (Product::where('slug', $data['slug'])->whereKeyNot($product?->id)->exists()) {
            $data['slug'] .= '-'.time();
        }

        unset($data['cover_image'], $data['gallery_images']);
        if ($request->hasFile('cover_image')) {
            $data['image_url'] = $this->storeImage($request->file('cover_image'), 'products', 'cover_image');
        }

        return $data;
    }

    private function saveVariants(Request $request, Product $product): void
    {
        $variants = collect($request->input('variants', []))
            ->map(fn (array $variant) => [
                'id' => $variant['id'] ?? null,
                'color' => trim((string) ($variant['color'] ?? '')),
                'size' => trim((string) ($variant['size'] ?? '')),
                'sku' => trim((string) ($variant['sku'] ?? '')),
                'stock' => max(0, (int) ($variant['stock'] ?? 0)),
                'low_stock_threshold' => max(0, (int) ($variant['low_stock_threshold'] ?? 5)),
            ])
            ->filter(fn (array $variant) => filled($variant['color']) || filled($variant['size']) || filled($variant['sku']))
            ->values();

        $incomplete = $variants->first(fn (array $variant) => blank($variant['color']) || blank($variant['size']));
        if ($incomplete) {
            throw ValidationException::withMessages([
                'variants' => 'Mỗi biến thể phải có đủ màu sắc và kích cỡ.',
            ]);
        }

        $duplicate = $variants
            ->groupBy(fn (array $variant) => Str::lower($variant['color']).'|'.Str::lower($variant['size']))
            ->first(fn ($group) => $group->count() > 1);
        if ($duplicate) {
            throw ValidationException::withMessages([
                'variants' => 'Không được nhập trùng cùng một tổ hợp màu sắc và kích cỡ.',
            ]);
        }

        $submittedVariantIds = $variants->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $product->variants()->whereNotIn('id', $submittedVariantIds)->get()->each(function (ProductVariant $variant) {
            if ($variant->orderItems()->exists() || $variant->inventoryMovements()->exists()) {
                $variant->update(['is_active' => false]);

                return;
            }

            $variant->delete();
        });

        foreach ($variants as $variant) {
            $existing = filled($variant['id'])
                ? $product->variants()->find($variant['id'])
                : null;

            if (filled($variant['id']) && ! $existing) {
                throw ValidationException::withMessages([
                    'variants' => 'Biến thể không thuộc về sản phẩm này.',
                ]);
            }

            $sku = blank($variant['sku'])
                ? $this->generateSku($product, $variant['color'], $variant['size'], $existing?->id)
                : Str::upper($variant['sku']);

            if (ProductVariant::where('sku', $sku)->whereKeyNot($existing?->id)->exists()) {
                throw ValidationException::withMessages([
                    'variants' => "SKU {$sku} đã được dùng cho một biến thể khác.",
                ]);
            }

            $attributes = [
                'sku' => $sku,
                'color' => $variant['color'],
                'size' => $variant['size'],
                'stock' => $variant['stock'],
                'low_stock_threshold' => $variant['low_stock_threshold'],
                'is_active' => true,
            ];

            if (! $existing) {
                $created = $product->variants()->create($attributes);
                if ($created->stock > 0) {
                    InventoryMovement::create(['product_variant_id' => $created->id, 'type' => 'in', 'quantity' => $created->stock, 'balance_after' => $created->stock, 'reason' => 'initial_stock', 'note' => 'Tạo biến thể từ quản trị']);
                }

                continue;
            }

            $before = $existing->stock;
            $existing->update($attributes);
            $difference = $existing->stock - $before;
            if ($difference !== 0) {
                InventoryMovement::create(['product_variant_id' => $existing->id, 'type' => $difference > 0 ? 'in' : 'out', 'quantity' => abs($difference), 'balance_after' => $existing->stock, 'reason' => 'manual_adjustment', 'note' => 'Điều chỉnh tồn kho từ quản trị']);
            }
        }
    }

    private function generateSku(Product $product, string $color, string $size, ?int $exceptId = null): string
    {
        $colorCode = Str::upper(Str::substr(Str::slug($color), 0, 18));
        $sizeCode = Str::upper(Str::substr(Str::slug($size), 0, 10));
        $base = "VB-{$product->id}-{$colorCode}-{$sizeCode}";
        $sku = $base;
        $suffix = 2;

        while (ProductVariant::where('sku', $sku)->whereKeyNot($exceptId)->exists()) {
            $sku = "{$base}-{$suffix}";
            $suffix++;
        }

        return $sku;
    }

    private function saveImages(Request $request, Product $product): void
    {
        $nextSortOrder = $product->images()->count();
        foreach ($request->file('gallery_images', []) as $index => $image) {
            $path = $this->storeImage($image, 'products', "gallery_images.{$index}");
            $product->images()->create(['path' => $path, 'sort_order' => $nextSortOrder + $index + 1]);
        }
    }

    private function storeImage(UploadedFile $image, string $directory, string $field): string
    {
        $url = app(ProductImageService::class)->store($image, $directory, $field);
        $this->storedAssets[] = $url;

        return $url;
    }

    private function cleanupStoredAssets(): void
    {
        foreach ($this->storedAssets as $asset) {
            $this->deleteLocalAsset($asset);
        }
        $this->storedAssets = [];
    }

    private function deleteLocalAsset(?string $url): void
    {
        app(ProductImageService::class)->deleteLocal($url);
    }
}
