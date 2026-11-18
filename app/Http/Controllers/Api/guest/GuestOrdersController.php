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
use Illuminate\Http\Request;

class GuestOrdersController extends Controller
{
    public function mainOrders(Request $request)
    {
        $guest = $request->header('guest-id');
//        return $guest;
        $orders = Order::latest()
            ->with('products', 'orderItems', 'orderItems.product')
            ->where('guest_id', $guest)
            ->where('return_order', false)
            ->paginate(6);
//        return $orders;
        return response()->json([
            'orders' => OrderResource::collection($orders),

        ]);
    }

    public function showOrder(Request $request, $number)
    {
        $guest = $request->header('guest-id');
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
            ->where('guest_id', $guest)
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


        $order = Order::where('number', $request->order_number)
            ->where('guest_id', $request->header('guest-id'))
            ->firstOrFail();
        $orderItem = $order->orderItems()->where('product_id', $request->product_id)->firstOrFail();

        // Only a pending order can still be edited — once it moves on,
        // it is already being prepared / paid for.
        if ($order->return_order || $order->status !== OrderStatus::Pending) {
            return ApiResponse::sendResponse(422, 'لا يمكن تعديل هذا الطلب');
        }

        if ($orderItem->quantity > 1) {
            $orderItem->decrement('quantity', 1);
        } else {
            $orderItem->delete();
        }

        // Recalculate the totals after the deletion
        $newTotalBeforeDiscount = $order->orderItems->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });

        $newTotalPrice = $order->orderItems->sum(function ($item) {
            return $item->quantity * ($item->discounted_price ?? $item->product->price);
        });

        // Update the order with the new totals
        $order->update([
            'totalBeforeDiscount' => $newTotalBeforeDiscount,
            'total_price' => $newTotalPrice,
        ]);

        // Check if the order has no remaining items and delete it if necessary
        if (!$order->orderItems()->exists()) {
            $order->delete();
        }
        return ApiResponse::sendResponse(200, 'تم الحذف بنجاح');
    }


    /*  المرتجعات  */
    public function returns(Request $request)
    {
        $guest_id = $request->header('guest-id');
//        return $guest_id;
        // Fetch user with related return products and orders with products
        $returnProducts = Guest::with('returnProducts', 'orders.products')
            ->where('id', $guest_id)
            ->first();

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
            $order = Order::where('id', $request->return_order_id)
                ->where('guest_id', $request->header('guest-id'))
                ->first();

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

            event(new OrderReturned($order));
        }
        return response()->json(['status_code' => 200, 'message' => 'تم ارجاع الطلب بنجاح']);

    }


}
