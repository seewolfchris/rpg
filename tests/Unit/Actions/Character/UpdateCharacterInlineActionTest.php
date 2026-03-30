<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Character;

use App\Actions\Character\UpdateCharacterInlineAction;
use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        $request = new InlineUpdateRequestFake(
            validatedPayload: [
                'epithet' => 'der Wache',
                'status' => 'downtime',
                'bio' => 'Neuer Bio-Abschnitt',
                'concept' => 'Konzept neu',
                'world_connection' => 'Kontakte zur Aschewacht',
                'gm_secret' => 'Geheimnis der Blutlinie',
                'gm_note' => 'Nur fuer Spielleitung',
            ],
            htmxRequest: true,
            assertRules: function (array $rules): void {
                $this->assertArrayHasKey('status', $rules);
                $this->assertArrayHasKey('bio', $rules);
                $this->assertArrayHasKey('gm_note', $rules);
            },
        );

        $result = app(UpdateCharacterInlineAction::class)->execute($request, $character);

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

        $request = new InlineUpdateRequestFake(
            validatedPayload: [
                'epithet' => null,
                'status' => 'active',
                'bio' => 'Nachher',
                'concept' => null,
                'world_connection' => null,
                'gm_secret' => null,
                'gm_note' => null,
            ],
            htmxRequest: false,
        );

        $result = app(UpdateCharacterInlineAction::class)->execute($request, $character);

        $this->assertFalse($result->shouldRenderFragment);
        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'bio' => 'Nachher',
        ]);
    }

    public function test_it_propagates_validation_exception_without_persisting_changes(): void
    {
        $character = Character::factory()->create([
            'status' => 'active',
            'bio' => 'Unveraendert',
        ]);

        $request = new InlineUpdateRequestFake(
            validatedPayload: [],
            htmxRequest: true,
            throwValidationException: true,
        );

        $this->expectException(ValidationException::class);

        try {
            app(UpdateCharacterInlineAction::class)->execute($request, $character);
        } finally {
            $this->assertDatabaseHas('characters', [
                'id' => $character->id,
                'bio' => 'Unveraendert',
            ]);
        }
    }
}

final class InlineUpdateRequestFake extends Request
{
    /**
     * @param  array<string, mixed>  $validatedPayload
     * @param  \Closure(array<string, mixed>):void|null  $assertRules
     */
    public function __construct(
        private readonly array $validatedPayload,
        private readonly bool $htmxRequest,
        private readonly ?\Closure $assertRules = null,
        private readonly bool $throwValidationException = false,
    ) {}

    /**
     * @param  array<string, mixed>  $rules
     * @param  mixed  ...$params
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $rules, ...$params): array
    {
        if ($this->assertRules instanceof \Closure) {
            ($this->assertRules)($rules);
        }

        if ($this->throwValidationException) {
            throw ValidationException::withMessages([
                'status' => ['Status ist ungueltig.'],
            ]);
        }

        return $this->validatedPayload;
    }

    public function header($key = null, $default = null): mixed
    {
        if ((string) $key === 'HX-Request') {
            return $this->htmxRequest ? 'true' : 'false';
        }

        return $default;
    }
}
