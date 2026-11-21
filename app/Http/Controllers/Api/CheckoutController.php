<?php

namespace App\Http\Controllers\Api;

use App\Events\UserOrder;
use App\Exceptions\CheckoutException;
use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UserCheckoutRequest;
use App\Http\Resources\ShippingResource;
use App\Models\Order;
use App\Models\ShippingTypesAndPrice;
use App\Services\CheckOut\CheckoutServices;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Throwable;

class CheckoutController extends Controller
{
    protected $checkOutservice;

    public function __construct(CheckoutServices $checkOutservices)
    {
        $this->checkOutservice = $checkOutservices;
    }

    public function usercheckout(UserCheckoutRequest $request,  Order $order)

    {

        $user = $request->user();

        // handle requests
        try {

            $this->checkOutservice->checkShippingSelectOptions($request);

            $this->checkOutservice->checkUserAddressOptions($request);

            $shipping_price = $this->checkOutservice->calculateShippingPrice($request, $user);

            $cartItems = $this->checkOutservice->getCartItems($user);

            if ($cartItems->isEmpty()) {
                return ApiResponse::sendResponse(200, 'لا يمكن اتمام الطلب والسلة فارغة');
            }
        } catch (CheckoutException $e) {
            // A deliberate, user-facing error (bad shipping/address
            // choice, missing shipping setup, unavailable product):
            // its message is safe to show to the customer.
            return ApiResponse::sendResponse(400, $e->getMessage());
        } catch (\Exception $e) {
            Log::error('API user checkout pre-check failed', ['exception' => $e]);
            return ApiResponse::sendResponse(400, 'Order creation failed');
        }


        // handle database proccessing
        DB::beginTransaction();
        try {
            $this->checkOutservice->checkJoinNews($request, $user);

            $order = $this->checkOutservice->createOrder($request, $user, $shipping_price);


            $this->checkOutservice->createOrderItems($order, $user);

            $this->checkOutservice->isAddingNewAddress($order, $request, $user);

            $this->checkOutservice->disActiveProduct($cartItems);

            $this->checkOutservice->updateProductStatue($user);

            DB::commit();

            // Notify admins and customer asynchronously through the UserOrder event.
            event(new UserOrder($order));

            return $this->checkOutservice->checkPaymentMethod($request, $order);
        } catch (CheckoutException $e) {
            DB::rollBack();
            // A deliberate, user-facing error (e.g. a soft-deleted
            // product surfacing in createOrder's totals): its message
            // is safe to show, like in the pre-check above — not the
            // generic "Order creation failed".
            return ApiResponse::sendResponse(400, $e->getMessage());
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('API user checkout failed', ['exception' => $e]);
            return response()->json(['message' => 'Order creation failed'], 500);
        }
    }

    // public function total($user): float
    // {
    //     /* السعر بعد الخصم لو فيه خصم*/
    //     return Cart::with('product')->withoutGlobalScope('cookie_id')
    //         ->where('user_id', $user->id)->where('status', 0)->get()
    //         ->sum(function ($item) {
    //             if ($item->product->discount_price) {
    //                 return $item->quantity * ($item->discounted_price ?? $item->product->discount_price);
    //             } else {

    //                 return $item->quantity * ($item->discounted_price ?? $item->product->price);
    //             }
    //         });
    // }


    // public function totalBeforeDiscount($user): float
    // {
    //     return Cart::with('product')->withoutGlobalScope('cookie_id')
    //         ->where('user_id', $user->id)->where('status', 0)->get()->sum(function ($item) {
    //             return $item->quantity * $item->product->discount_price ?? $item->product->price;
    //         });
    // }



    public function shippingInfo(Request $request)
    {

        $shipping = ShippingTypesAndPrice::find(1);

        // No shipping row configured — a clean error, not a 500 on null.
        if (! $shipping) {
            return ApiResponse::sendResponse(422, 'طريقة الشحن غير مُعدّة في المتجر بعد.');
        }

        $data = [
            'shipping_info' => new ShippingResource($shipping),
        ];
        return ApiResponse::sendResponse(200, '', $data);
    }
}
