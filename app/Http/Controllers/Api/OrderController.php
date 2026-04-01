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

    public function store(CreateOrderRequest $request, \App\Services\VNPayService $vnpayService)
    {
        $cart = Cart::where('user_id', $request->user()->id)->with('items.variant.product')->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Giỏ hàng của bạn đang trống.'], 400);
        }

        try {
            return DB::transaction(function () use ($request, $cart, $vnpayService) {
                // 1. Tính tổng tiền
                $totalPrice = $cart->items->sum(function ($item) {
                    return $item->variant->price * $item->quantity;
                });

                // 2. Tạo Đơn hàng (Trạng thái mặc định: pending)
                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'total_price' => $totalPrice,
                    'status' => 'pending',
                ]);

                // 3. Chuyển Cart Items sang Order Items & Cập nhật tồn kho
                foreach ($cart->items as $item) {
                    // Kiểm tra tồn kho trước khi trừ
                    if ($item->quantity > $item->variant->stock) {
                        throw new \Exception("Sản phẩm '" . $item->variant->product->name . "' hiện không đủ số lượng tồn kho.");
                    }

                    $order->items()->create([
                        'product_variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                        'price' => $item->variant->price, // Snapshot giá hiện tại
                    ]);

                    // Trừ số lượng trong kho
                    $item->variant->decrement('stock', $item->quantity);
                }

                // 4. Lưu địa chỉ giao hàng
                $order->addresses()->attach($request->validated('address_id'));

                // 5. Khởi tạo bản ghi Payment
                $paymentMethod = $request->validated('payment_method');
                $order->payment()->create([
                    'method' => $paymentMethod,
                    'status' => 'pending',
                ]);

                // 6. Xóa giỏ hàng sau khi đặt hàng thành công
                $cart->items()->delete();

                $order->load(['items.variant.product', 'payment']);
                $resource = new OrderResource($order);

                // 7. Xử lý logic VNPay: Sinh URL thanh toán nếu chọn vnpay
                if ($paymentMethod === 'vnpay') {
                    $paymentUrl = $vnpayService->createPaymentUrl($order);
                    return $resource->additional([
                        'message' => 'Đơn hàng đã được khởi tạo, vui lòng thanh toán qua VNPay.',
                        'payment_url' => $paymentUrl
                    ]);
                }

                return $resource->additional(['message' => 'Đặt hàng thành công (COD).']);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xử lý đơn hàng.',
                'errors' => ['checkout' => [$e->getMessage()]]
            ], 422);
        }
    }
}
