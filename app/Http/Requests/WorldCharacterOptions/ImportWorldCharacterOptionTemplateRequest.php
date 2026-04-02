<?php

namespace App\Http\Requests\WorldCharacterOptions;

use App\Support\WorldCharacterOptionTemplateService;
use Illuminate\Validation\Rule;

class ImportWorldCharacterOptionTemplateRequest extends WorldCharacterOptionsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $templateOptions = array_keys(
            app(WorldCharacterOptionTemplateService::class)->templateSelectOptions()
        );

        return [
            'template_key' => ['required', 'string', Rule::in($templateOptions)],
        ];
    }
}
