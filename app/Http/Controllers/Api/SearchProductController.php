<?php

namespace App\Http\Controllers\Api;

use App\Helper\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\FilterProductsResource;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchProductController extends Controller
{
    public function search(Request $request)
    {
        $word = $request->has('product_name') ? $request->input('product_name') : null;
        $products = Product::with('parent', 'availability')
            ->when($word != null, function ($query) use ($word) {
                $query->where(fn ($q) => $q->where('name', 'LIKE', '%' . $word . '%')
                    ->orWhere('name_en', 'LIKE', '%' . $word . '%'));
            })->latest()->paginate(10);


        if (count($products) > 0) {
            // Same envelope as AllProductsController: records + pagination links.
            if ($products->total() > $products->perPage()) {
                $data = [
                    'records' => FilterProductsResource::collection($products),
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
                $data = FilterProductsResource::collection($products);
            }
            return ApiResponse::sendResponse(200, 'Search Completed', $data);
        }
        return ApiResponse::sendResponse(200, 'No Matching Data', []);
    }

}
