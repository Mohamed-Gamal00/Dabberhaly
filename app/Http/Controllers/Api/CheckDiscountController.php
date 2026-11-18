<?php

namespace App\Http\Controllers\Api;

use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckDiscountCode;
use App\Models\Cart;
use App\Models\UserDiscountCode;
use App\Services\Discount\CheckDiscountService;

class CheckDiscountController extends Controller
{
    protected $checkDiscountCode;
    public function __construct(CheckDiscountService $checkDiscount)
    {
        $this->checkDiscountCode = $checkDiscount;
    }
    public function __invoke(CheckDiscountCode $request)
    {
        $discountCode = $this->checkDiscountCode->fetchDiscountCode($request);

        if (!$discountCode) {
            return ApiResponse::sendResponse(400, 'لقد انتهت صلاحية رمز الخصم هذا أو لم يعد صالحًا.');
        }

        $userUsedDiscountCode = $this->checkDiscountCode->checkIfUserUseDiscountCode($discountCode);
        if ($userUsedDiscountCode) {
            return $userUsedDiscountCode;
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
        UserDiscountCode::create([
            'cookie_id' => Cart::getCookieIdApi(),
            'discount_id' => $discountCode->id,
        ]);
        $discountCode->decrement('number_of_used');

        return ApiResponse::sendResponse(200, __('flash.success'), $result);
    }
}
