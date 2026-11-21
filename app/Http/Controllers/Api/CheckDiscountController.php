<?php

namespace App\Http\Controllers\Api;

use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckDiscountCode;
use App\Models\AppliedDiscountCode;
use App\Models\Cart;
use App\Services\CheckOut\CheckoutServices;
use App\Services\Discount\CheckDiscountService;
use Illuminate\Http\Request;

class CheckDiscountController extends Controller
{
    protected $checkDiscountCode;
    protected $checkoutService;
    public function __construct(CheckDiscountService $checkDiscount, CheckoutServices $checkoutService)
    {
        $this->checkDiscountCode = $checkDiscount;
        $this->checkoutService = $checkoutService;
    }
    public function __invoke(CheckDiscountCode $request)
    {
        $discountCode = $this->checkDiscountCode->fetchDiscountCode($request);

        if (!$discountCode) {
            return ApiResponse::sendResponse(400, 'لقد انتهت صلاحية رمز الخصم هذا أو لم يعد صالحًا.');
        }

        $discountAlreadyApplied = $this->checkDiscountCode->checkIfDiscountCodeAlreadyApplied($discountCode);
        if ($discountAlreadyApplied) {
            return $discountAlreadyApplied;
        }

        // بشوف لو كود الخصم مش متحدد لمنتجات معينة هيبقي خصم علي كل المنتجات
        $isGlobalDiscount = $discountCode->products()->count() === 0;

        $user = $request->user();

        // Apply discount to cart items — nothing is consumed if the
        // cart is empty.
        $result = $this->checkDiscountCode->applyDiscountToCartItems($discountCode, $isGlobalDiscount, $user);

        if ($result === null) {
            return ApiResponse::sendResponse(200, __('flash.discount_cart_empty'));
        }

        // Consume the code only now that it actually applied.
        AppliedDiscountCode::create([
            'cookie_id' => Cart::getCookieIdApi(),
            'discount_id' => $discountCode->id,
        ]);
        $discountCode->decrement('number_of_used');

        return ApiResponse::sendResponse(200, __('flash.success'), $result);
    }

    /**
     * Remove an applied discount code — the API mirror of the web
     * POST /checkout/remove-discount: reset discounted_price on the
     * user's cart rows and delete the AppliedDiscountCode row, so a
     * later checkout is charged the full price and the code is not
     * consumed.
     */
    public function removeDiscount(Request $request)
    {
        $user = $request->user();

        session()->forget('applied_discount_code_id');

        $cartItems = $this->checkoutService->getCartItems($user);
        $cartItems->each(fn ($item) => $item->update(['discounted_price' => null]));

        AppliedDiscountCode::whereIn(
            'cookie_id',
            $cartItems->pluck('cookie_id')->filter()->unique()
        )->delete();

        return ApiResponse::sendResponse(200, __('flash.discount_removed'), [
            'new_total' => (float) $this->checkoutService->calculateTotal($user),
            'summary' => $this->checkoutService->checkoutSummary($user),
        ]);
    }
}
