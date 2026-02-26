<?php

namespace App\Http\Requests\RoomType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class AddRoomtypePricingRequest extends FormRequest
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
            'NameAr' => 'required|string|max:255',
            'NameEn' => 'required|string|max:255',
            'StartDate' => 'required|date',
            'EndDate' => 'required|date|after_or_equal:StartDate',
            'roomtype_id' => 'required|exists:room_types,id',
            'DailyPrice' => 'required|numeric|min:0',
            'MonthlyPrice' => 'required|numeric|min:0',
            'YearlyPrice' => 'required|numeric|min:0',
            'ActiveType' => 'required|numeric|in:0,1,2',
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
