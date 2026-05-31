<?php

namespace App\Http\Requests\ClientNote;

use Illuminate\Foundation\Http\FormRequest;

class GetNotesByClientIdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|exists:clients,id',
        ];
    }
}
