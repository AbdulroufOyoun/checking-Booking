<?php

namespace App\Http\Resources\Login;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id"=> $this->id,
             'job_number'=>$this->job_number,
        'jobtitle_id'=>$this->jobtitle_id,
        'department_id'=>$this->department_id,
        'discount_id'=>$this->discount_id,
        'mobile'=>$this->mobile,
        'email'=>$this->email,
        'permission'=>$this->permission,
        'token'=>$this->token->accessToken,
        'token_expire_at'=>Carbon::parse($this->token->token->expires_at)->format('Y-m-d H:i:s'),
        ];
    }
}
