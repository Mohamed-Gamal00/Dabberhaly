<?php

namespace App\Http\Controllers\Inertia;

use App\Events\UserOrder;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\ShippingTypesAndPrice;
use App\Models\AppliedDiscountCode;
use App\Repositories\Cart\CartRepository;
use App\Services\CheckOut\CheckoutServices;
use App\Services\Discount\CheckDiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

class CheckoutController extends Controller
{
    protected CheckoutServices $checkoutService;

    public function __construct(CheckoutServices $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    public function create(CartRepository $cart)
    {
        // One cart query for the whole page load (the page used to run
        // the same query five times).
        $items = $cart->get();

        if ($items->count() == 0) {
            return redirect()->route('home');
        }

        foreach ($items as $item) {
            $item->update(['weight' => $item->quantity * ($item->product->weight ?? 0)]);
        }

        $countries  = DB::table('countries')->where('status', 'used')->get();
        $countryIds = DB::table('countries')->where('status', 'used')->pluck('id')->toArray();
        $cities     = DB::table('cities')->whereIn('country_id', $countryIds)->where('status', 'used')->get();
        $shipping   = ShippingTypesAndPrice::find(1);

        $discount      = DB::table('applied_discount_codes')->where('cookie_id', Cart::getCookieId())->first();
        $discountPrice = $discount ? DiscountCode::where('id', $discount->discount_id)->value('price') : null;

        $cartItems = $items->map(fn($item) => [
            'id'             => $item->id,
            'product_id'     => $item->product_id,
            'name'           => $item->product?->name,
            'name_en'        => $item->product?->name_en,
            'image'          => $item->product?->image_url,
            'price'          => (float) $item->product?->price,
            'discount_price' => $item->product?->discount_price ? (float) $item->product->discount_price : null,
            'weight'         => (float) ($item->product?->weight ?? 0),
            'quantity'       => (int) $item->quantity,
        ]);

        $user          = Auth::guard('web')->user();
        $userAddresses = $user
            ? $user->addresses()->get()->map(fn($a) => [
                'id'            => $a->id,
                'address_title' => $a->address_title,
                'first_name'    => $a->first_name,
                'family_name'   => $a->family_name,
                'phone_number'  => $a->phone_number,
                'address'       => $a->address,
                'country_id'    => $a->country_id,
                'city_id'       => $a->city_id,
                'main_address'  => $a->main_address,
            ])
            : collect();

        return Inertia::render('Checkout/Index', [
            'cartItems'     => $cartItems,
            'total'         => $cart->total(),
            'totalBefore'   => $cart->totalBeforeDiscount(),
            'countries'     => $countries,
            'cities'        => $cities,
            'shipping'      => $shipping,
            'discountPrice' => $discountPrice,
            'userAddresses' => $userAddresses,
            // The summary needs these to show the VAT line and to
            // waive shipping at the free-shipping threshold.
            'vatRate'             => CheckoutServices::vatRate(),
            'freeShippingThreshold' => CheckoutServices::freeShippingThreshold(),
        ]);
    }

    public function store(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $user   = Auth::guard('web')->user();
        $isNew  = isset($request->addr['shipping']) || isset($request->addr['billing']);
        $isGuest = !$user;

        // ===== Validation =====

        // No mandatory terms checkbox: placing the order implies consent
        // (the button is followed by a note linking to the terms and the
        // privacy policy), so a blocking checkbox only adds friction
        // at the moment of purchase.

        // Authenticated user with existing address
        if ($user && !$isNew) {
            $request->validate([
                'user_address' => 'required|exists:user_addresses,id',
            ], [
                'user_address.required' => 'برجاء اختيار عنوان أو إضافة عنوان جديد',
                'user_address.exists'   => 'العنوان المحدد غير موجود',
            ]);
        }

        // New address fields (guest billing or user adding new shipping)
        if ($isNew) {
            $addrType = $isGuest ? 'billing' : 'shipping';
            $request->validate([
                "addr.{$addrType}.first_name"   => ['required', 'string', 'max:255'],
                "addr.{$addrType}.last_name"    => ['required', 'string', 'max:255'],
                "addr.{$addrType}.phone_number" => ['required', 'string', 'max:20'],
                "addr.{$addrType}.address"      => ['required', 'string', 'max:500'],
                "addr.{$addrType}.country_id"   => ['required', 'exists:countries,id'],
                "addr.{$addrType}.city_id"      => ['required', 'exists:cities,id'],
            ], [
                "addr.{$addrType}.first_name.required"   => 'الاسم الأول مطلوب',
                "addr.{$addrType}.last_name.required"    => 'اسم العائلة مطلوب',
                "addr.{$addrType}.phone_number.required" => 'رقم الجوال مطلوب',
                "addr.{$addrType}.address.required"      => 'العنوان التفصيلي مطلوب',
                "addr.{$addrType}.country_id.required"   => 'الدولة مطلوبة',
                "addr.{$addrType}.country_id.exists"     => 'الدولة المحددة غير صحيحة',
                "addr.{$addrType}.city_id.required"      => 'المدينة مطلوبة',
                "addr.{$addrType}.city_id.exists"        => 'المدينة المحددة غير صحيحة',
            ]);

            if ($isGuest) {
                $request->validate([
                    'guest_email' => ['required', 'email'],
                ], [
                    'guest_email.required' => 'البريد الإلكتروني مطلوب',
                    'guest_email.email'    => 'البريد الإلكتروني غير صحيح',
                ]);
            }
        }

        // Payment method
        $request->validate([
            'payment_method' => ['required', 'in:cash_on_delivery,card_payment'],
        ], [
            'payment_method.required' => 'طريقة الدفع مطلوبة',
            'payment_method.in'       => 'طريقة الدفع غير صحيحة',
        ]);

        // handle requests
        try {
            $this->checkoutService->checkShippingSelectOptions($request);
            $this->checkoutService->checkUserAddressOptions($request);

            $shipping_price = $this->checkoutService->calculateShippingPrice($request, $user);

            $cartItems = $this->checkoutService->getCartItems($user);

            if ($cartItems->isEmpty()) {
                return response()->json(['message' => __('flash.cart_empty_checkout')], 422);
            }

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        // handle database processing
        DB::beginTransaction();
        try {
            $this->checkoutService->checkJoinNews($request, $user);

            $order = $this->checkoutService->createOrder($request, $user, $shipping_price);
            $this->checkoutService->createOrderItems($order, $user);

            $this->checkoutService->isAddingNewAddress($order, $request, $user);


            $this->checkoutService->disActiveProduct($cartItems);

            $this->checkoutService->updateProductStatue($user);

            // Clear applied discount from session
            session()->forget('applied_discount_code_id');

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Web checkout failed', ['exception' => $e]);
            return response()->json(['message' => __('flash.order_creation_failed')], 500);
        }

        // Notify admins and customer asynchronously through the UserOrder event.
        // Dispatched AFTER commit so queued listeners only run for an
        // order that actually exists — never for a rolled-back one.
        event(new UserOrder($order));

        return $this->checkoutService->checkPaymentMethod($request, $order, $user);
    }

    /**
     * Order success page — the landing for guest cash-on-delivery
     * orders (logged-in customers land on their profile instead).
     *
     * A bare order number is not enough: the page only serves the
     * order owner — the logged-in user for a user order, or the
     * browser holding the cart cookie the order was placed with for
     * a guest order.
     */
    public function success(Request $request)
    {
        $order = Order::where('number', $request->query('order_number'))
            ->with('orderItems')
            ->first();

        if (! $order) {
            return redirect()->route('home');
        }

        if (! $this->ownsOrder($order)) {
            abort(404);
        }

        return Inertia::render('Checkout/Success', [
            'order' => [
                'number'         => $order->number,
                'items_count'    => $order->orderItems->sum('quantity'),
                'total_price'    => (float) $order->total_price,
                'shipping_price' => (float) ($order->shipping_price ?? 0),
                'payment_method' => $order->payment_method,
                'is_guest'       => is_null($order->user_id),
            ],
        ]);
    }

    /**
     * Validate and apply a discount code — returns JSON for the Vue checkout page.
     */
    public function applyDiscount(Request $request)
    {
        $request->validate(['discount_code' => 'required|string']);

        $user = Auth::guard('web')->user();

        // 1. Guests cannot use discount codes
        if (!$user) {
            return response()->json([
                'message'       => __('flash.login_required_discount'),
                'requires_login' => true,
            ], 401);
        }

        $code = DiscountCode::where('code', $request->discount_code)
            ->where('status', 'active')
            ->where('number_of_used', '>', 0)
            ->first();

        if (!$code) {
            return response()->json(['message' => __('flash.invalid_discount_code')], 422);
        }

        // 2. If code is product-specific, check that at least one cart item matches
        $cartItems = $this->checkoutService->getCartItems($user);
        $isGlobal  = $code->products()->count() === 0;

        if (!$isGlobal) {
            $codeProductIds  = $code->products->pluck('id')->toArray();
            $cartProductIds  = $cartItems->pluck('product_id')->toArray();
            $hasMatchingItem = count(array_intersect($codeProductIds, $cartProductIds)) > 0;

            if (!$hasMatchingItem) {
                return response()->json([
                    'message' => __('flash.discount_not_applicable'),
                ], 422);
            }
        }

        $cookieId    = Cart::getCookieId();
        $alreadyUsed = AppliedDiscountCode::where('cookie_id', $cookieId)
            ->where('discount_id', $code->id)
            ->exists();

        if ($alreadyUsed) {
            return response()->json(['message' => __('flash.discount_already_used')], 422);
        }

        // Already applied to this checkout (consumption happens at
        // order creation, so there is no usage row yet to check).
        if (session('applied_discount_code_id') == $code->id) {
            return response()->json(['message' => __('flash.discount_already_used')], 422);
        }

        // Apply discount to matching cart items only — shared with
        // the API flows: a fixed amount applies ONCE per order.
        CheckDiscountService::applyDiscountToItems($cartItems, $code, $isGlobal);

        $newTotal = $this->checkoutService->calculateTotal($user);

        // Store in session so createOrder can attach it to the order
        session(['applied_discount_code_id' => $code->id]);

        return response()->json([
            'message'        => __('flash.discount_applied_success'),
            'discount_code'  => $code->code,
            'discount_value' => (float) $code->price,
            'discount_type'  => $code->discount_type,
            'new_total'      => (float) $newTotal,
        ]);
    }

    /**
     * Remove an applied discount code for real: forget the session
     * code and reset discounted_price on the active cart rows, so a
     * later checkout is charged the full price and the code is not
     * consumed.
     */
    public function removeDiscount(Request $request)
    {
        $user = Auth::guard('web')->user();

        session()->forget('applied_discount_code_id');

        $this->checkoutService->getCartItems($user)
            ->each(fn ($item) => $item->update(['discounted_price' => null]));

        return response()->json([
            'message'   => __('flash.discount_removed'),
            'new_total' => (float) $this->checkoutService->calculateTotal($user),
        ]);
    }

    /**
     * Does the current browser own this order? Logged-in user orders
     * need the matching user; guest orders need the cart cookie the
     * order was placed with.
     */
    protected function ownsOrder(Order $order): bool
    {
        $user = Auth::guard('web')->user();

        if ($order->user_id) {
            return $user !== null && (int) $user->id === (int) $order->user_id;
        }

        return $order->cookie_id !== null
            && $order->cookie_id === Cart::getCookieId();
    }
}
