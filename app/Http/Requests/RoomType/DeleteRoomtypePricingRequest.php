<?php

namespace App\Http\Requests\RoomType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class DeleteRoomtypePricingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => 'required|exists:roomtype_pricingplan,id',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'result' => 'failed',
            'error' => $validator->errors()->first(),
        ], 200);
        throw new HttpResponseException($response);
    }
}
