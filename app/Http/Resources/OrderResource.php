<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'shipping-price' => $this->shipping_price ?? "0",
            'total_price' => $this->total_price,
            // Money breakdown: the discounted items subtotal, the
            // subtotal before any coupon, the discount amount and
            // the VAT charged — the detail used to show only shipping
            // and the total, so the tax line was invisible.
            'subtotal' => round((float) $this->total_price - (float) ($this->shipping_price ?? 0) - (float) ($this->tax_amount ?? 0), 2),
            'total_before_discount' => (float) ($this->totalBeforeDiscount ?? 0),
            'discount_amount' => round(max(0, (float) ($this->totalBeforeDiscount ?? 0) - ((float) $this->total_price - (float) ($this->shipping_price ?? 0) - (float) ($this->tax_amount ?? 0))), 2),
            'tax_amount' => (float) ($this->tax_amount ?? 0),
            'products' => $this->getProducts(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->first_name,
                'email' => $this->user->email,
                'address' => $this->user->address,
                // Add other user fields as needed
            ],
            'status' => $this->status->value,
            'order-status' => $this->status->label(),
        ];


    }

    private function getProducts()
    {
        return $this->orderItems->map(function ($orderItem) {
            return [
                'product' => new ProductsResource($orderItem->product),
                'quantity' => $orderItem->quantity,
            ];
        });
    }
}
