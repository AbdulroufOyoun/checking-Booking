<?php

namespace App\Http\Resources\RefundPolicy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'days_before_checkin' => $this->days_before_checkin,
            'refund_percent' => $this->refund_percent,
            'payment_status' => $this->payment_status,
            'during_stay' => $this->during_stay,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

