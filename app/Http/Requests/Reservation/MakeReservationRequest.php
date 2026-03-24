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
            'rooms'                 => 'required|array|min:1',
'rooms.*.room_id'       => 'nullable|numeric|exists:rooms,id',
            'rooms.*.suite_id'      => 'nullable|numeric|exists:suites,id',
            // السعر يحسب تلقائياً من getRoomPrice
            'start_date'            => 'required|date|after_or_equal:today',
            'expire_date'           => 'required|date|after:start_date',
            // nights يحسب تلقائياً من start_date و expire_date

            'reservation_type'      => 'required|in:0,1',
            'reservation_status'    => 'nullable|in:0,1',
            'stay_reason_id'        => 'required|numeric|exists:stay_reasons,id',
            'reservation_source_id' => 'required|numeric|exists:reservation_sources,id',
            'rent_type'             => 'required|in:0,1',
            'price_calculation_mode' => 'required|in:0,1,2',
            // هذه الحقول يمكن للمستخدم إرسالها
            'discount'              => 'nullable|numeric|min:0',
            'extras'                => 'nullable|numeric|min:0',
            'penalties'             => 'nullable|numeric|min:0',
            'taxes'                 => 'nullable|numeric|min:0',
            'logedin'               => 'nullable|in:0,1',
            'login_time'            => 'nullable|date',
        ];
    }

public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach ($this->input('rooms', []) as $index => $roomData) {
                if (empty($roomData['room_id']) && empty($roomData['suite_id'])) {
                    $validator->errors()->add('rooms.' . $index, 'Either room_id or suite_id must be provided for each room.');
                }
            }
        });
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

