<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'job_number'      => $this->job_number,
            'mobile'          => $this->mobile,
            'email'           => $this->email,
            'active'          => $this->active,

            // العلاقات
            'jobtitle_id'     => $this->jobtitle_id,
            'jobtitle_name'   => $this->jobtitle?->jobtitle,
            'department_id'   => $this->department_id,
            'department_name' => $this->department?->name,
            'discount_id'     => $this->discount_id,
            'discount_name'   => $this->discount?->name,

            // الصلاحيات
            'permissions'     => $this->permissions->pluck('permission'),

            // التواريخ
            'created_at'      => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'      => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
