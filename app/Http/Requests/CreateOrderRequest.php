<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'in:cod,vnpay,momo,credit_card'],
            // Add address rules here if needed
            'address_id' => ['nullable', 'exists:addresses,id']
        ];
    }
}
