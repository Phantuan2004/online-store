<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributes = [];
        if ($this->relationLoaded('attributeValues')) {
            foreach ($this->attributeValues as $val) {
                if ($val->relationLoaded('attribute')) {
                    $attributes[$val->attribute->name] = $val->value;
                }
            }
        }

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price' => $this->price,
            'stock' => $this->stock,
            'image' => $this->whenLoaded('image', function() {
                return $this->image ? $this->image->url : null;
            }),
            'attributes' => empty($attributes) ? null : (object) $attributes,
        ];
    }
}
