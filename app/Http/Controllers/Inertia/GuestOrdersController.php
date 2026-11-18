<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\OrderStatus;
use App\Events\OrderReturned;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Guest;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuestOrdersController extends Controller
{
    /* مشاهدة الطلب */
    public function orders($number)
    {
        $guest_cookie_id = Cart::getCookieId();
        $order = Order::latest()->with('products', 'orderItems', 'orderItems.product')->where('cookie_id', $guest_cookie_id)
            ->where('return_order', false)
            ->where('number', $number)
            ->first();

        return view('front.guest_orders.orders', compact('order'));
    }

    /* حذف الطلب */
    public function guestOrderDelete(Request $request)
    {
        $orderItem = OrderItem::where('product_id', $request->product_id)->first();
        $order = OrderItem::findOrFail($orderItem->id);
        $order->delete();

        $currenctOrder = Order::where('number', $request->order_number)
            ->has('orderItems')
            ->first();

        if (is_null($currenctOrder)) {
            $order = Order::where('number', $request->order_number)->first();
            $order->delete();
        }


        return back()->with('danger', 'تم مسح الطلب ');
    }

    /* مرتجعات */
    public function guestReturnProductsindex()
    {
        $products = Order::with('products')
            ->where('cookie_id', Cart::getCookieId())
            ->where('return_order', true)
            ->paginate(10);

        return view('front.guest_orders.guest_return_products', compact('products'));
    }

    /* ارجاع */
    public function store(Request $request)
    {
        if ($request->return_order_id) {
            // The order must belong to this guest's cart cookie.
            $order = Order::where('id', $request->return_order_id)
                ->where('cookie_id', Cart::getCookieId())
                ->firstOrFail();

            // Already returned (e.g. double click): succeed quietly, no second alert.
            if (! $order->return_order) {
                // Only a pending order can be returned — once it moves on,
                // it is already being prepared.
                if (! $order->canBeReturned()) {
                    return back()->with('danger', __('flash.return_not_allowed'));
                }

                $order->update([
                    'return_order' => true,
                    'status'       => OrderStatus::Pending,
                ]);

                event(new OrderReturned($order));
            }
        }

        // NOTE: this controller currently has no web route and its old
        // redirect target (guest.main.orders) does not exist, so redirect
        // back instead of to a route name that would 404.
        return back()->with('success', 'تم اضافة المنتج لقائمة الارجاع');
    }

    /* الطلبات */
    public function mainOrders()
    {
        $orders = Order::latest()->with('products', 'orderItems', 'orderItems.product')
            ->where('cookie_id', Cart::getCookieId())
            ->where('return_order', false)
            ->has('orderItems')
            ->paginate(6);

        return view('front.guest_orders.main_orders', compact('orders'));
    }

    /* المفضلة */
    public function guestWishList(Request $request)
    {
        $guestId = $this->getGuestId($request);
//        dd($guestId);
        $guest = Guest::find($guestId);

        if ($guest) {
            $products = $guest->wishlistProducts;
            return view('front.guest_orders.guest_wishlist', compact('products'));
        }
        $products = [];
        return view('front.guest_orders.guest_wishlist', compact('products'));
    }

    private function getGuestId(Request $request)
    {
        $cookieId = $request->cookie('guest_id');
        if (!$cookieId) {
            $cookieId = (string)Str::uuid();
            cookie()->queue('guest_id', $cookieId, 60 * 24 * 30); // Store guest_id for 30 days
        }

        $guest = Guest::firstOrCreate(['cookie_id' => $cookieId]);

        return $guest->id; // Return the auto-incrementing guest_id
    }
}
