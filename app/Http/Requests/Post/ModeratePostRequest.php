<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModeratePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'moderation_status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'moderation_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
