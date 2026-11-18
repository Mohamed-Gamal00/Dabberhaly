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

        foreach ($cartItems as $item) {
            // موجودة في السلة $discountCode->products لو المنتجات اللي عليها خصم
            if ($isGlobalDiscount || $discountCode->products->contains($item->product_id)) {
                // Apply discount based on discount type نوع الخصم اذا كان نسبة او ثابت
                if ($discountCode->discount_type == 'percentage') {
                    $discountAmount = (($item->product->discount_price ?? $item->product->price) * $discountCode->price) / 100;
                } else {
                    $discountAmount = $discountCode->price;
                }
                // نسبة الخصم علي المنتج الواحد
                $originalPrice = $item->product->discount_price ?? $item->product->price;
                $item->discounted_price = max(0, $originalPrice - $discountAmount);
                $item->save();
            }
        }

        return [
            'discount_code' => $discountCode->code,
            'discount_price' => $discountCode->price,
        ];
    }
}
