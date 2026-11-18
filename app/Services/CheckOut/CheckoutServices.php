<?php

namespace App\Services\CheckOut;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\City;
use App\Models\DiscountCode;
use App\Models\Guest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SendNewsToUser;
use App\Models\ShippingTypesAndPrice;
use App\Models\UserAddress;
use App\Models\UserDiscountCode;
use App\Notifications\OrderCreatedAdminNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CheckoutServices
{
    public function checkJoinNews($request, $user)
    {
        if ($request->join_news) {
            $email = $user ? $user->email : $request->guest_email;
            if ($email) {
                SendNewsToUser::firstOrCreate(['subscription_email' => $email]);
            }
        }
    }

    public function checkUserAddressOptions($request)
    {
        // تعارض فقط لو اليوزر بعت عنوان محفوظ وعنوان جديد في نفس الوقت
        if ($request->has('user_address')
            && $request->user_address !== 'add_address'
            && (isset($request->addr['shipping']) || isset($request->addr['billing']))) {
            throw new \Exception('الرجاء اختيار خيار واحد فقط: إما عنوان محفوظ أو إضافة عنوان جديد.', 400);
        }
    }

    public function checkShippingSelectOptions($request)
    {
        $selectedShippingOptions = 0;
        if ($request->has('pickup_from_store'))        $selectedShippingOptions++;
        if ($request->has('fixed_shipping'))           $selectedShippingOptions++;
        if ($request->has('shipping_based_on_weight')) $selectedShippingOptions++;
        if ($request->has('shipping_based_on_city'))   $selectedShippingOptions++;

        if ($selectedShippingOptions > 1) {
            throw new \Exception('لا يمكن اختيار اكثر من طريقة شحن في اّن واحد', 400);
        }
    }

    public function calculateShippingPrice($request, $user): float
    {
        $shipping = ShippingTypesAndPrice::find(1);
        $shippingPrice = 0;

        // الاستلام من المتجر
        if ($request->has('pickup_from_store') && $shipping->add_pickup_from_store == 1) {
            $shippingPrice = 0;
        }
        // شحن ثابت
        elseif ($request->has('fixed_shipping') && $shipping->add_normal_price == 1) {
            $shippingPrice = $shipping->normal_shipping_price;
        }
        // شحن بناء علي الوزن
        elseif ($request->has('shipping_based_on_weight') && $shipping->add_wight_price == 1) {
            $cartItems = $this->getCartItems($user);
            $totalWeight = $cartItems->sum(fn($item) => ($item->product->weight ?? 0) * $item->quantity);
            $shippingPrice = $totalWeight * $shipping->weight_price;
        }
        // شحن بناء علي المدينة
        elseif ($request->has('shipping_based_on_city') && $shipping->add_price_based_on_city == 1) {
            // لو اليوزر اختار عنوان محفوظ — لازم يكون عنوانه هو، مش
            // عنوان عميل تاني. الزائر معندوش عناوين محفوظة أصلاً.
            if ($request->has('user_address') && $request->user_address !== 'add_address') {
                $userAddress = $user ? $user->addresses()->find($request->user_address) : null;
                if (!$userAddress) {
                    throw new \Exception('العنوان المحدد غير موجود. يرجى التحقق من صحة العنوان.', 400);
                }
                $cityId = $userAddress->city_id;
            } else {
                // لو بيضيف عنوان جديد
                $cityId = $request->addr['shipping']['city_id']
                    ?? $request->addr['billing']['city_id']
                    ?? null;
            }
            $city = City::find($cityId);
            $shippingPrice = $city ? $city->shipping_price : 0;
        } else {
            throw new \Exception('تأكد من اختيارك طريقة شحن متاحة', 200);
        }

        return (float) $shippingPrice;
    }

    /**
     * Record a discount code's use, exactly once, when the order is
     * created (inside the checkout transaction). Applying a code only
     * discounts the cart — abandoning checkout must not burn a use.
     */
    protected function consumeDiscountCode(): ?int
    {
        $discountCodeId = session('applied_discount_code_id');

        if (! $discountCodeId) {
            return null;
        }

        $code = DiscountCode::where('id', $discountCodeId)->lockForUpdate()->first();

        if (! $code || $code->status !== 'active' || $code->number_of_used <= 0) {
            throw new \Exception('كود الخصم غير متاح', 400);
        }

        UserDiscountCode::create([
            'cookie_id' => Cart::getCookieId(),
            'discount_id' => $code->id,
        ]);
        $code->decrement('number_of_used');

        return $code->id;
    }

    public function createOrder($request, $user, $shippingPrice): Order
    {
        $totalPrice          = $this->calculateTotal($user);
        $totalBeforeDiscount = $this->calculateTotalBeforeDiscount($user);
        $cookieId = Cart::getCookieId();

        // Consume the applied discount code now (not at apply time),
        // so an abandoned checkout never burns a use.
        $discountCodeId = $this->consumeDiscountCode();

        // For guest checkout: create or update a Guest record
        $guestId = null;

        if (!$user) {
            $addr     = $request->addr['billing'] ?? [];
            $guest    = Guest::updateOrCreate(
                ['cookie_id' => $cookieId],
                [
                    'first_name'   => $addr['first_name'] ?? null,
                    'family_name'  => $addr['last_name'] ?? null,
                    'email'        => $request->guest_email ?? null,
                    'phone_number' => $addr['phone_number'] ?? null,
                    'address'      => $addr['address'] ?? null,
                ]
            );
            $guestId = $guest->id;
        }

        return Order::createWithUniqueNumber([
            'user_id'             => $user?->id,
            'guest_id'            => $guestId,
            'discount_code_id'    => $discountCodeId,
            'payment_method'      => $request->payment_method,
            'status'              => OrderStatus::Pending,
            'note'                => $request->note,
            'shipping_price'      => $shippingPrice,
            'totalBeforeDiscount' => $totalBeforeDiscount,
            'total_price'         => $totalPrice + $shippingPrice,
            'cookie_id'           => $user ? null : $cookieId,
        ]);
    }

    public function createOrderItems($order, $user)
    {
        $cartItems = $this->getCartItems($user);

        foreach ($cartItems as $cartItem) {
            // Lock the product row and refuse to oversell: without the
            // check the quantity could go negative. Checked before the
            // item is created so a failure leaves nothing behind.
            $product = Product::where('id', $cartItem->product_id)->lockForUpdate()->first();

            if (! $product || $product->quantity < $cartItem->quantity) {
                throw new \Exception('الكمية المطلوبة غير متوفرة.', 400);
            }

            $orderItem = OrderItem::create([
                'order_id'     => $order->id,
                'product_id'   => $cartItem->product_id,
                'product_name' => $cartItem->product->name,
                'price'        => $cartItem->product->discount_price ?? $cartItem->product->price,
                'quantity'     => $cartItem->quantity,
            ]);

            $product->decrement('quantity', $cartItem->quantity);
        }
    }

    public function calculateTotal($user): float
    {
        // السعر بعد الخصم لو فيه خصم
        return $this->getCartItems($user)
            ->sum(function ($item) {
                if ($item->product->discount_price) {
                    return $item->quantity * ($item->discounted_price ?? $item->product->discount_price);
                } else {
                    return $item->quantity * ($item->discounted_price ?? $item->product->price);
                }
            });
    }

    public function calculateTotalBeforeDiscount($user): float
    {
        return $this->getCartItems($user)
            ->sum(function ($item) {
                if ($item->product->discount_price) {
                    return $item->quantity * $item->product->discount_price;
                } else {
                    return $item->quantity * $item->product->price;
                }
            });
    }

    public function isAddingNewAddress($order, $request, $user)
    {
        // الحالة الأولى: اليوزر اختار عنوان محفوظ — لازم يكون عنوانه هو
        if ($request->has('user_address') && $request->user_address !== 'add_address') {
            $UserAddress = $user ? $user->addresses()->find($request->user_address) : null;
            if ($UserAddress) {
                $order->addresses()->create([
                    'type'         => 'shipping',
                    'first_name'   => $UserAddress->first_name,
                    'last_name'    => $UserAddress->family_name,
                    'phone_number' => $UserAddress->phone_number,
                    'country_id'   => $UserAddress->country_id,
                    'city_id'      => $UserAddress->city_id,
                    'address'      => $UserAddress->address,
                    'email'        => $user?->email,
                ]);
            }
            return;
        }

        // الحالة الثانية: يوزر مسجل بيضيف عنوان شحن جديد
        if (isset($request->addr['shipping'])) {
            $addr = $request->addr['shipping'];
            $order->addresses()->create([
                'type'         => 'shipping',
                'first_name'   => $addr['first_name'] ?? null,
                'last_name'    => $addr['last_name'] ?? null,
                'phone_number' => $addr['phone_number'] ?? null,
                'country_id'   => $addr['country_id'] ?? null,
                'city_id'      => $addr['city_id'] ?? null,
                'address'      => $addr['address'] ?? null,
                'email'        => $user?->email,
            ]);

            // حفظ العنوان في قائمة عناوين اليوزر للاستخدام مستقبلاً
            if ($user) {
                UserAddress::create([
                    'user_id'       => $user->id,
                    'address_title' => 'عنوان جديد',
                    'first_name'    => $addr['first_name'] ?? null,
                    'family_name'   => $addr['last_name'] ?? null,
                    'phone_number'  => $addr['phone_number'] ?? null,
                    'country_id'    => $addr['country_id'] ?? null,
                    'city_id'       => $addr['city_id'] ?? null,
                    'address'       => $addr['address'] ?? null,
                    'main_address'  => false,
                ]);
            }
            return;
        }

        // الحالة الثالثة: زائر بيضيف عنوان billing
        if (isset($request->addr['billing'])) {
            $addr = $request->addr['billing'];
            $order->addresses()->create([
                'type'         => 'billing',
                'first_name'   => $addr['first_name'] ?? null,
                'last_name'    => $addr['last_name'] ?? null,
                'phone_number' => $addr['phone_number'] ?? null,
                'country_id'   => $addr['country_id'] ?? null,
                'city_id'      => $addr['city_id'] ?? null,
                'address'      => $addr['address'] ?? null,
                'email'        => $request->guest_email,
            ]);
        }
    }

    public function getCartItems($user)
    {
        $query = Cart::with('product')->withoutGlobalScope('cookie_id')->where('status', 0);

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('cookie_id', Cart::getCookieId());
        }

        return $query->get();
    }

    public function disActiveProduct($cartItems)
    {
        // تعطيل المنتج لو الكمية وصلت للصفر أو أقل
        foreach ($cartItems as $item) {
            $product = Product::where('id', $item->product_id)->first();
            if ($product && $product->quantity <= 0) {
                $product->update(['status' => 'archived']);
            }
        }
    }

    public function updateProductStatue($user)
    {
        // تحويل حالة السلة من نشطة (0) إلى منتهية (1) بعد إتمام الطلب
        $query = Cart::withoutGlobalScope('cookie_id')->where('status', 0);

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('cookie_id', Cart::getCookieId());
        }

        $query->update(['status' => 1]);
    }

    public function sendNotificationToAdmin($order)
    {
        $admins = Admin::all();
        Notification::send($admins, new OrderCreatedAdminNotification($order));
    }

    public function checkPaymentMethod($request, $order)
    {
        if ($request->payment_method == 'card_payment') {
            // توجيه لصفحة الدفع بالبطاقة
            return response()->json([
                'redirect' => route('user.payment', ['order_number' => $order->number]),
            ], 201);
        }

        // الدفع عند الاستلام — توجيه للصفحة الرئيسية
        return response()->json([
            'redirect' => route('home'),
            'message'  => 'تم إتمام الطلب بنجاح',
        ], 201);
    }

    /**
     * Merge guest cart (by cookie_id) into the authenticated user's cart.
     * Called right after login or register.
     *
     * Wrapped in a DB transaction with row-level locking to prevent race
     * conditions when two requests try to merge the same guest cart or when
     * the user adds items concurrently.
     */
    public function mergeGuestCart(int $userId): void
    {
        $cookieId = Cart::getCookieId();

        DB::transaction(function () use ($cookieId, $userId) {
            // Lock and fetch guest cart items (cookie_id match, active, no user)
            $guestItems = Cart::withoutGlobalScope('cookie_id')
                ->where('cookie_id', $cookieId)
                ->where('status', 0)
                ->whereNull('user_id')
                ->lockForUpdate()
                ->get();

            foreach ($guestItems as $guestItem) {
                // Lock and check if user already has this product
                $existing = Cart::withoutGlobalScope('cookie_id')
                    ->where('user_id', $userId)
                    ->where('product_id', $guestItem->product_id)
                    ->where('status', 0)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    // Merge quantities (cap at product stock)
                    $product = $guestItem->product;
                    $newQty  = $existing->quantity + $guestItem->quantity;
                    $maxQty  = $product ? $product->quantity : $newQty;
                    $existing->update(['quantity' => min($newQty, $maxQty)]);
                    // Mark the old guest item as completed
                    $guestItem->update(['status' => 1]);
                } else {
                    // Reassign to user — active_key changes from g:cookie:p:X to u:user:p:X
                    $guestItem->update([
                        'user_id' => $userId,
                    ]);
                }
            }
        });
    }

    /**
     * Transfer an authenticated user's cart to a new guest identity.
     * Called during logout so the user's cart items are preserved but
     * become inaccessible to the authenticated user and the old guest identity.
     *
     * Generates a fresh guest UUID, reassigns the user's active cart items
     * (user_id → null, cookie_id → new UUID), and queues the new cookie
     * so the browser picks it up on the next request.
     */
    public function transferCartToGuest(int $userId): void
    {
        $newCookieId = Cart::generateNewGuestCookie();

        DB::transaction(function () use ($userId, $newCookieId) {
            // Lock and fetch the user's active cart items
            $userItems = Cart::withoutGlobalScope('cookie_id')
                ->where('user_id', $userId)
                ->where('status', 0)
                ->lockForUpdate()
                ->get();

            foreach ($userItems as $item) {
                // Check if a guest cart already has this product with the new cookie
                // (unlikely since the cookie is fresh, but handle it safely)
                $existingGuestItem = Cart::withoutGlobalScope('cookie_id')
                    ->where('cookie_id', $newCookieId)
                    ->where('product_id', $item->product_id)
                    ->where('status', 0)
                    ->whereNull('user_id')
                    ->lockForUpdate()
                    ->first();

                if ($existingGuestItem) {
                    // Merge quantities (cap at stock)
                    $product = $item->product;
                    $newQty  = $existingGuestItem->quantity + $item->quantity;
                    $maxQty  = $product ? $product->quantity : $newQty;
                    $existingGuestItem->update(['quantity' => min($newQty, $maxQty)]);
                    // Mark the old user item as completed
                    $item->update(['status' => 1]);
                } else {
                    // Transfer to the new guest identity
                    // active_key changes from u:user:p:X to g:newCookie:p:X
                    $item->update([
                        'user_id'   => null,
                        'cookie_id' => $newCookieId,
                    ]);
                }
            }
        });
    }
}
