<?php

namespace Tests\Feature;

use App\Domain\Character\CharacterProgressionService;
use App\Models\Character;
use App\Models\CharacterInventoryLog;
use App\Models\CharacterProgressionEvent;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use App\Models\DiceRoll;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\PostRevision;
use App\Models\Scene;
use App\Models\User;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CharacterManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function characterPayload(array $overrides = []): array
    {
        $payload = [
            'name' => 'Aldric',
            'epithet' => 'der Graupriester',
            'bio' => str_repeat('Dunkle Geschichte. ', 4),
            'origin' => 'native_vhaltor',
            'species' => 'mensch',
            'calling' => 'abenteurer',
            'concept' => 'Ich jage Wahrheiten durch Asche und Nebel.',
            'gm_secret' => 'Ich schulde der Schattenbank von Nerez einen Eid.',
            'world_connection' => 'Meine Schwester dient den Glutrichtern als Schreiberin.',
            'advantages' => ['Blutpforten-Sinn'],
            'disadvantages' => ['Aschesucht'],
            'inventory' => [[
                'name' => 'Seil 10m lang',
                'quantity' => 1,
                'equipped' => false,
            ], [
                'name' => 'Feuerstein',
                'quantity' => 3,
                'equipped' => false,
            ]],
            'weapons' => [[
                'name' => 'Kurzschwert',
                'attack' => 48,
                'parry' => 41,
                'damage' => 12,
            ]],
            'armors' => [[
                'name' => 'Lederruestung',
                'protection' => 5,
                'equipped' => true,
            ]],
            'gm_note' => 'Vorteil/Nachteil fuer Kampagne freigegeben.',
            'mu' => 40,
            'kl' => 45,
            'in' => 40,
            'ch' => 35,
            'ff' => 40,
            'ge' => 40,
            'ko' => 45,
            'kk' => 40,
            'mu_note' => 'Haelt auch in Finsternis den Blick gerade.',
            'kl_note' => 'Liest Archive schneller als andere Gesichter.',
            'in_note' => 'Vertraut dem Druecken der Stille.',
            'ch_note' => 'Wirkt warm, bleibt aber unnahbar.',
            'ff_note' => 'Feine Hand bei Siegeln und Schlossnadeln.',
            'ge_note' => 'Leichtfussig trotz schwerem Mantel.',
            'ko_note' => 'Zaeh wie alter Lederpanzer.',
            'kk_note' => 'Schultert Lasten ohne Klage.',
        ];

        return array_merge($payload, $overrides);
    }

    /**
     * @return array<string, int>
     */
    private function persistedAttributes(array $overrides = []): array
    {
        return array_merge([
            'mu' => 40,
            'kl' => 45,
            'in' => 40,
            'ch' => 35,
            'ff' => 40,
            'ge' => 40,
            'ko' => 45,
            'kk' => 40,
            'strength' => 40,
            'dexterity' => 40,
            'constitution' => 45,
            'intelligence' => 45,
            'wisdom' => 40,
            'charisma' => 35,
        ], $overrides);
    }

    private function grantCoGmWorldAccess(User $owner, User $coGm, int $worldId): void
    {
        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'world_id' => $worldId,
            'is_public' => false,
            'status' => 'active',
        ]);

        CampaignInvitation::query()->create([
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $coGm->id,
            'invited_by' => (int) $owner->id,
            'status' => CampaignInvitation::STATUS_ACCEPTED,
            'role' => CampaignInvitation::ROLE_CO_GM,
            'accepted_at' => now(),
            'responded_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_guest_cannot_access_character_index(): void
    {
        $response = $this->get('/characters');

        $response->assertRedirect('/login');
    }

    public function test_character_create_page_loads_character_sheet_bootstrap_script(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('characters.create'));

        $response
            ->assertOk()
            ->assertSee('character-sheet.global.js', false)
            ->assertSee('x-data="characterSheetForm(', false)
            ->assertDontSee('@vite/client', false)
            ->assertDontSee(':5173', false);
    }

    public function test_user_can_create_character(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/characters', $this->characterPayload());

        $character = Character::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'origin' => 'native_vhaltor',
            'species' => 'mensch',
            'calling' => 'abenteurer',
            'concept' => 'Ich jage Wahrheiten durch Asche und Nebel.',
            'strength' => 40,
            'mu' => 40,
            'kl' => 45,
            'in' => 40,
            'ch' => 35,
            'ff' => 40,
            'ge' => 40,
            'ko' => 45,
            'kk' => 40,
            'mu_note' => 'Haelt auch in Finsternis den Blick gerade.',
            'kk_note' => 'Schultert Lasten ohne Klage.',
            'le_max' => 42,
            'le_current' => 42,
            'ae_max' => 0,
            'ae_current' => 0,
        ]);

        $this->assertSame(['Blutpforten-Sinn'], $character->advantages);
        $this->assertSame(['Aschesucht'], $character->disadvantages);
        $this->assertSame([[
            'name' => 'Seil 10m lang',
            'quantity' => 1,
            'equipped' => false,
        ], [
            'name' => 'Feuerstein',
            'quantity' => 3,
            'equipped' => false,
        ]], $character->inventory);
        $this->assertSame([[
            'name' => 'Kurzschwert',
            'attack' => 48,
            'parry' => 41,
            'damage' => 12,
        ]], $character->weapons);
        $this->assertSame([[
            'name' => 'Lederruestung',
            'protection' => 5,
            'equipped' => true,
        ]], $character->armors);

        $response->assertRedirect();
    }

    public function test_real_world_beginner_must_use_mensch_species(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('characters.create'))
            ->post(route('characters.store'), $this->characterPayload([
                'origin' => 'real_world_beginner',
                'species' => 'elf',
            ]));

        $response->assertRedirect(route('characters.create'));
        $response->assertSessionHasErrors('species');
        $this->assertDatabaseCount('characters', 0);
    }

    public function test_non_magical_characters_have_no_ae_but_magical_callings_gain_ae(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('characters.store'), $this->characterPayload([
            'name' => 'Nichtmagier',
            'species' => 'mensch',
            'calling' => 'abenteurer',
        ]))->assertRedirect();

        $nonMagical = Character::query()
            ->where('user_id', $user->id)
            ->where('name', 'Nichtmagier')
            ->firstOrFail();

        $this->assertSame(0, (int) $nonMagical->ae_max);
        $this->assertSame(0, (int) $nonMagical->ae_current);

        $this->actingAs($user)->post(route('characters.store'), $this->characterPayload([
            'name' => 'Magiebegabt',
            'species' => 'mensch',
            'calling' => 'heiler',
        ]))->assertRedirect();

        $magical = Character::query()
            ->where('user_id', $user->id)
            ->where('name', 'Magiebegabt')
            ->firstOrFail();

        $this->assertSame(45, (int) $magical->ae_max);
        $this->assertSame(45, (int) $magical->ae_current);
    }

    public function test_character_sheet_min_validation_shows_readable_messages_instead_of_translation_keys(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('characters.create'))
            ->post(route('characters.store'), $this->characterPayload([
                'concept' => 'Kurz',
                'gm_secret' => 'zu kurz',
                'world_connection' => 'kurz',
            ]));

        $response->assertRedirect(route('characters.create'));
        $response->assertSessionHasErrors(['concept', 'gm_secret', 'world_connection']);

        /** @var \Illuminate\Support\ViewErrorBag|null $errors */
        $errors = session('errors');
        $this->assertNotNull($errors);

        $messages = $errors->getBag('default')->all();

        $this->assertFalse(in_array('validation.min.string', $messages, true));
        $this->assertTrue(
            collect($messages)->contains(
                fn (string $message): bool => str_contains($message, 'mindestens')
            )
        );
    }

    public function test_character_sheet_shows_specific_required_messages_for_traits(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('characters.create'))
            ->post(route('characters.store'), $this->characterPayload([
                'advantages' => [],
                'disadvantages' => [],
            ]));

        $response->assertRedirect(route('characters.create'));
        $response->assertSessionHasErrors(['advantages', 'disadvantages']);

        /** @var \Illuminate\Support\ViewErrorBag|null $errors */
        $errors = session('errors');
        $this->assertNotNull($errors);

        $messages = $errors->getBag('default')->all();

        $this->assertContains('Bitte mindestens einen Vorteil eintragen.', $messages);
        $this->assertContains('Bitte mindestens einen Nachteil eintragen.', $messages);
    }

    public function test_user_cannot_view_other_users_character(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($intruder)->get(route('characters.show', $character));

        $response->assertForbidden();
    }

    public function test_campaign_co_gm_can_view_and_update_other_users_character_inventory(): void
    {
        $owner = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $character = Character::factory()->create([
            'user_id' => $owner->id,
            'inventory' => [[
                'name' => 'Fackel',
                'quantity' => 1,
                'equipped' => true,
            ]],
            'weapons' => [[
                'name' => 'Dolch',
                'attack' => 40,
                'parry' => 32,
                'damage' => 8,
            ]],
            ...$this->persistedAttributes(),
        ]);

        $this->grantCoGmWorldAccess(
            owner: $owner,
            coGm: $gm,
            worldId: (int) $character->world_id,
        );

        $this->actingAs($gm)
            ->get(route('characters.show', $character))
            ->assertOk()
            ->assertSeeText($character->name);

        $this->actingAs($gm)
            ->get(route('characters.edit', $character))
            ->assertOk();

        $response = $this->actingAs($gm)->put(route('characters.update', $character), [
            ...$this->characterPayload([
                'name' => $character->name,
                'inventory' => [[
                    'name' => 'Fackel',
                    'quantity' => 1,
                    'equipped' => true,
                ], [
                    'name' => 'Seil 10m lang',
                    'quantity' => 2,
                    'equipped' => false,
                ]],
                'weapons' => [[
                    'name' => 'Dolch',
                    'attack' => 42,
                    'parry' => 35,
                    'damage' => 9,
                ]],
            ]),
        ]);

        $character->refresh();

        $this->assertSame([[
            'name' => 'Fackel',
            'quantity' => 1,
            'equipped' => true,
        ], [
            'name' => 'Seil 10m lang',
            'quantity' => 2,
            'equipped' => false,
        ]], $character->inventory);
        $this->assertSame([[
            'name' => 'Dolch',
            'attack' => 42,
            'parry' => 35,
            'damage' => 9,
        ]], $character->weapons);

        $response->assertRedirect(route('characters.show', $character));
    }

    public function test_character_inventory_changes_are_written_to_audit_log(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
            'inventory' => [[
                'name' => 'Fackel',
                'quantity' => 1,
                'equipped' => false,
            ]],
            ...$this->persistedAttributes(),
        ]);

        $this->actingAs($user)->put(route('characters.update', $character), [
            ...$this->characterPayload([
                'name' => $character->name,
                'inventory' => [[
                    'name' => 'Fackel',
                    'quantity' => 1,
                    'equipped' => false,
                ], [
                    'name' => 'Heiltrank',
                    'quantity' => 3,
                    'equipped' => false,
                ]],
            ]),
        ])->assertRedirect(route('characters.show', $character));

        $this->assertDatabaseHas('character_inventory_logs', [
            'character_id' => $character->id,
            'actor_user_id' => $user->id,
            'source' => 'character_sheet_update',
            'action' => 'add',
            'item_name' => 'Heiltrank',
            'quantity' => 3,
        ]);

        $this->assertGreaterThanOrEqual(
            1,
            CharacterInventoryLog::query()
                ->where('character_id', $character->id)
                ->count()
        );
    }

    public function test_campaign_co_gm_can_delete_other_users_character(): void
    {
        $owner = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $character = Character::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->grantCoGmWorldAccess(
            owner: $owner,
            coGm: $gm,
            worldId: (int) $character->world_id,
        );

        $this->actingAs($gm)
            ->delete(route('characters.destroy', $character))
            ->assertRedirect(route('characters.index'));

        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
    }

    public function test_show_redirects_when_character_details_cannot_be_loaded(): void
    {
        $gm = User::factory()->gm()->create();

        $character = Character::factory()->create([
            'user_id' => $gm->id,
        ]);

        $progressionService = \Mockery::mock(CharacterProgressionService::class);
        $progressionService
            ->shouldReceive('describe')
            ->once()
            ->andThrow(new \RuntimeException('Legacy payload is malformed.'));
        $this->app->instance(CharacterProgressionService::class, $progressionService);

        $response = $this->actingAs($gm)->get(route('characters.show', $character));

        $response->assertRedirect(route('characters.index'));
        $response->assertSessionHas('error', 'Charakterdetails konnten nicht geladen werden.');
    }

    public function test_character_edit_form_prefills_existing_sheet_values(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
            'origin' => 'native_vhaltor',
            'species' => 'elf',
            'calling' => 'gelehrter',
            'concept' => 'Reliktjaeger aus den verbrannten Archiven.',
            'world_connection' => 'Verbindung zur Glutpforte von Erest.',
            'gm_secret' => 'Schwur im schwarzen Archiv.',
            'advantages' => ['Klingenfokus'],
            'disadvantages' => ['Blutschuld'],
            'inventory' => [[
                'name' => 'Alte Muenze aus Erest',
                'quantity' => 1,
                'equipped' => false,
            ]],
            'weapons' => [[
                'name' => 'Speer',
                'attack' => 42,
                'parry' => 33,
                'damage' => 12,
            ]],
        ]);

        $response = $this->actingAs($user)->get(route('characters.edit', $character));

        $response->assertOk()
            ->assertSeeText('Reliktjaeger aus den verbrannten Archiven.')
            ->assertSeeText('Verbindung zur Glutpforte von Erest.')
            ->assertSeeText('Schwur im schwarzen Archiv.')
            ->assertSee('Alte Muenze aus Erest', false)
            ->assertSee('Speer', false)
            ->assertSee('12', false);

        $content = $response->getContent();

        $this->assertMatchesRegularExpression('/name="origin" value="native_vhaltor"[^>]*checked/', $content);
        $this->assertStringContainsString('"species":"elf"', html_entity_decode($content));
        $this->assertStringContainsString('"calling":"gelehrter"', html_entity_decode($content));
    }

    public function test_user_can_update_own_character(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
            'name' => 'Vorher',
            ...$this->persistedAttributes([
                'mu' => 45,
                'kl' => 42,
                'in' => 40,
                'ch' => 36,
                'ff' => 38,
                'ge' => 37,
                'ko' => 44,
                'kk' => 46,
                'strength' => 46,
                'dexterity' => 37,
                'constitution' => 44,
                'intelligence' => 42,
                'wisdom' => 40,
                'charisma' => 36,
            ]),
        ]);

        $response = $this->actingAs($user)->put(route('characters.update', $character), [
            ...$this->characterPayload([
                'name' => 'Nachher',
                'epithet' => 'der Namegewandelte',
                'bio' => str_repeat('Neue Legende. ', 4),
                'calling' => 'ritter',
                'mu' => 45,
                'kl' => 42,
                'in' => 40,
                'ch' => 36,
                'ff' => 38,
                'ge' => 37,
                'ko' => 44,
                'kk' => 46,
                'inventory' => [[
                    'name' => 'Wurfhaken',
                    'quantity' => 1,
                    'equipped' => false,
                ], [
                    'name' => 'Salbe gegen Brandwunden',
                    'quantity' => 2,
                    'equipped' => false,
                ]],
                'weapons' => [[
                    'name' => 'Langschwert',
                    'attack' => 53,
                    'parry' => 47,
                    'damage' => 12,
                ]],
            ]),
        ]);

        $character->refresh();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'name' => 'Nachher',
            'calling' => 'ritter',
            'strength' => 46,
            'mu' => 45,
            'kl' => 42,
            'in' => 40,
            'ch' => 36,
            'ff' => 38,
            'ge' => 37,
            'ko' => 44,
            'kk' => 46,
            'le_max' => 50,
            'le_current' => 50,
            'ae_max' => 0,
            'ae_current' => 0,
        ]);

        $this->assertSame(['Blutpforten-Sinn'], $character->advantages);
        $this->assertSame(['Aschesucht'], $character->disadvantages);
        $this->assertSame([[
            'name' => 'Wurfhaken',
            'quantity' => 1,
            'equipped' => false,
        ], [
            'name' => 'Salbe gegen Brandwunden',
            'quantity' => 2,
            'equipped' => false,
        ]], $character->inventory);
        $this->assertSame([[
            'name' => 'Langschwert',
            'attack' => 53,
            'parry' => 47,
            'damage' => 12,
        ]], $character->weapons);

        $response->assertRedirect(route('characters.show', $character));
    }

    public function test_user_cannot_change_attributes_directly_after_creation(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
            ...$this->persistedAttributes(),
        ]);

        $response = $this->actingAs($user)
            ->from(route('characters.edit', $character))
            ->put(route('characters.update', $character), [
                ...$this->characterPayload([
                    'name' => $character->name,
                    'mu' => 41,
                ]),
            ]);

        $response->assertRedirect(route('characters.edit', $character));
        $response->assertSessionHasErrors('mu');

        $character->refresh();
        $this->assertSame(40, (int) $character->mu);
    }

    public function test_updating_character_keeps_current_pools_and_ignores_injected_pool_values(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
            'le_max' => 42,
            'le_current' => 17,
            'ae_max' => 45,
            'ae_current' => 12,
            'species' => 'mensch',
            'calling' => 'heiler',
            ...$this->persistedAttributes(),
        ]);

        $response = $this->actingAs($user)->put(route('characters.update', $character), [
            ...$this->characterPayload([
                'name' => 'Pool bleibt',
                'species' => 'mensch',
                'calling' => 'heiler',
            ]),
            // Manipulationsversuch: diese Werte duerfen nicht uebernommen werden.
            'le_current' => 999,
            'ae_current' => 999,
        ]);

        $character->refresh();

        $this->assertSame(17, (int) $character->le_current);
        $this->assertSame(12, (int) $character->ae_current);
        $this->assertSame(42, (int) $character->le_max);
        $this->assertSame(45, (int) $character->ae_max);

        $response->assertRedirect(route('characters.show', $character));
    }

    public function test_effective_attributes_match_form_logic_species_modifiers_only(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
            'species' => 'elf',
            'calling' => 'magier',
            'mu' => 40,
            'kl' => 40,
            'in' => 40,
            'ch' => 40,
            'ff' => 40,
            'ge' => 40,
            'ko' => 40,
            'kk' => 40,
            'strength' => 40,
            'dexterity' => 40,
            'constitution' => 40,
            'intelligence' => 40,
            'wisdom' => 40,
            'charisma' => 40,
        ]);

        $effective = (array) $character->effective_attributes;

        $this->assertSame(40, (int) ($effective['kl'] ?? 0)); // Berufungsbonus KL+5 darf hier nicht eingerechnet werden.
        $this->assertSame(50, (int) ($effective['in'] ?? 0)); // Speziesbonus Elf +10 wird eingerechnet.
        $this->assertSame(50, (int) ($effective['ch'] ?? 0)); // Speziesbonus Elf +10 wird eingerechnet.
        $this->assertSame(35, (int) ($effective['kk'] ?? 0)); // Speziesmalus Elf -5 wird eingerechnet.
    }

    public function test_user_can_delete_own_character(): void
    {
        $user = User::factory()->create();

        $character = Character::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('characters.destroy', $character));

        $response->assertRedirect(route('characters.index'));
        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
    }

    public function test_deleting_character_hard_removes_or_detaches_all_related_records(): void
    {
        Storage::fake('public');

        $world = World::resolveDefault();
        $owner = User::factory()->create();
        $gm = User::factory()->gm()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'world_id' => $world->id,
            'status' => 'active',
        ]);
        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
        ]);

        $avatarPath = 'character-avatars/delete-target.jpg';
        Storage::disk('public')->put($avatarPath, 'avatar');

        $character = Character::factory()->create([
            'user_id' => $owner->id,
            'world_id' => $world->id,
            'name' => 'Delete Target',
            'avatar_path' => $avatarPath,
            ...$this->persistedAttributes(),
        ]);

        $post = Post::factory()->create([
            'scene_id' => $scene->id,
            'user_id' => $owner->id,
            'character_id' => $character->id,
        ]);

        $postRevision = PostRevision::query()->create([
            'post_id' => $post->id,
            'version' => 1,
            'editor_id' => $owner->id,
            'character_id' => $character->id,
            'post_type' => 'ic',
            'content_format' => 'markdown',
            'content' => 'Revision',
            'meta' => null,
            'moderation_status' => 'approved',
            'created_at' => now(),
        ]);

        $diceRoll = DiceRoll::query()->create([
            'scene_id' => $scene->id,
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'character_id' => $character->id,
            'roll_mode' => DiceRoll::MODE_NORMAL,
            'modifier' => 0,
            'label' => 'Probe',
            'probe_attribute_key' => 'mu',
            'probe_target_value' => 40,
            'probe_is_success' => true,
            'rolls' => [40],
            'kept_roll' => 40,
            'total' => 40,
            'applied_le_delta' => 0,
            'applied_ae_delta' => 0,
            'resulting_le_current' => 40,
            'resulting_ae_current' => 0,
            'is_critical_success' => false,
            'is_critical_failure' => false,
            'created_at' => now(),
        ]);

        CharacterInventoryLog::query()->create([
            'character_id' => $character->id,
            'actor_user_id' => $owner->id,
            'source' => 'test',
            'action' => 'add',
            'item_name' => 'Heiltrank',
            'quantity' => 1,
            'equipped' => false,
            'note' => null,
            'context' => ['reason' => 'test'],
            'created_at' => now(),
        ]);

        CharacterProgressionEvent::query()->create([
            'character_id' => $character->id,
            'actor_user_id' => $gm->id,
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'event_type' => CharacterProgressionEvent::EVENT_XP_MILESTONE,
            'xp_delta' => 25,
            'level_before' => 1,
            'level_after' => 1,
            'ap_delta' => 0,
            'attribute_deltas' => null,
            'reason' => 'Test',
            'meta' => null,
            'created_at' => now(),
        ]);

        PostMention::query()->create([
            'post_id' => $post->id,
            'mentioned_user_id' => $owner->id,
            'mentioned_character_id' => $character->id,
            'mentioned_character_name' => $character->name,
        ]);

        $response = $this->actingAs($owner)->delete(route('characters.destroy', $character));

        $response->assertRedirect(route('characters.index'));
        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'character_id' => null]);
        $this->assertDatabaseHas('post_revisions', ['id' => $postRevision->id, 'character_id' => null]);
        $this->assertDatabaseHas('dice_rolls', ['id' => $diceRoll->id, 'character_id' => null]);
        $this->assertDatabaseMissing('post_mentions', ['mentioned_character_id' => $character->id]);
        $this->assertDatabaseMissing('character_inventory_logs', ['character_id' => $character->id]);
        $this->assertDatabaseMissing('character_progression_events', ['character_id' => $character->id]);
        Storage::disk('public')->assertMissing($avatarPath);

        $this->actingAs($owner)
            ->get(route('characters.show', ['character' => $character->id]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('characters.index'))
            ->assertOk()
            ->assertDontSeeText('Delete Target');
    }
}
