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
            'payment_method' => ['required', 'string', 'in:cod,vnpay'],
            'address_id' => [
                'required', 
                'exists:addresses,id,user_id,' . $this->user()->id
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
