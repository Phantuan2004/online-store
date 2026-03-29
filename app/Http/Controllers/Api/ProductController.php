<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['variants.attributeValues.attribute', 'category'])->paginate(15);
        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        $product->load(['variants.attributeValues.attribute', 'category']);
        return new ProductResource($product);
    }

    public function store(StoreProductRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $product = Product::create($request->only(['name', 'description', 'price', 'category_id']));

            foreach ($request->validated('variants') as $variantData) {
                $variant = $product->variants()->create([
                    'sku' => $variantData['sku'],
                    'price' => $variantData['price'],
                    'stock' => $variantData['stock'],
                ]);

                if (!empty($variantData['attribute_value_ids'])) {
                    $variant->attributeValues()->attach($variantData['attribute_value_ids']);
                }
            }

            $product->load(['variants.attributeValues.attribute', 'category']);
            return new ProductResource($product);
        });
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        return DB::transaction(function () use ($request, $product) {
            $product->update($request->only(['name', 'description', 'price', 'category_id']));

            if ($request->has('variants')) {
                // For simplicity, we assume updating variants completely replaces them or updates existing ones
                // A robust implementation would differentiate between create/update/delete based on variant array logic
                foreach ($request->validated('variants') as $variantData) {
                    if (isset($variantData['id'])) {
                        $variant = $product->variants()->findOrFail($variantData['id']);
                        $variant->update([
                            'sku' => $variantData['sku'],
                            'price' => $variantData['price'],
                            'stock' => $variantData['stock'],
                        ]);
                    } else {
                        $variant = $product->variants()->create([
                            'sku' => $variantData['sku'],
                            'price' => $variantData['price'],
                            'stock' => $variantData['stock'],
                        ]);
                    }

                    if (isset($variantData['attribute_value_ids'])) {
                        $variant->attributeValues()->sync($variantData['attribute_value_ids']);
                    }
                }
            }

            $product->load(['variants.attributeValues.attribute', 'category']);
            return new ProductResource($product);
        });
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
