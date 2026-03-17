<?php

namespace App\Http\Controllers;

use App\Domain\Character\CharacterProgressionService;
use App\Http\Requests\Character\SpendCharacterAttributePointsRequest;
use App\Models\Character;
use Illuminate\Http\RedirectResponse;

class CharacterProgressionController extends Controller
{
    public function __construct(
        private readonly CharacterProgressionService $progressionService,
    ) {}

    public function spend(SpendCharacterAttributePointsRequest $request, Character $character): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $character->user_id === $user->id || $user->isGmOrAdmin(),
            403
        );

        $result = $this->progressionService->spendAttributePoints(
            character: $character,
            actor: $user,
            attributeAllocations: (array) $request->validated('attribute_allocations', []),
            note: (string) $request->validated('note', ''),
        );

        return redirect()
            ->route('characters.show', $character)
            ->with('status', 'Attributpunkte verteilt: '.$result['spent_points'].'.');
    }
}
