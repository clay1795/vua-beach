<?php

namespace App\Http\Controllers\Admin;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseReceiptController extends AdminController
{
    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $receipts = PurchaseReceipt::with('supplier', 'creator')
            ->when($request->filled('q'), fn ($query) => $query->where('receipt_code', 'like', '%'.$request->string('q').'%'))
            ->latest('received_at')->paginate(15)->withQueryString();

        return view('admin.purchase-receipts.index', compact('receipts'));
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.purchase-receipts.form', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'variants' => ProductVariant::active()->with('product')->orderBy('sku')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'received_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0', 'max:100000000'],
        ]);
        abort_unless(Supplier::whereKey($data['supplier_id'])->where('is_active', true)->exists(), 422, 'Nhà cung cấp không còn hoạt động.');

        $receipt = DB::transaction(function () use ($data, $request) {
            $receipt = PurchaseReceipt::create([
                'receipt_code' => $this->receiptCode(),
                'supplier_id' => $data['supplier_id'],
                'created_by_user_id' => $request->user()->id,
                'received_at' => $data['received_at'],
                'note' => $data['note'] ?? null,
            ]);
            $total = 0;
            foreach ($data['items'] as $item) {
                $variant = ProductVariant::lockForUpdate()->findOrFail($item['variant_id']);
                abort_unless($variant->is_active, 422, 'Không thể nhập kho cho biến thể đã ngừng bán.');
                $lineTotal = $item['quantity'] * $item['unit_cost'];
                $receipt->items()->create([
                    'product_variant_id' => $variant->id,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'line_total' => $lineTotal,
                ]);
                $variant->increment('stock', $item['quantity']);
                InventoryMovement::create([
                    'product_variant_id' => $variant->id,
                    'purchase_receipt_id' => $receipt->id,
                    'type' => 'in',
                    'quantity' => $item['quantity'],
                    'balance_after' => $variant->fresh()->stock,
                    'reason' => 'purchase_receipt',
                    'note' => 'Nhập kho '.$receipt->receipt_code,
                ]);
                $total += $lineTotal;
            }
            $receipt->update(['total_cost' => $total]);

            return $receipt;
        });

        $this->audit('purchase_receipt.created', $receipt, 'Lập phiếu nhập '.$receipt->receipt_code.'.', ['total_cost' => $receipt->total_cost, 'supplier_id' => $receipt->supplier_id]);

        return redirect()->route('admin.purchase-receipts.show', $receipt)->with('success', 'Đã lập phiếu nhập và cập nhật tồn kho.');
    }

    public function show(PurchaseReceipt $purchaseReceipt)
    {
        $this->authorizeAdmin();
        $purchaseReceipt->load('supplier', 'creator', 'items.variant.product');

        return view('admin.purchase-receipts.show', compact('purchaseReceipt'));
    }

    private function receiptCode(): string
    {
        do {
            $code = 'PN-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (PurchaseReceipt::where('receipt_code', $code)->exists());

        return $code;
    }
}
