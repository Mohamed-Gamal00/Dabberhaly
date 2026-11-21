<?php

namespace App\Http\Controllers\Api;

use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ViewProductsResource;
use App\Models\Guest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GetAllFavProductsController extends Controller
{
    public function userFavProducts(Request $request)
    {
        $user = $request->user();


        $allFavProducts = DB::table('wishlist_products_user')->where('user_id', $user->id)->pluck('product_id')->toArray();
        $products = Product::with('parent', 'availability')->whereIn('id', $allFavProducts)->paginate(10);

        if (count($products) > 0) {
            if ($products->total() > $products->perPage()) {
                $data = [
                    'records' => ViewProductsResource::collection($products),
                    'pagination links' => [
                        'current page' => $products->currentPage(),
                        'per page' => $products->perPage(),
                        'total' => $products->total(),
                        'links' => [
                            'first' => $products->url(1),
                            'last' => $products->url($products->lastPage())
                        ],
                    ],
                ];
            } else {
                $data = ViewProductsResource::collection($products);
            }
            return ApiResponse::sendResponse(200, 'Products Retrieved Successfully', $data);
        }
        return ApiResponse::sendResponse(200, 'No Favourite Products To Retrieved', []);
    }

    public function guestFavProducts(Request $request)
    {
        $guestId = $request->header('guest-id');

        // The header carries the guest's cookie_id (the mobile guest
        // identity) — never the numeric guest id — so resolve the
        // Guest row by cookie_id first; the wishlist table holds the
        // numeric guest id as its foreign key.
        $guest = Guest::where('cookie_id', $guestId)->first();

        $allFavProducts = $guest
            ? DB::table('wishlist_products_guest')->where('guest_id', $guest->id)->pluck('product_id')->toArray()
            : [];
        $products = Product::with('parent', 'availability')->whereIn('id', $allFavProducts)->paginate(10);

        if (count($products) > 0) {
            if ($products->total() > $products->perPage()) {
                $data = [
                    'records' => ViewProductsResource::collection($products),
                    'pagination links' => [
                        'current page' => $products->currentPage(),
                        'per page' => $products->perPage(),
                        'total' => $products->total(),
                        'links' => [
                            'first' => $products->url(1),
                            'last' => $products->url($products->lastPage())
                        ],
                    ],
                ];
            } else {
                $data = ViewProductsResource::collection($products);
            }
            return ApiResponse::sendResponse(200, 'Products Retrieved Successfully', $data);
        }
        return ApiResponse::sendResponse(200, 'No Favourite Products To Retrieved', []);
    }
}
