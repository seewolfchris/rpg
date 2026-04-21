<?php

namespace App\Http\Requests\CampaignGmContact;

use App\Models\CampaignGmContactThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignGmContactThreadStatusRequest extends FormRequest
{
    protected $errorBag = 'gmContactStatus';

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
            'status' => ['required', Rule::in(CampaignGmContactThread::MANUAL_STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => trim(strtolower((string) $this->input('status', ''))),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'Status',
        ];
    }
}
