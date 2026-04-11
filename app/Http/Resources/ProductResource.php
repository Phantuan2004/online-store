<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attributesMap = collect();

        if ($this->relationLoaded('variants')) {
            foreach ($this->variants as $variant) {
                if ($variant->relationLoaded('attributeValues')) {
                    foreach ($variant->attributeValues as $attrValue) {
                        if ($attrValue->relationLoaded('attribute')) {
                            $attrName = $attrValue->attribute->name;
                            if (!$attributesMap->has($attrName)) {
                                $attributesMap->put($attrName, collect());
                            }
                            $attributesMap[$attrName]->push($attrValue->value);
                        }
                    }
                }
            }
        }

        $formattedAttributes = $attributesMap->map(function ($values, $name) {
            return [
                'name' => $name,
                'values' => $values->unique()->values()->all()
            ];
        })->values()->all();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', function() {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),
            'primary_image' => $this->whenLoaded('image', function() {
                return $this->image ? $this->image->url : null;
            }),
            'images' => $this->whenLoaded('images', function() {
                return $this->images->pluck('url');
            }),
            'attributes' => empty($formattedAttributes) ? null : $formattedAttributes,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
