<?php

namespace App\Http\Requests\ClientNote;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|exists:client_notes,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ];
    }
}
