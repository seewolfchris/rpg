<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dice\StoreDiceRollRequest;
use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\Scene;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class DiceRollController extends Controller
{
    public function store(StoreDiceRollRequest $request, Campaign $campaign, Scene $scene): JsonResponse|RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('view', $scene);
        $this->authorize('create', [Post::class, $scene]);

        $data = $request->validated();

        $rollMode = (string) $data['dice_roll_mode'];
        $modifier = (int) ($data['dice_modifier'] ?? 0);
        $rolls = $this->generateRolls($rollMode);
        $keptRoll = $this->resolveKeptRoll($rollMode, $rolls);
        $total = $keptRoll + $modifier;

        $diceRoll = DiceRoll::query()->create([
            'scene_id' => $scene->id,
            'user_id' => auth()->id(),
            'character_id' => $data['dice_character_id'] ?? null,
            'roll_mode' => $rollMode,
            'modifier' => $modifier,
            'label' => isset($data['dice_label']) ? trim((string) $data['dice_label']) ?: null : null,
            'rolls' => $rolls,
            'kept_roll' => $keptRoll,
            'total' => $total,
            'is_critical_success' => $keptRoll === 20,
            'is_critical_failure' => $keptRoll === 1,
        ]);

        if ($request->expectsJson()) {
            $diceRoll->load(['user', 'character']);

            return response()->json([
                'message' => 'Wurf gespeichert.',
                'html' => view('dice-rolls._item', [
                    'roll' => $diceRoll,
                ])->render(),
                'roll' => [
                    'id' => $diceRoll->id,
                    'mode' => $diceRoll->roll_mode,
                    'rolls' => $diceRoll->rolls,
                    'kept_roll' => $diceRoll->kept_roll,
                    'modifier' => $diceRoll->modifier,
                    'total' => $diceRoll->total,
                    'critical_success' => $diceRoll->is_critical_success,
                    'critical_failure' => $diceRoll->is_critical_failure,
                ],
            ], 201);
        }

        return redirect()
            ->to(route('campaigns.scenes.show', [$campaign, $scene]).'#dice-log')
            ->with('status', 'Wurf gespeichert: d20 '.$this->modeLabel($rollMode).' => '.$keptRoll.' '.($modifier >= 0 ? '+' : '').$modifier.' = '.$total);
    }

    private function ensureSceneBelongsToCampaign(Campaign $campaign, Scene $scene): void
    {
        abort_unless($scene->campaign_id === $campaign->id, 404);
    }

    /**
     * @return array<int, int>
     */
    private function generateRolls(string $mode): array
    {
        $rolls = [$this->rollD20()];

        if (in_array($mode, [DiceRoll::MODE_ADVANTAGE, DiceRoll::MODE_DISADVANTAGE], true)) {
            $rolls[] = $this->rollD20();
        }

        return $rolls;
    }

    /**
     * @param  array<int, int>  $rolls
     */
    private function resolveKeptRoll(string $mode, array $rolls): int
    {
        return match ($mode) {
            DiceRoll::MODE_ADVANTAGE => max($rolls),
            DiceRoll::MODE_DISADVANTAGE => min($rolls),
            default => $rolls[0],
        };
    }

    private function rollD20(): int
    {
        return random_int(1, 20);
    }

    private function modeLabel(string $mode): string
    {
        return match ($mode) {
            DiceRoll::MODE_ADVANTAGE => '(Vorteil)',
            DiceRoll::MODE_DISADVANTAGE => '(Nachteil)',
            default => '(Normal)',
        };
    }
}
