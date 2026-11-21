<?php

namespace App\Http\Controllers\Api\guest;

use App\Enums\OrderStatus;
use App\Events\UserOrder;
use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\Guest;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SendNewsToUser;
use App\Models\Setting;
use App\Models\ShippingTypesAndPrice;
use App\Models\UserAddress;
use App\Models\AppliedDiscountCode;
use App\Services\CheckOut\CheckoutServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CheckoutController extends Controller
{
    public function guestCheckout(Request $request, Order $order)
    {
        $guestId = $request->headers->get('guest-id');

        // Unknown payment methods must not silently become cash on
        // delivery: only the values the admin enabled in settings.
        if ($request->filled('payment_method')
            && ! in_array($request->payment_method, Setting::allowedPaymentMethodValues(), true)) {
            return response()->json([
                'message' => 'طريقة الدفع غير صحيحة',
                'errors' => ['payment_method' => ['طريقة الدفع غير صحيحة']],
            ], 422);
        }

        $isAddingNewAddress = !$request->has('user_address');
        if ($guestId) {
            $request->validate([
                'user_address' => $isAddingNewAddress ? 'nullable' : 'required',
                'terms' => 'required|boolean',
                'first_name' => $isAddingNewAddress ? 'required' : 'nullable',
                'last_name' => $isAddingNewAddress ? 'required' : 'nullable',
                'phone_number' => $isAddingNewAddress ? 'required' : 'nullable',
                'country_id' => $isAddingNewAddress ? 'required' : 'nullable',
                'city_id' => $isAddingNewAddress ? 'required' : 'nullable',
                'address' => $isAddingNewAddress ? 'required' : 'nullable',
            ], [
                'user_address.required' => 'Please select or add a new address',
                'first_name.required' => 'First name is required for the new address',
                'last_name.required' => 'Last name is required for the new address',
                'phone_number.required' => 'Phone number is required for the new address',
                'country_id.required' => 'Country is required for the new address',
                'city_id.required' => 'City is required for the new address',
                'address.required' => 'Address is required for the new address',
            ]);
        }


        if ($request->has('shipping_price') && ($request->shipping)) {
            return response()->json(['message' => 'لا يمكن تحديد اكثر من نوع شحن معا'], 422);
        }

        /*طرق الشحن*/
        $shipping = ShippingTypesAndPrice::find(1);

        // No shipping row configured — a clean error, not a 500 on null.
        if (! $shipping) {
            return response()->json([
                'message' => 'طريقة الشحن غير مُعدّة في المتجر بعد. يرجى التواصل مع الدعم.',
                'errors' => ['shipping' => ['طريقة الشحن غير مُعدّة في المتجر بعد.']],
            ], 422);
        }

        $selectedShippingOptions = 0;
        $shipping_price = 0;
        if ($request->has('pickup_from_store')) {
            $selectedShippingOptions++;
        }

        if ($request->has('fixed_shipping')) {
            $selectedShippingOptions++;
        }

        if ($request->has('shipping_based_on_weight')) {
            $selectedShippingOptions++;
        }

        if ($request->has('shipping_based_on_city')) {
            $selectedShippingOptions++;
        }
        if ($selectedShippingOptions > 1) {
            return response()->json([
                'error' => 'لا يمكن اختيار اكثر من طريقة شحن في اّن واحد'
            ], 400);
        }

        /*calc shipping price based on shipping selected*/
        if ($request->has('pickup_from_store') && $shipping->add_pickup_from_store == 1) {
            $shipping_price = 0;
        }//
        // سعر شحن ثابت
        elseif ($request->has('fixed_shipping') && $request->fixed_shipping == 1 && $shipping->add_normal_price == 1) {
            $shipping_price = $shipping->normal_shipping_price;
        } //
        // شحن بناء علي الوزن
        elseif ($request->has('shipping_based_on_weight') && $request->shipping_based_on_weight == 1 && $shipping->add_wight_price == 1) {
            $Cartitems = Cart::withoutGlobalScope('cookie_id')
                ->where('cookie_id', $guestId)
                ->where('status', 0)->get();

            $productWeight = [];

            foreach ($Cartitems as $item) {
                // Calculate the weight of each product multiplied by its quantity
                $productWeight[] = $item->product->weight * $item->quantity;
            }

            $shipping_price = array_sum($productWeight) * $shipping->weight_price;
        } //
        // شحن بناء علي المدينة
        elseif ($request->has('shipping_based_on_city') && $request->shipping_based_on_city == 1 && $shipping->add_price_based_on_city == 1) {
            if ($isAddingNewAddress) {
                $city_id = $request->city_id;
                $city = City::where('id', $city_id)->first();
                $shipping_price = $city->shipping_price;
            } else {
                $UserAddress = UserAddress::where('id', $request->user_address)->first();
                if ($UserAddress) {
                    $shipping_price = $UserAddress->city->shipping_price;
//                    return $city;
                }
            }
        } ///
        else {
            return ApiResponse::sendResponse(200, 'هذه الخدمة غير مفعلة');
        }

//        return $request->all();

//        return $request->all();
        $items = Cart::withoutGlobalScope('cookie_id')
            ->where('cookie_id', $guestId)
            ->where('status', 0)->get();

        if ($items->isEmpty()) {
            return ApiResponse::sendResponse(200, 'لا يمكن اتمام الطلب والسلة فارغة');
        }
//        return $guestId;

        DB::beginTransaction();
        try {

            // The guest identity is the cookie_id from the header: make
            // sure a Guest row exists for it, so the order can always
            // be listed/tracked afterwards with the same header.
            $guest = $guestId
                ? Guest::firstOrCreate(['cookie_id' => $guestId])
                : null;

            // Totals: discounted subtotal + shipping + VAT (VAT base =
            // the discounted items subtotal; 0 when no rate is set).
            // Free shipping waives the fee once the subtotal before
            // any coupon reaches the configured threshold.
            $subtotal = $this->total($guestId);
            $shippingPrice = $request->pickup_from_store == '1'
                ? 0
                : $shipping_price;
            if ($shippingPrice > 0 && CheckoutServices::freeShippingApplies($this->totalBeforeDiscount($guestId))) {
                $shippingPrice = 0;
            }
            $taxAmount = CheckoutServices::taxFor($subtotal);

            $order = Order::createWithUniqueNumber([
                'guest_id' => $guest?->id,
                'payment_method' => $request->payment_method, // cash on deleviry
                'status' => OrderStatus::Pending,
                'note' => $request->note,
                'shipping_price' => request()->pickup_from_store == '1' ? null : $shippingPrice,
                'tax_amount' => $taxAmount,
                'totalBeforeDiscount' => $this->totalBeforeDiscount($guestId),
                'total_price' => $subtotal + $shippingPrice + $taxAmount,
                'cookie_id' => $guestId ?: Cart::getCookieId()
            ]);

//            return $order;
//            return $items;

            /* items of cart items */
            foreach ($items as $cart_items) {
                // Check stock before creating the item, so a failure
                // leaves nothing behind.
                $product = Product::where('id', $cart_items->product_id)->lockForUpdate()->first();
                if (! $product || $product->quantity < $cart_items->quantity) {
                    throw new \Exception('الكمية المطلوبة غير متوفرة.', 400);
                }

                // Unit price — the admin view shows price as the unit
                // price and price * quantity as the line total, so the
                // line total must NOT be stored here. The coupon
                // discounted_price counts only when a discount code was
                // actually applied for this guest; otherwise a stale
                // leftover from an abandoned checkout must be ignored.
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $cart_items->product_id,
                    'product_name' => $cart_items->product->name, // product is the relation
                    'price' => $this->chargedUnitPrice($cart_items, $guestId), // product is the relation
                    'quantity' => $cart_items->quantity,
                ]);

                # NOTE:: Ensure that the user cannot add more items to the cart than the available quantity of the product.
                Product::where('id', $item->product_id)->update([
                    'quantity' => $product->quantity - $item->quantity
                ]);
//                return $request->all();
                # TODO:: make sure that user will use one option for address

                if ($isAddingNewAddress) {
                    $addressData['type'] = 'shipping';
                    $addressData['first_name'] = $request->first_name;
                    $addressData['last_name'] = $request->last_name;
                    $addressData['phone_number'] = $request->phone_number;
                    $addressData['country_id'] = $request->country_id;
                    $addressData['city_id'] = $request->city_id;
                    $addressData['address'] = $request->address;
                    $order->addresses()->create($addressData);
                }
            }

            foreach ($items as $item) {
                $product = Product::where('id', $item->product_id)->first();
                # TODO::check what is this mean ? check product quantity after checkout
                if ($product->quantity <= 0) {
                    $product->update([
                        'status' => 'archived'
                    ]);
                }
            }


            Cart::withoutGlobalScope('cookie_id')->where('cookie_id', $guestId)
                ->where('status', 0)
                ->update(['status' => 1]);

            DB::commit();

            // Notify admins and customer asynchronously through the UserOrder event.
            event(new UserOrder($order));
            if ($request->payment_method == 'card_payment') {

                $paymentLink = route('user.payment', ['order_number' => $order->number]);

                return response()->json([
                    'message' => 'Order created successfully',
                    'payment_url' => $paymentLink
                ], 201);
            } else {
                return response()->json([
                    'message' => 'Order created successfully',
                ], 201);
            }

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Guest checkout failed', ['exception' => $e]);
            return response()->json(['message' => 'Order creation failed'], 500);
        }
        return response()->json(['message' => 'Order created successfully', 'order' => $order], 201);


    }

    /* السعر قبل الخصم */
    public function totalBeforeDiscount($guestId): float
    {
        return Cart::with('product')->withoutGlobalScope('cookie_id')
            ->where('cookie_id', $guestId)->where('status', 0)->get()->sum(function ($item) {
                return $item->quantity * CheckoutServices::baseUnitPrice($item->product);
            });
    }

    /* السعر بعد الخصم لو فيه خصم*/
    public function total($guestId): float
    {
        $hasActiveDiscount = $guestId
            ? AppliedDiscountCode::where('cookie_id', $guestId)->exists()
            : false;

        return Cart::with('product')->withoutGlobalScope('cookie_id')
            ->where('cookie_id', $guestId)->where('status', 0)->get()->sum(function ($item) use ($hasActiveDiscount) {
                return $item->quantity * CheckoutServices::chargedUnitPrice($item, $hasActiveDiscount);
            });
    }

    /** Unit price actually charged for one guest cart row. */
    protected function chargedUnitPrice($cartItem, $guestId): float
    {
        $hasActiveDiscount = $guestId
            ? AppliedDiscountCode::where('cookie_id', $guestId)->exists()
            : false;

        return CheckoutServices::chargedUnitPrice($cartItem, $hasActiveDiscount);
    }
}
