<?php

namespace App\Http\Requests\RefundPolicy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRefundPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'days_before_checkin' => 'sometimes|integer|min:0',
            'refund_percent' => 'sometimes|numeric|min:0|max:100',
            'payment_status' => 'sometimes|in:0,1,2',
            'during_stay' => 'sometimes|in:0,1',
        ];
    }
}

