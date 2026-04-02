<?php

declare(strict_types=1);

namespace App\Http\Requests\WorldCharacterOptions;

use Illuminate\Validation\Rule;

class MoveWorldCharacterOptionRequest extends WorldCharacterOptionsRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'direction' => (string) $this->route('direction'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', 'string', Rule::in(['up', 'down'])],
        ];
    }

    public function direction(): string
    {
        return (string) $this->validated('direction');
    }
}
