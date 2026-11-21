<?php

namespace App\Services\Cart;

use App\Helper\ApiResponse;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Product;
use App\Services\CheckOut\CheckoutServices;

class CartServices
{
    public function fetchCartItems($user)
    {
        if ($user) {
            $cartItems = Cart::with('product')->withoutGlobalScope('cookie_id')
                ->where('user_id', $user->id)
                ->where('status', 0)->get();

            // A soft-deleted product leaves product null: a clean
            // "product unavailable" error, not a 500 (this also guards
            // CartResource below, which dereferences ->product).
            if ($cartItems->contains(fn ($item) => ! $item->product)) {
                return ApiResponse::sendResponse(422, 'المنتج غير متاح');
            }

            $totalCount = $cartItems->count();

            $totalQuantity = $cartItems->sum('quantity');

            // Same formula as checkout: per-product offers via
            // baseUnitPrice, and the coupon discounted_price only when
            // a discount code is actually active for this user — a
            // stale leftover from an abandoned checkout is ignored.
            $hasActiveDiscount = (new CheckoutServices)->hasActiveCouponDiscount($user);

            $totalBeforeDiscount = $cartItems->sum(function ($item) {
                return $item->quantity * CheckoutServices::baseUnitPrice($item->product);
            });

            $TotalPrice = $cartItems->sum(function ($item) use ($hasActiveDiscount) {
                return $item->quantity * CheckoutServices::chargedUnitPrice($item, $hasActiveDiscount);
            });

            // The actual discount amount on the whole cart — not the
            // first product's discounted_price (the old value).
            $discounted_price = $totalBeforeDiscount - $TotalPrice;
        }

        $data = [
            'total_count' => $totalCount,
            'discounted_price' => $discounted_price,  //  قيمة الخصم علي المنتج الواحد
            'total_price' => $TotalPrice,
            'total_quantity' => $totalQuantity,
            // Same source of truth as the web cart (GET /cart).
            'free_shipping_threshold' => CheckoutServices::freeShippingThreshold(),
            'items' => CartResource::collection($cartItems),
        ];
        return ApiResponse::sendResponse(200, '', $data);
    }

    public function addProductToCart(array $data, $user)
    {
        $product = Product::findOrFail($data['product_id']);
        $quantity = $data['quantity'];

        // Check if requested quantity is available
        if ($quantity > $product->quantity) {
            return ApiResponse::sendResponse(400, __('flash.quantity_not_available'));
        }

        // Check if product is already in the cart
        $cartItem = Cart::withoutGlobalScope('cookie_id')
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('status', 0)
            ->first();

        if ($cartItem) {
            // Ensure adding more quantity does not exceed available stock
            if (($cartItem->quantity + $quantity) > $product->quantity) {
                return ApiResponse::sendResponse(400, __('flash.quantity_exceeds_stock'));
            }

            // Increment the quantity of the existing cart item
            $cartItem->increment('quantity', $quantity);

            return ApiResponse::sendResponse(200, __('flash.cart_quantity_updated'), $cartItem);
        }

        // Create new cart item if not found
        $cart = Cart::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'status' => 0,
            'quantity' => $quantity,
        ]);

        return ApiResponse::sendResponse(201, __('flash.product_added_to_cart'), $cart);
    }

    public function deleteCartItem($cart)
    {
        if (!$cart) {
            return ApiResponse::sendResponse(404, __('flash.product_not_in_cart'), []);
        }
        $cart->update([
            'status' => 1
        ]);

        return ApiResponse::sendResponse(200, __('flash.success'), []);
    }

    public function UpdateCartProductCount($request,$id)
    {
        $request->validate([
            'quantity' => ['required', 'int', 'min:1'],
        ]);
        Cart::where('id', '=', $id)->withoutGlobalScope('cookie_id')
            ->where('user_id', $request->user()->id)
            ->update([
                'quantity' => $request->post('quantity')
            ]);

        return ApiResponse::sendResponse(200, __('flash.cart_updated_success'), []);
    }
}
