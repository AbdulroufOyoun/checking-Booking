<?php

namespace App\Http\Requests\ClientClassification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class AssignClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|numeric',
            'classifications_id' => 'required|numeric|exists:guest_classifications,id',
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
