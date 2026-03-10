<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class GetRoomPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'startDate'        => 'required|date',
            'endDate'          => 'required_if:typeReservation,0|date|after_or_equal:startDate',
            'roomTypeId'       => 'required|numeric|exists:room_types,id',
            'typeReservation'  => 'required|in:0,1,2',
            'numberOfMonths'   => 'required_if:typeReservation,1|numeric'
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'code' => 422,
            'data' => null
        ], 422);
        throw new HttpResponseException($response);
    }
}

