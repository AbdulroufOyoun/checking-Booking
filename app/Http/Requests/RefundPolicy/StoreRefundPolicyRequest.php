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
            'rent_type' => 'nullable|integer|in:0,1,2',
            'timing' => 'required|in:before_start,after_start',
            'days_threshold' => 'required|integer|min:0',
            'refund_percent' => 'required|numeric|min:0|max:100',
            'refund_basis' => 'required|in:total,remaining_nights,paid_net',
            'payment_status' => 'nullable|integer|in:0,1,2',
            'days_before_checkin' => 'nullable|integer|min:0',
            'during_stay' => 'nullable|in:0,1',
        ];
    }

    protected function prepareForValidation(): void
    {
        $timing = $this->input('timing', 'before_start');
        $this->merge([
            'days_before_checkin' => $this->input('days_threshold', $this->input('days_before_checkin', 0)),
            'during_stay' => $timing === 'after_start' ? 1 : 0,
        ]);
    }
}
