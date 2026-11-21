<?php

namespace App\Repositories\Cart;

use App\Models\AppliedDiscountCode;
use App\Models\Cart;
use App\Models\Product;
use App\Services\CheckOut\CheckoutServices;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CartModelRepository implements CartRepository
{
    /**
     * Add a product to the cart (or increment quantity if it already exists).
     *
     * The global scope on Cart ensures we only look at items belonging to the
     * current authenticated user or guest cookie — ownership is verified implicitly.
     *
     * @throws \InvalidArgumentException When the resulting quantity would exceed stock.
     */
    public function add(Product $product, $quantity = 1)
    {
        $quantity = max(1, (int) $quantity);

        // The global scope restricts this to the current owner's items.
        $item = Cart::where('product_id', '=', $product->id)
            ->where('status', 0)
            ->first();

        if (!$item) {
            // Creating a new line — cap at available stock.
            if ($quantity > $product->quantity) {
                $quantity = $product->quantity;
            }
            if ($quantity < 1) {
                throw new \InvalidArgumentException(__('flash.quantity_not_available'));
            }

            Cart::create([
                'product_id' => $product->id,
                'status'     => 0,
                'quantity'   => $quantity,
            ]);
        } else {
            // Incrementing an existing line — respect stock.
            $newQty = $item->quantity + $quantity;
            if ($newQty > $product->quantity) {
                throw new \InvalidArgumentException(__('flash.quantity_not_available'));
            }
            $item->increment('quantity', $quantity);
        }
    }

    /**
     * Get all active cart items for the current owner.
     * The global scope restricts results to the current user or guest.
     *
     * We intentionally do NOT cache results — the repository instance can be
     * reused across requests (Laravel's router caches controller instances on
     * the Route object), so a cache would leak items between different users.
     */
    public function get(): Collection
    {
        return Cart::with('product')->where('status', 0)->get();
    }

    /**
     * Delete a cart item (soft delete via status = 1).
     *
     * The global scope ensures only items belonging to the current owner
     * are affected — if the id doesn't belong to this cart, nothing happens.
     */
    public function delete($id)
    {
        Cart::where('id', '=', $id)->update(['status' => 1]);
    }

    /**
     * Update the quantity of a cart item.
     *
     * The global scope ensures only the current owner's items are affected.
     *
     * @throws \InvalidArgumentException When the quantity exceeds stock.
     */
    public function update($id, $quantity)
    {
        $item = Cart::where('id', '=', $id)->where('status', 0)->first();

        if (!$item) {
            return; // Item doesn't belong to this cart — silently ignore.
        }

        $quantity = max(1, (int) $quantity);

        $product = $item->product;
        if ($product && $quantity > $product->quantity) {
            throw new \InvalidArgumentException(__('flash.quantity_not_available'));
        }

        $item->update(['quantity' => $quantity]);
    }

    public function empty()
    {
        Cart::query()->update(['status' => 1]);
    }

    /**
     * Total before any per-row discount snapshot is applied.
     * Uses the product's live price / discount_price.
     */
    public function totalBeforeDiscount(): float
    {
        return $this->get()->sum(function ($item) {
            // A soft-deleted product leaves product null — it contributes 0,
            // it must not break the total (mapItems() already uses ?->).
            $price = $item->product?->discount_price ?? $item->product?->price ?? 0;
            return $item->quantity * $price;
        });
    }

    /**
     * Final total — the same formula as checkout
     * (CheckoutServices::chargedUnitPrice): per-product offers via
     * baseUnitPrice, and the per-row discounted_price snapshot only
     * when a discount code is actually active — a stale leftover
     * from an abandoned checkout must be ignored.
     */
    public function total(): float
    {
        $items = $this->get();

        $hasActiveDiscount = (bool) session('applied_discount_code_id')
            || AppliedDiscountCode::whereIn(
                'cookie_id',
                $items->pluck('cookie_id')->filter()->unique()
            )->exists();

        return $items->sum(function ($item) use ($hasActiveDiscount) {
            // Soft-deleted product: product is null — the line
            // contributes only its stored discounted_price snapshot
            // when a discount is active, else 0 (as before).
            if (! $item->product) {
                return $item->quantity * ($hasActiveDiscount ? (float) ($item->discounted_price ?? 0) : 0);
            }

            return $item->quantity * CheckoutServices::chargedUnitPrice($item, $hasActiveDiscount);
        });
    }
}
