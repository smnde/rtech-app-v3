<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\StoreCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Support\StockItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartsController extends Controller
{
    public function show(Request $request, string $type): JsonResponse
    {
        $cart = $this->activeCart($request, $type)->load(['customer:id,name', 'items.product']);

        return response()->json(['cart' => new CartResource($cart)]);
    }

    public function held(Request $request, string $type): JsonResponse
    {
        $this->authorizeType($request, $type);

        $carts = Cart::query()
            ->where('user_id', $request->user()->id)
            ->where('type', $type)
            ->where('status', 'held')
            ->with(['customer:id,name', 'items.product'])
            ->latest()
            ->get();

        return response()->json(['held' => CartResource::collection($carts)]);
    }

    public function updateCustomer(Request $request, string $type): RedirectResponse
    {
        $validated = $request->validate(['customer_id' => ['nullable', 'integer', 'exists:customers,id']]);

        $this->activeCart($request, $type)->update(['customer_id' => $validated['customer_id']]);

        return back();
    }

    public function addItem(StoreCartItemRequest $request, string $type): RedirectResponse
    {
        $data = $request->validated();
        $cart = $this->activeCart($request, $type);
        $stockItem = StockItem::modelFor($data['item_type'])::query()->findOrFail($data['item_id']);

        $item = $cart->items()->firstOrNew([
            'item_type' => $data['item_type'],
            'item_id' => $data['item_id'],
        ]);

        if (! $item->exists) {
            $item->fill([
                'qty' => 0,
                'price' => $type === 'sale' ? $stockItem->sell_price : $stockItem->buy_price,
                'discount' => 0,
                'is_bonus' => false,
            ]);
        }

        $item->qty += $data['qty'] ?? 1;
        $item->price = $data['price'] ?? $item->price;
        $item->save();

        $this->recalculate($cart);

        return back();
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $item): RedirectResponse
    {
        $cart = $this->ownedCart($request, $item->cart);

        $item->update($request->validated());
        $this->recalculate($cart);

        return back();
    }

    public function destroyItem(Request $request, CartItem $item): RedirectResponse
    {
        $cart = $this->ownedCart($request, $item->cart);

        $item->delete();
        $this->recalculate($cart);

        return back();
    }

    public function hold(Request $request, string $type): RedirectResponse
    {
        $validated = $request->validate(['label' => ['nullable', 'string', 'max:100']]);

        $cart = $this->activeCart($request, $type);
        abort_if($cart->items()->doesntExist(), 422, 'Keranjang kosong, tidak bisa di-hold.');

        $cart->update([
            'status' => 'held',
            'label' => $validated['label'] ?? null,
        ]);

        return back()->with('success', 'Transaksi ditahan.');
    }

    public function resume(Request $request, Cart $cart): RedirectResponse
    {
        $this->ownedCart($request, $cart);
        abort_unless($cart->status === 'held', 422, 'Transaksi ini tidak sedang di-hold.');

        $active = $this->activeCart($request, $cart->type);
        abort_if($active->items()->exists(), 422, 'Kosongkan keranjang aktif dulu sebelum melanjutkan transaksi yang di-hold.');

        $active->delete();
        $cart->update(['status' => 'active', 'label' => null]);

        return back();
    }

    public function destroy(Request $request, Cart $cart): RedirectResponse
    {
        $this->ownedCart($request, $cart)->delete();

        return back()->with('success', 'Transaksi di-hold dihapus.');
    }

    private function authorizeType(Request $request, string $type): void
    {
        abort_unless(in_array($type, ['sale', 'purchase'], true), 404);
        abort_unless($request->user()->can("add {$type}"), 403);
    }

    private function activeCart(Request $request, string $type): Cart
    {
        $this->authorizeType($request, $type);

        return Cart::activeFor($request->user(), $type);
    }

    private function ownedCart(Request $request, Cart $cart): Cart
    {
        abort_unless($cart->user_id === $request->user()->id, 403);
        $this->authorizeType($request, $cart->type);

        return $cart;
    }

    private function recalculate(Cart $cart): void
    {
        $items = $cart->items()->get();

        $cart->update([
            'total' => $items->sum(fn (CartItem $item) => max(0, ($item->qty * $item->price) - $item->discount)),
            'item_count' => $items->sum('qty'),
        ]);
    }
}
