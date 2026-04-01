<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Data\Character\InlineUpdateCharacterInput;
use Illuminate\Validation\Rule;

class UpdateCharacterInlineAction
{
    /**
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        $statusOptions = array_keys((array) config('characters.statuses', []));

        return [
            'epithet' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in($statusOptions)],
            'bio' => ['required', 'string', 'max:12000'],
            'concept' => ['nullable', 'string', 'max:180'],
            'world_connection' => ['nullable', 'string', 'max:2000'],
            'gm_secret' => ['nullable', 'string', 'max:3000'],
            'gm_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function execute(InlineUpdateCharacterInput $input): UpdateCharacterInlineResult
    {
        $input->character->fill($input->payload);
        $input->character->save();

        return new UpdateCharacterInlineResult(
            character: $input->character,
            shouldRenderFragment: $input->isHtmxRequest,
        );
    }
}
