<?php

namespace App\Http\Resources;

use App\currency\Currency;
use App\Helper\Helper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FilterProductsResource extends JsonResource
{
    use Helper;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Same currency derivation as ViewProductsResource, so the
        // mobile payload stays consistent across list endpoints.
        $priceAfter = strval(Currency::getCurrencyApi($this->discount_price));

        return [
            'product_id' => $this->id,
            'product_name' => $this->name,
            'category' => [
                'id' => $this->parent?->id,
                'name' => $this->parent?->name,
            ],
            'current_price' => $this->price,
            'price_before_discount' => $this->discount_price,
            'currency' => preg_replace('/[0-9.]+/', '', $priceAfter),
            'image' => $this->image_url,
            'product_status' => $this->availability?->name,
            'is_favourite' => $this->checkIfProductExists($request, $this->id),
        ];
    }
}
