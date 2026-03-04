<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dice\StoreDiceRollRequest;
use App\Models\Campaign;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\Scene;
use App\Support\ProbeRoller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class DiceRollController extends Controller
{
    public function __construct(
        private readonly ProbeRoller $probeRoller,
    ) {}

    public function store(StoreDiceRollRequest $request, Campaign $campaign, Scene $scene): JsonResponse|RedirectResponse
    {
        $this->ensureSceneBelongsToCampaign($campaign, $scene);
        $this->authorize('view', $scene);
        $this->authorize('create', [Post::class, $scene]);

        $data = $request->validated();

        $rollMode = (string) $data['dice_roll_mode'];
        $modifier = (int) ($data['dice_modifier'] ?? 0);
        $rolled = $this->probeRoller->roll($rollMode, $modifier);

        $diceRoll = DiceRoll::query()->create([
            'scene_id' => $scene->id,
            'post_id' => null,
            'user_id' => auth()->id(),
            'character_id' => $data['dice_character_id'] ?? null,
            'roll_mode' => $rolled['mode'],
            'modifier' => $rolled['modifier'],
            'label' => isset($data['dice_label']) ? trim((string) $data['dice_label']) ?: null : null,
            'rolls' => $rolled['rolls'],
            'kept_roll' => $rolled['kept_roll'],
            'total' => $rolled['total'],
            'is_critical_success' => $rolled['critical_success'],
            'is_critical_failure' => $rolled['critical_failure'],
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
            ->to(route('campaigns.scenes.show', [$campaign, $scene]).'#new-post-form')
            ->with('status', 'Probe gespeichert: '.$this->modeLabel((string) $rolled['mode']).' => '.$rolled['kept_roll'].' '.($rolled['modifier'] >= 0 ? '+' : '').$rolled['modifier'].' = '.$rolled['total']);
    }

    private function ensureSceneBelongsToCampaign(Campaign $campaign, Scene $scene): void
    {
        abort_unless($scene->campaign_id === $campaign->id, 404);
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
