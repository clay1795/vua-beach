<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Services\ProductImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class CategoryController extends AdminController
{
    public function index()
    {
        $this->authorizeAdmin();

        return view('admin.categories.index', ['categories' => Category::withCount('products')->latest()->get()]);
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.categories.form', ['category' => new Category]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $data = $this->data($request);
        try {
            $category = Category::create($data);
        } catch (Throwable $exception) {
            app(ProductImageService::class)->deleteLocal($data['image_url'] ?? null);

            throw $exception;
        }
        $this->audit('category.created', $category, 'Tạo danh mục: '.$category->name);

        return redirect()->route('admin.categories.index')->with('success', 'Đã thêm danh mục.');
    }

    public function edit(Category $category)
    {
        $this->authorizeAdmin();

        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeAdmin();
        $oldImage = $category->image_url;
        $data = $this->data($request, $category);
        try {
            $category->update($data);
        } catch (Throwable $exception) {
            if (($data['image_url'] ?? null) !== $oldImage) {
                app(ProductImageService::class)->deleteLocal($data['image_url'] ?? null);
            }

            throw $exception;
        }
        if ($oldImage !== $category->fresh()->image_url) {
            app(ProductImageService::class)->deleteLocal($oldImage);
        }
        $this->audit('category.updated', $category, 'Cập nhật danh mục: '.$category->name);

        return redirect()->route('admin.categories.index')->with('success', 'Đã cập nhật danh mục.');
    }

    public function destroy(Category $category)
    {
        $this->authorizeAdmin();
        if ($category->products()->exists()) {
            return back()->withErrors(['category' => 'Không thể xóa danh mục đang có sản phẩm.']);
        }
        $categoryName = $category->name;
        $image = $category->image_url;
        $category->delete();
        app(ProductImageService::class)->deleteLocal($image);
        $this->audit('category.deleted', null, 'Xóa danh mục: '.$categoryName);

        return back()->with('success', 'Đã xóa danh mục.');
    }

    private function data(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=300,min_height=300,max_width=6000,max_height=6000', 'max:5120'],
        ]);
        $data['slug'] = Str::slug($data['name']);
        if (Category::where('slug', $data['slug'])->whereKeyNot($category?->id)->exists()) {
            $data['slug'] .= '-'.time();
        }
        unset($data['category_image']);
        if ($request->hasFile('category_image')) {
            $data['image_url'] = app(ProductImageService::class)->store($request->file('category_image'), 'categories', 'category_image');
        }

        return $data;
    }
}
