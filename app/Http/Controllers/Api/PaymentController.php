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
        $vnp_ResponseCode = $request->input('vnp_ResponseCode');
        $vnp_TxnRef = $request->input('vnp_TxnRef'); // Order ID

        $order = Order::with('payment')->find($vnp_TxnRef);

        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        $isSuccess = ($vnp_ResponseCode === '00');

        if ($isSuccess) {
            DB::transaction(function () use ($order) {
                $order->update(['status' => 'paid']);
                $order->payment()->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            });
        } else {
            $order->payment()->update(['status' => 'failed']);
        }

        // Chuyển hướng về Frontend nếu là yêu cầu từ Browser (GET)
        // Nếu là IPN (thường là Server-to-Server), trả về JSON
        if ($request->isMethod('GET')) {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            $status = $isSuccess ? 'success' : 'failed';
            return redirect()->to("{$frontendUrl}/profile?payment_status={$status}&order_id={$order->id}");
        }

        return response()->json([
            'RspCode' => $isSuccess ? '00' : '01',
            'Message' => $isSuccess ? 'Confirm Success' : 'Confirm Error'
        ]);
    }
}
