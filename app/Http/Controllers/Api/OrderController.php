<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = $request->user()->orders()
            ->with(['items.variant.product', 'payment'])
            ->latest()
            ->paginate(10);
            
        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $order->load(['items.variant.product', 'payment']);
        return new OrderResource($order);
    }

    public function store(CreateOrderRequest $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->with('items.variant')->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        return DB::transaction(function () use ($request, $cart) {
            $totalPrice = $cart->items->sum(function ($item) {
                return $item->variant->price * $item->quantity;
            });

            $order = Order::create([
                'user_id' => $request->user()->id,
                'total_price' => $totalPrice,
                'status' => 'pending',
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                    'price' => $item->variant->price,
                ]);

                // Decrease stock
                $item->variant->decrement('stock', $item->quantity);
            }

            // Optional: attach address if provided via address_id
            if ($request->has('address_id')) {
                $order->addresses()->attach($request->validated('address_id'));
            }

            // Create pending payment record
            $order->payment()->create([
                'method' => $request->validated('payment_method'),
                'status' => 'pending',
            ]);

            // Clear cart
            $cart->items()->delete();

            $order->load(['items.variant.product', 'payment']);
            return new OrderResource($order);
        });
    }
}
