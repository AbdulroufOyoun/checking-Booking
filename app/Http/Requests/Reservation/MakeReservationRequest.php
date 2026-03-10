<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class MakeReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id'             => 'required|numeric',
            'room_id'               => 'required|numeric|exists:rooms,id',
            'room_suite'            => 'required|in:0,1',
            'multi_room'            => 'required|in:0,1',
            'additional_rooms_ids'  => 'nullable|string',
            'start_date'            => 'required|date|after_or_equal:today',
            'nights'                => 'required_if:rent_type,0|integer|min:1',
            'expire_date'           => 'required|date|after_or_equal:start_date',
            'reservation_type'      => 'required|in:0,1',
            'reservation_status'    => 'nullable|in:0,1',
            'stay_reason_id'        => 'nullable|numeric|exists:stay_reasons,id',
            'reservation_source_id' => 'nullable|numeric|exists:reservation_sources,id',
            'rent_type'             => 'required|in:0,1',
            'base_price'            => 'required|numeric|min:0',
            'discount'              => 'nullable|numeric|min:0',
            'extras'                => 'nullable|numeric|min:0',
            'penalties'             => 'nullable|numeric|min:0',
            'subtotal'              => 'required|numeric|min:0',
            'taxes'                 => 'nullable|numeric|min:0',
            'total'                 => 'required|numeric|min:0',
            'logedin'               => 'nullable|in:0,1',
            'login_time'            => 'nullable|date',
            'user_id'               => 'required|numeric|exists:users,id',
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

