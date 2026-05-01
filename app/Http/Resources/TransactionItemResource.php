<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionItemResource extends JsonResource
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
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', function () {
                return new ProductResource($this->product);
            }),
            'price' => (float)(string) $this->price,
            'quantity' => $this->quantity,
            'subtotal' => (float)(string) $this->subtotal,
        ];
    }
}
