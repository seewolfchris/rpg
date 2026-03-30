<?php

declare(strict_types=1);

namespace App\Actions\Character;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateCharacterInlineAction
{
    public function execute(Request $request, Character $character): UpdateCharacterInlineResult
    {
        $statusOptions = array_keys((array) config('characters.statuses', []));

        $validated = $request->validate([
            'epithet' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in($statusOptions)],
            'bio' => ['required', 'string', 'max:12000'],
            'concept' => ['nullable', 'string', 'max:180'],
            'world_connection' => ['nullable', 'string', 'max:2000'],
            'gm_secret' => ['nullable', 'string', 'max:3000'],
            'gm_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $character->fill($validated);
        $character->save();

        return new UpdateCharacterInlineResult(
            character: $character,
            shouldRenderFragment: $request->header('HX-Request') === 'true',
        );
    }
}
