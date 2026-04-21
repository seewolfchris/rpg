<?php

namespace App\Http\Requests\CampaignGmContact;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignGmContactMessageRequest extends FormRequest
{
    protected $errorBag = 'gmContactMessage';

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
            'content' => ['required', 'string', 'min:1', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'content' => trim((string) $this->input('content', '')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'content' => 'Nachricht',
        ];
    }
}
