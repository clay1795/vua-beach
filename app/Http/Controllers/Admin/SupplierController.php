<?php

namespace App\Http\Controllers\Admin;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends AdminController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $suppliers = Supplier::query()
            ->withCount('purchaseReceipts')
            ->when($request->filled('q'), fn ($query) => $query->where(function ($items) use ($request) {
                $term = '%'.$request->string('q').'%';
                $items->where('name', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('email', 'like', $term);
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.suppliers.form', ['supplier' => new Supplier]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $supplier = Supplier::create($this->data($request));
        $this->audit('supplier.created', $supplier, 'Thêm nhà cung cấp '.$supplier->name.'.');

        return redirect()->route('admin.suppliers.index')->with('success', 'Đã thêm nhà cung cấp.');
    }

    public function edit(Supplier $supplier)
    {
        $this->authorizeAdmin();

        return view('admin.suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->authorizeAdmin();
        $supplier->update($this->data($request));
        $this->audit('supplier.updated', $supplier, 'Cập nhật nhà cung cấp '.$supplier->name.'.');

        return redirect()->route('admin.suppliers.index')->with('success', 'Đã cập nhật nhà cung cấp.');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorizeAdmin();
        $supplier->update(['is_active' => false]);
        $this->audit('supplier.disabled', $supplier, 'Ngừng sử dụng nhà cung cấp '.$supplier->name.'.');

        return back()->with('success', 'Đã ngừng sử dụng nhà cung cấp.');
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
