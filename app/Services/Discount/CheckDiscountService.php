<?php

namespace App\Services\Discount;

use App\Helper\ApiResponse;
use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\UserDiscountCode;

class CheckDiscountService
{
    public function fetchDiscountCode($request)
    {
        return DiscountCode::where('code', $request->discount_code)
            ->where('status', 'active')
            ->where('number_of_used', '>', 0)
            ->first();
    }

    /**
     * Has this guest/user already used the code? Read-only check — the
     * usage row is created only when the discount actually applies to
     * a non-empty cart (see CheckDiscountController).
     */
    public function checkIfUserUseDiscountCode($discountCode)
    {
        $userDiscountCodeExists = UserDiscountCode::where([
            'cookie_id' => Cart::getCookieIdApi(),
            'discount_id' => $discountCode->id
        ])->exists();

        if ($userDiscountCodeExists) {
            return ApiResponse::sendResponse(400, __('flash.discount_already_used'));
        }

        return null;
    }

    /**
     * Apply a discount code to cart items (shared by the web, API-user
     * and API-guest apply flows so all three behave the same).
     *
     * Percentage: per unit, off the offer price when set.
     * Fixed amount: ONCE per order — the amount is spread across the
     * eligible lines (each line capped at its own total), never the
     * full amount off every matching product.
     */
    public static function applyDiscountToItems($cartItems, DiscountCode $discountCode, bool $isGlobalDiscount): void
    {
        $eligible = $cartItems->filter(
            fn ($item) => $isGlobalDiscount || $discountCode->products->contains($item->product_id)
        );

        if ($eligible->isEmpty()) {
            return;
        }

        if ($discountCode->discount_type === 'percentage') {
            foreach ($eligible as $item) {
                $base = $item->product->discount_price ?? $item->product->price;
                $item->discounted_price = max(0, $base - ($base * $discountCode->price) / 100);
                $item->save();
            }

            return;
        }

        $remaining = (float) $discountCode->price;

        foreach ($eligible as $item) {
            if ($remaining <= 0) {
                break;
            }

            $base = $item->product->discount_price ?? $item->product->price;
            // This line absorbs at most its own total; the per-unit
            // discount is the absorbed amount spread over its quantity
            // so the line total drops by exactly that amount.
            $absorbed = min($remaining, $base * $item->quantity);
            $item->discounted_price = max(0, round($base - $absorbed / $item->quantity, 2));
            $item->save();
            $remaining -= $absorbed;
        }
    }

    /**
     * Apply the discount to the cart items. Returns the response data
     * on success, or null when the cart is empty (so the caller can
     * skip consuming the code).
     */
    public function applyDiscountToCartItems($discountCode, $isGlobalDiscount, $user)
    {
        $cartItems = Cart::with('product')->withoutGlobalScope('cookie_id')
            ->where('user_id', $user->id)
            ->where('status', 0)->get();

        if ($cartItems->isEmpty()) {
            return null;
        }

        self::applyDiscountToItems($cartItems, $discountCode, $isGlobalDiscount);

        return [
            'discount_code' => $discountCode->code,
            'discount_price' => $discountCode->price,
        ];
    }
}
