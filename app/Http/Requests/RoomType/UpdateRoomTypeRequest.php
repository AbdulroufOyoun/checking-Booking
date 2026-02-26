<?php

namespace App\Http\Requests\RoomType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class UpdateRoomTypeRequest extends FormRequest
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
        $rules = [
            'id'          => 'required|numeric|exists:room_types,id',
            'name_ar'     => 'required|string|max:255',
            'name_en'     => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'active_type' => 'required|numeric|in:0,1,2',
        ];

        if ($this->active_type == 0) {
            $rules['Min_daily_price'] = 'required|numeric|min:1';
            $rules['Min_monthly_price'] = 'required|numeric|min:1';
            $rules['Min_yearly_price'] = 'required|numeric|min:1';
        } else {
            $rules['Min_daily_price'] = 'required|numeric|min:1';
            $rules['Max_daily_price'] = 'required|numeric|min:1';
            $rules['Min_monthly_price'] = 'required|numeric|min:1';
            $rules['Max_monthly_price'] = 'required|numeric|min:1';
            $rules['Min_yearly_price'] = 'required|numeric|min:1';
            $rules['Max_yearly_price'] = 'required|numeric|min:1';
        }

        return $rules;
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
