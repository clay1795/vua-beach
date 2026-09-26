<?php

namespace App\Http\Controllers\Admin;

use App\Models\InventoryMovement;
use Illuminate\Http\Request;

class InventoryController extends AdminController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $movements = InventoryMovement::with('variant.product')
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->whereHas('variant', fn ($variants) => $variants
                    ->where('sku', 'like', $term)
                    ->orWhereHas('product', fn ($products) => $products->where('name', 'like', $term)));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.inventory.index', compact('movements'));
    }
}
