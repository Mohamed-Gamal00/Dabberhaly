<?php

namespace App\Http\Controllers\Api\guest;

use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;

class GuestCartController extends Controller
{
    public function guestIndex(Request $request)
    {
        $cookieId = $request->header('guest-id');

        if (!$cookieId) {
            return response()->json(['error' => 'not found'], 401);
        }

        $cartItems = Cart::with('product')->withoutGlobalScope('cookie_id')
            ->where('cookie_id', $cookieId)
            ->where('status', 0)->get();

        $totalCount = Cart::withoutGlobalScope('cookie_id')
            ->where('cookie_id', $cookieId)
            ->where('status', 0)
            ->count();

        $TotalPrice = $cartItems->sum(function ($item) {
            return $item->quantity * ($item->discounted_price ?? $item->product->price);
        });

        $totalQuantity = $cartItems->sum('quantity');

        $data = [
            'total_count'    => $totalCount,
            'total_price'    => $TotalPrice,
            'total_quantity' => $totalQuantity,
            'items'          => CartResource::collection($cartItems),
        ];

        return ApiResponse::sendResponse(200, '', $data);
    }

    public function guestStore(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        // The guest identity comes from the guest-id header, never from
        // the request body — a body cookie_id would let any guest write
        // into any other guest's cart.
        $cookieId = $request->header('guest-id');

        if (!$cookieId) {
            return response()->json(['error' => 'not found'], 401);
        }

        $product  = Product::findOrFail($request->product_id);
        $quantity = $request->quantity;

        $item = Cart::where('product_id', $product->id)
            ->withoutGlobalScope('cookie_id')
            ->where('cookie_id', $cookieId)
            ->where('status', 0)
            ->first();

        if (!$item) {
            $cart = Cart::create([
                'cookie_id'  => $cookieId,
                'product_id' => $product->id,
                'status'     => 0,
                'quantity'   => $quantity,
            ]);

            return response()->json([
                'message' => 'Product added to cart successfully.',
                'cart'    => $cart,
            ], 201);
        }

        if ($item->quantity + $quantity > $product->quantity) {
            return response()->json([
                'message' => 'Sorry, the requested quantity is not available.',
            ], 400);
        }

        $item->increment('quantity', $quantity);

        return response()->json([
            'message' => 'Cart item quantity updated successfully.',
            'cart'    => $item,
        ], 200);
    }

    public function guestDestroy(Request $request, $id)
    {
        Cart::where('id', $id)->withoutGlobalScope('cookie_id')
            ->where('cookie_id', $request->header('guest-id'))
            ->update(['status' => 1]);

        return ApiResponse::sendResponse(200, 'تم الحذف بنجاح', []);
    }

    public function guestUpdate(Request $request, $id)
    {
        $request->validate([
            'quantity' => ['required', 'int', 'min:1'],
        ]);

        Cart::where('id', $id)->withoutGlobalScope('cookie_id')
            ->where('cookie_id', $request->header('guest-id'))
            ->update(['quantity' => $request->post('quantity')]);

        return ApiResponse::sendResponse(200, 'تم التعديل بنجاح', []);
    }
}
