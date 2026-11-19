<?php

namespace App\Http\Controllers\Api\guest;

use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\UserDiscountCode;
use App\Repositories\Cart\CartRepository;
use App\Services\Discount\CheckDiscountService;
use Illuminate\Http\Request;

class GuestCheckDiscountController extends Controller
{
    public function __invoke(Request $request, CartRepository $cart)
    {

        // Validate the request
        $request->validate([
            'discount_code' => 'required|exists:discount_codes,code'
        ]);

        // Fetch the discount code and check its validity
        $discountCode = DiscountCode::where('code', $request->discount_code)
            ->where('status', 'active')
            ->where('number_of_used', '>', 0)
            ->first();
//        return $discountCode;
        if (!$discountCode) {
            return response()->json([
                'message' => 'Sorry, this discount code has expired or is no longer valid.'
            ], 400);
        }

        // Check if the discount code has already been used by this user
        // find it in  cookie_discount_ids table
        $userDiscountCode = UserDiscountCode::where([
            'cookie_id' => Cart::getCookieIdApi(),
            'discount_id' => $discountCode->id
        ])->first();

//        return $userDiscountCode;

        if ($userDiscountCode) {
            return response()->json([
                'message' => 'You have already used this discount code.'
            ], 400);
        }

        // بشوف لو كود الخصم مش متحدد لمنتجات معينة هيبقي خصم علي كل المنتجات
        $isGlobalDiscount = $discountCode->products()->count() === 0;

        // Apply discount to eligible cart items
        $guestId = $request->header('guest-id');
//        return $guestId;
        $cartItems = Cart::with('product')->withoutGlobalScope('cookie_id')
            ->where('cookie_id', $guestId)
            ->where('status', 0)->get();

        // Empty cart: consume nothing.
        if ($cartItems->isEmpty()) {
            return ApiResponse::sendResponse(200, 'لا يمكن استخدام كود الخصم والسلة فارغة');
        }

        // Create a record for the user using the discount code
        UserDiscountCode::create([
            'cookie_id' => Cart::getCookieIdApi(), // منتجات السلة دي
            'discount_id' => $discountCode->id // هيطبق عليها الخصم ده
        ]);

        // Decrement the number of uses for this discount code
        $discountCode->decrement('number_of_used');

        // Shared with the web and API-user flows: percentage is per
        // unit, a fixed amount applies ONCE per order (spread across
        // the eligible lines, each capped at its own total) — never
        // the full amount off every matching product, and never the
        // discount amount itself as the new price.
        CheckDiscountService::applyDiscountToItems($cartItems, $discountCode, $isGlobalDiscount);

        return response()->json([
            'message' => 'Discount code applied successfully.',
            'discount_code' => $discountCode->code,
            'cart' => $cartItems
        ], 200);
    }

}
