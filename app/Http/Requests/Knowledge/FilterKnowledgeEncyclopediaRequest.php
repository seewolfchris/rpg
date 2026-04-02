<?php

declare(strict_types=1);

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

class FilterKnowledgeEncyclopediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:160'],
            'k' => ['nullable', 'string', 'alpha_dash', 'max:120'],
        ];
    }
}
