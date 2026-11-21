<?php

namespace App\Http\Controllers\Api\guest;

use App\Enums\OrderStatus;
use App\Events\OrderReturned;
use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Guest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\Order\OrderRepository;
use App\Services\CheckOut\CheckoutServices;
use Illuminate\Http\Request;

class GuestOrdersController extends Controller
{
    protected $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }
    /**
     * Resolve the guest from the guest-id header. The header carries
     * the cookie_id (the mobile guest identity) — never the numeric
     * guest id — so look the Guest row up by cookie_id first.
     */
    protected function resolveGuest(Request $request): ?Guest
    {
        $cookieId = $request->header('guest-id');

        if (! $cookieId) {
            return null;
        }

        return Guest::where('cookie_id', $cookieId)->first();
    }

    public function mainOrders(Request $request)
    {
        $guest = $this->resolveGuest($request);

        // No Guest row for this cookie_id: the guest has no orders.
        if (! $guest) {
            return response()->json(['orders' => []]);
        }

        $orders = Order::latest()
            ->with('products', 'orderItems', 'orderItems.product')
            ->where('guest_id', $guest->id)
            ->where('return_order', false)
            ->paginate(6);
//        return $orders;
        return response()->json([
            'orders' => OrderResource::collection($orders),

        ]);
    }

    public function showOrder(Request $request, $number)
    {
        $guest = $this->resolveGuest($request);
        if (! $guest) {
            return ApiResponse::sendResponse(404, 'الطلب غير موجود');
        }
        if (!$number) {
            return response()->json([
                'message' => 'Order number is required',
                'errors' => [
                    'number' => ['Order number is required.']
                ]
            ], 400); // Bad Request
        }

        $order = Order::latest()
            ->with('products', 'orderItems', 'orderItems.product')
            ->where('guest_id', $guest->id)
            ->where('return_order', false)
            ->where('number', $number)
            ->first();

        if (!$order) {
            return ApiResponse::sendResponse(404, 'الطلب غير موجود');
        }
//        return $order;
        $data = [
            'order' => new  OrderResource($order),
            'order status' => $order->status->label(),
        ];
        return ApiResponse::sendResponse(200, '', $data);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:order_items,product_id',
            'order_number' => 'required|string|exists:orders,number',
        ]);

        $guest = $this->resolveGuest($request);

        if (! $guest) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order = Order::where('number', $request->order_number)
            ->where('guest_id', $guest->id)
            ->firstOrFail();
        $orderItem = $order->orderItems()->where('product_id', $request->product_id)->firstOrFail();

        // Only a pending order can still be edited — once it moves on,
        // it is already being prepared / paid for.
        if ($order->return_order || $order->status !== OrderStatus::Pending) {
            return ApiResponse::sendResponse(422, 'لا يمكن تعديل هذا الطلب');
        }

        // A paid order must not be edited: the customer already paid
        // for these items.
        if ($order->payment_status === 'paid') {
            return ApiResponse::sendResponse(422, 'لا يمكن تعديل طلب مدفوع');
        }

        // Return the removed quantity to stock.
        $removedQuantity = $orderItem->quantity > 1 ? 1 : $orderItem->quantity;

        if ($orderItem->quantity > 1) {
            $orderItem->decrement('quantity', 1);
        } else {
            $orderItem->delete();
        }

        Product::where('id', $request->product_id)->increment('quantity', $removedQuantity);

        // Recalculate from the STORED order-item prices (never the live
        // product price), including shipping and VAT.
        $remainingItems = OrderItem::where('order_id', $order->id)->get();
        $newSubtotal = $remainingItems->sum(fn ($item) => $item->quantity * $item->price);
        $newTax = CheckoutServices::taxFor($newSubtotal);

        // Update the order with the new totals
        $order->update([
            'totalBeforeDiscount' => $newSubtotal,
            'tax_amount' => $newTax,
            'total_price' => $newSubtotal + (float) ($order->shipping_price ?? 0) + $newTax,
        ]);

        // Check if the order has no remaining items and delete it if necessary
        if (! $order->orderItems()->exists()) {
            $order->delete();
        }
        return ApiResponse::sendResponse(200, 'تم الحذف بنجاح');
    }


    /*  إلغاء الطلب  */
    public function cancel(Request $request)
    {
        $request->validate([
            'order_number' => 'required|string|exists:orders,number',
        ]);

        $guest = $this->resolveGuest($request);

        if (! $guest) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order = Order::where('number', $request->order_number)
            ->where('guest_id', $guest->id)
            ->firstOrFail();

        // Delivered / already-cancelled / already-returned orders
        // cannot be cancelled (see OrderRepository::cancel).
        $cancelled = $this->orderRepository->cancel($order->id);

        if (! $cancelled) {
            return ApiResponse::sendResponse(422, 'لا يمكن إلغاء هذا الطلب');
        }

        return ApiResponse::sendResponse(200, 'تم إلغاء الطلب بنجاح');
    }


    /*  المرتجعات  */
    public function returns(Request $request)
    {
        // The header carries the cookie_id, not the numeric guest id.
        $guest = $this->resolveGuest($request);
//        return $guest_id;
        // Fetch user with related return products and orders with products
        $returnProducts = $guest
            ? Guest::with('returnProducts', 'orders.products')->find($guest->id)
            : null;

        if (!$returnProducts) {
            return response()->json(['message' => 'Guest or related data not found'], 404);
        }
        $products = $returnProducts->orders()
            ->where('return_order', true)
            ->paginate(10);

        if ($products->isEmpty()) {
            return response()->json([
                'status_code' => 404,
                'message' => 'لا يوجد مرتجعات'
            ], 404);
        }


        // Return JSON response
        return response()->json([
            'status_code' => 200,
            'message' => 'success',
            'data' => [
                'products' => OrderResource::collection($products),
            ]
        ]);
    }

    /*  make return order  */

    public function store(Request $request)
    {

        $request->validate([
            'return_order_id' => 'required',
        ]);

        // If return_order_id is provided, update the order to mark it as a return order
        if ($request->return_order_id) {
            // The order must belong to the guest identified by the guest-id header.
            $guest = $this->resolveGuest($request);
            $order = $guest
                ? Order::where('id', $request->return_order_id)
                    ->where('guest_id', $guest->id)
                    ->first()
                : null;

            if (!$order) {
                return response()->json(['status_code' => 404, 'message' => 'Order not found'], 404);
            }

            // Already returned: succeed quietly, no second admin alert.
            if ($order->return_order) {
                return response()->json(['status_code' => 200, 'message' => 'تم ارجاع الطلب بنجاح']);
            }

            // Only a pending order can be returned — once it moves on, it
            // is already being prepared.
            if (! $order->canBeReturned()) {
                return response()->json(['status_code' => 422, 'message' => 'لا يمكن إرجاع هذا الطلب'], 422);
            }

            $order->update([
                'return_order' => true,
                'status'       => OrderStatus::Pending,
            ]);

            // Returned items go back to stock. payment_status is left
            // untouched on purpose: refunds are manual, via the Moyasar
            // dashboard.
            foreach ($order->orderItems as $orderItem) {
                Product::where('id', $orderItem->product_id)
                    ->increment('quantity', $orderItem->quantity);
            }

            event(new OrderReturned($order));
        }
        return response()->json(['status_code' => 200, 'message' => 'تم ارجاع الطلب بنجاح']);

    }


}
