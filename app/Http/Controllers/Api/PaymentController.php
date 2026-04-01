<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function createPayment(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($order->status !== 'pending' || $order->payment->status === 'paid') {
            return response()->json(['message' => 'Order is not pending or already paid'], 400);
        }

        // Logic to generate payment gateway URL (e.g. VNPAY, Momo) goes here.
        // For demonstration, we simply return a mock URL.
        
        $paymentUrl = 'https://mock-payment-gateway.com/pay/' . $order->id;

        return response()->json(['payment_url' => $paymentUrl]);
    }

    public function handleCallback(Request $request)
    {
        // VNPay trả về các tham số qua Query String (GET) hoặc POST tùy cấu hình
        // Ở đây chúng ta lấy vnp_ResponseCode để kiểm tra trạng thái
        $vnp_ResponseCode = $request->input('vnp_ResponseCode');
        $vnp_TxnRef = $request->input('vnp_TxnRef'); // Chính là Order ID chúng ta đã gửi đi

        $order = Order::with('payment')->find($vnp_TxnRef);

        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        if ($vnp_ResponseCode === '00') {
            DB::transaction(function () use ($order) {
                $order->update(['status' => 'paid']);
                $order->payment()->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            });

            return response()->json([
                'message' => 'Thanh toán qua VNPay thành công.',
                'order_id' => $order->id
            ]);
        }

        // Các mã lỗi khác của VNPay (ví dụ: 24 là khách hàng hủy giao dịch)
        $order->payment()->update(['status' => 'failed']);
        
        return response()->json([
            'message' => 'Giao dịch không thành công hoặc đã bị hủy.',
            'vnp_ResponseCode' => $vnp_ResponseCode
        ], 400);
    }
}
