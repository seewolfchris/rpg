<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Character;

use App\Actions\Character\UpdateCharacterInlineAction;
use App\Data\Character\InlineUpdateCharacterInput;
use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCharacterInlineActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_and_persists_inline_payload_and_marks_htmx_fragment_boundary(): void
    {
        $user = User::factory()->create();
        $character = Character::factory()->create([
            'user_id' => $user->id,
            'name' => 'Inline Held',
            'status' => 'active',
            'bio' => 'Alter Bio-Text',
        ]);

        $validated = [
            'epithet' => 'der Wache',
            'status' => 'downtime',
            'bio' => 'Neuer Bio-Abschnitt',
            'concept' => 'Konzept neu',
            'world_connection' => 'Kontakte zur Aschewacht',
            'gm_secret' => 'Geheimnis der Blutlinie',
            'gm_note' => 'Nur fuer Spielleitung',
        ];

        $result = app(UpdateCharacterInlineAction::class)->execute(
            new InlineUpdateCharacterInput(
                character: $character,
                payload: $validated,
                isHtmxRequest: true,
            )
        );

        $this->assertTrue($result->shouldRenderFragment);
        $this->assertSame((int) $character->id, (int) $result->character->id);
        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'epithet' => 'der Wache',
            'status' => 'downtime',
            'bio' => 'Neuer Bio-Abschnitt',
            'concept' => 'Konzept neu',
        ]);
    }

    public function test_it_marks_non_htmx_requests_for_redirect_boundary(): void
    {
        $character = Character::factory()->create([
            'status' => 'active',
            'bio' => 'Vorher',
        ]);

        $validated = [
            'epithet' => null,
            'status' => 'active',
            'bio' => 'Nachher',
            'concept' => null,
            'world_connection' => null,
            'gm_secret' => null,
            'gm_note' => null,
        ];

        $result = app(UpdateCharacterInlineAction::class)->execute(
            new InlineUpdateCharacterInput(
                character: $character,
                payload: $validated,
                isHtmxRequest: false,
            )
        );

        $this->assertFalse($result->shouldRenderFragment);
        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'bio' => 'Nachher',
        ]);
    }

    public function test_it_exposes_inline_update_validation_rules(): void
    {
        $rules = app(UpdateCharacterInlineAction::class)->rules();

        $this->assertArrayHasKey('status', $rules);
        $this->assertArrayHasKey('bio', $rules);
        $this->assertArrayHasKey('gm_note', $rules);
    }
}
