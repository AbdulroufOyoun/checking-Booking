<?php

namespace App\Http\Requests\RefundPolicy;

use Illuminate\Foundation\Http\FormRequest;

class StoreRefundPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'days_before_checkin' => 'required|integer|min:0',
            'refund_percent' => 'required|numeric|min:0|max:100',
            'payment_status' => 'required|in:0,1,2',
            'during_stay' => 'required|in:0,1',
        ];
    }
}

