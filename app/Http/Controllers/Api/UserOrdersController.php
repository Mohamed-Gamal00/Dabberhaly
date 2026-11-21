<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Events\OrderReturned;
use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductsResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Order\OrderRepository;
use App\Services\CheckOut\CheckoutServices;
use Illuminate\Http\Request;

class UserOrdersController extends Controller
{
    protected $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function mainOrders(Request $request)
    {
        $user = $request->user();

        $orders = Order::latest()
            ->with('products', 'orderItems', 'orderItems.product')
            ->where('user_id', $user->id)
            ->where('return_order', false)
            ->has('orderItems')
            ->paginate(6);
        if ($orders) {
            return ApiResponse::sendResponse(200, '', OrderResource::collection($orders));
        } else {
            return ApiResponse::sendResponse(200, 'لا يوجد طلبات لعرضها');

        }

    }


    public function showOrder(Request $request, $number)
    {
        $user = $request->user();

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
            ->where('user_id', $user->id)
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

//        $orderItem = OrderItem::where('product_id', $request->product_id)->firstOrFail();
//        $order = Order::where('number', $request->order_number)->firstOrFail();
//
//        // Delete the order item
//        $orderItem->delete();

        $order = Order::where('number', $request->order_number)
            ->where('user_id', $request->user()->id)
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
        if (!$order->orderItems()->exists()) {
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

        $order = Order::where('number', $request->order_number)
            ->where('user_id', $request->user()->id)
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
        $user = $request->user();

        // Fetch user with related return products and orders with products
        $returnProducts = User::with('returnProducts', 'orders.products')
            ->where('id', $user->id)
            ->first();

        if (!$returnProducts) {
            return response()->json(['message' => 'User or related data not found'], 404);
        }
        $products = $returnProducts->orders()
            ->where('return_order', true)
            ->paginate(10);

        if ($products->isEmpty()) {
            return ApiResponse::sendResponse(200, 'لا يوجد مرتجعات');
        }


        // Return JSON response
        return response()->json([
            'status_code' => 200,
            'message' => 'User return products retrieved successfully',
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
            // The order must belong to the logged-in user.
            $order = Order::where('id', $request->return_order_id)
                ->where('user_id', $request->user()->id)
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
