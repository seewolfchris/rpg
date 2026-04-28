<?php

namespace Tests\Feature;

use App\Domain\Handout\HandoutMediaService;
use App\Enums\CampaignMembershipRole;
use App\Models\Campaign;
use App\Models\CampaignMembership;
use App\Models\Handout;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class HandoutManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_gm_can_create_handout_with_image(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();

        $response = $this->actingAs($gm)->post(route('campaigns.handouts.store', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]), [
            'title' => 'Karte des Nordpasses',
            'description' => 'Pfadmarken und alte Wachtürme.',
            'scene_id' => $scene->id,
            'version_label' => 'v1.0',
            'sort_order' => 10,
            'handout_file' => UploadedFile::fake()->image('nordpass.jpg', 1600, 900),
        ]);

        $handout = Handout::query()->where('campaign_id', $campaign->id)->firstOrFail();

        $response->assertRedirect(route('campaigns.handouts.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]));

        $this->assertDatabaseHas('handouts', [
            'id' => $handout->id,
            'campaign_id' => $campaign->id,
            'scene_id' => $scene->id,
            'created_by' => $gm->id,
            'title' => 'Karte des Nordpasses',
        ]);

        $media = $handout->getMedia(Handout::HANDOUT_FILE_COLLECTION);
        $this->assertCount(1, $media);
        $this->assertSame('local', (string) $media->first()?->disk);
    }

    public function test_player_and_trusted_player_cannot_create_handout(): void
    {
        [$campaign, $scene, $player, , $trustedPlayer] = $this->seedCampaignContext();

        foreach ([$player, $trustedPlayer] as $actor) {
            $response = $this->actingAs($actor)->post(route('campaigns.handouts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), [
                'title' => 'Verbotenes Handout '.$actor->id,
                'scene_id' => $scene->id,
                'handout_file' => UploadedFile::fake()->image('blocked.jpg', 1200, 700),
            ]);

            $response->assertForbidden();
        }

        $this->assertDatabaseCount('handouts', 0);
    }

    public function test_store_rejects_svg_handout_file(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();

        $response = $this->actingAs($gm)
            ->from(route('campaigns.handouts.create', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]))
            ->post(route('campaigns.handouts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), [
                'title' => 'Ungueltiges SVG',
                'scene_id' => $scene->id,
                'handout_file' => UploadedFile::fake()->createWithContent(
                    'invalid.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>'
                ),
            ]);

        $response->assertRedirect(route('campaigns.handouts.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));
        $response->assertSessionHasErrors('handout_file');
        $this->assertDatabaseCount('handouts', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_store_rejects_non_image_handout_file(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();

        $response = $this->actingAs($gm)
            ->from(route('campaigns.handouts.create', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]))
            ->post(route('campaigns.handouts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), [
                'title' => 'Ungueltige Textdatei',
                'scene_id' => $scene->id,
                'handout_file' => UploadedFile::fake()->create('invalid.txt', 12, 'text/plain'),
            ]);

        $response->assertRedirect(route('campaigns.handouts.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));
        $response->assertSessionHasErrors('handout_file');
        $this->assertDatabaseCount('handouts', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_trusted_player_cannot_manage_existing_handout(): void
    {
        [$campaign, $scene, , $gm, $trustedPlayer] = $this->seedCampaignContext();
        $handout = $this->createHandoutWithFile($campaign, $gm, $scene, false, 'Nur GM verwaltet');

        $this->actingAs($trustedPlayer)->patch(route('campaigns.handouts.update', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]), [
            'title' => 'Manipuliert',
            'description' => 'x',
        ])->assertForbidden();

        $this->actingAs($trustedPlayer)->patch(route('campaigns.handouts.reveal', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]))->assertForbidden();
    }

    public function test_gm_can_update_handout_metadata(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();
        $handout = $this->createHandoutWithFile($campaign, $gm, $scene);

        $response = $this->actingAs($gm)->patch(route('campaigns.handouts.update', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]), [
            'title' => 'Karte des Nordpasses (aktualisiert)',
            'description' => 'Neue Pfadnotizen mit Sperrzonen.',
            'scene_id' => $scene->id,
            'version_label' => 'v1.1',
            'sort_order' => 20,
        ]);

        $response->assertRedirect(route('campaigns.handouts.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]));

        $this->assertDatabaseHas('handouts', [
            'id' => $handout->id,
            'title' => 'Karte des Nordpasses (aktualisiert)',
            'version_label' => 'v1.1',
            'sort_order' => 20,
            'updated_by' => $gm->id,
        ]);
    }

    public function test_gm_can_replace_handout_file_and_keep_single_primary_file(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();
        $handout = $this->createHandoutWithFile($campaign, $gm, $scene);

        $oldMediaId = (int) $handout->getFirstMedia(Handout::HANDOUT_FILE_COLLECTION)?->id;

        $response = $this->actingAs($gm)->patch(route('campaigns.handouts.update', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]), [
            'title' => $handout->title,
            'description' => $handout->description,
            'scene_id' => $scene->id,
            'version_label' => $handout->version_label,
            'sort_order' => $handout->sort_order,
            'handout_file' => UploadedFile::fake()->image('replacement.jpg', 1800, 1000),
        ]);

        $response->assertRedirect();

        $handout->refresh();
        $media = $handout->getMedia(Handout::HANDOUT_FILE_COLLECTION);

        $this->assertCount(1, $media);
        $newMediaId = (int) $media->first()->id;
        $this->assertNotSame($oldMediaId, $newMediaId);
        $this->assertDatabaseMissing('media', ['id' => $oldMediaId]);
        $this->assertDatabaseHas('media', [
            'id' => $newMediaId,
            'model_type' => Handout::class,
            'model_id' => $handout->id,
            'collection_name' => Handout::HANDOUT_FILE_COLLECTION,
            'disk' => 'local',
        ]);
    }

    public function test_failed_file_replacement_keeps_existing_primary_file(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();
        $handout = $this->createHandoutWithFile($campaign, $gm, $scene);

        $oldMediaId = (int) $handout->getFirstMedia(Handout::HANDOUT_FILE_COLLECTION)?->id;

        $mockedService = \Mockery::mock(HandoutMediaService::class);
        $mockedService->shouldReceive('replacePrimaryFile')
            ->once()
            ->andThrow(new RuntimeException('Die neue Handout-Datei konnte nicht gespeichert werden.'));
        $this->app->instance(HandoutMediaService::class, $mockedService);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.handouts.edit', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->patch(route('campaigns.handouts.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]), [
                'title' => $handout->title,
                'description' => $handout->description,
                'scene_id' => $scene->id,
                'version_label' => $handout->version_label,
                'sort_order' => $handout->sort_order,
                'handout_file' => UploadedFile::fake()->image('replacement-fail.jpg', 1400, 900),
            ]);

        $response->assertRedirect(route('campaigns.handouts.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]));
        $response->assertSessionHasErrors('handout_file');

        $handout->refresh();
        $media = $handout->getMedia(Handout::HANDOUT_FILE_COLLECTION);

        $this->assertCount(1, $media);
        $this->assertSame($oldMediaId, (int) $media->first()->id);
    }

    public function test_update_rejects_svg_replacement_and_keeps_existing_primary_file(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();
        $handout = $this->createHandoutWithFile($campaign, $gm, $scene);

        $oldMediaId = (int) $handout->getFirstMedia(Handout::HANDOUT_FILE_COLLECTION)?->id;

        $response = $this->actingAs($gm)
            ->from(route('campaigns.handouts.edit', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->patch(route('campaigns.handouts.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]), [
                'title' => $handout->title,
                'description' => $handout->description,
                'scene_id' => $scene->id,
                'version_label' => $handout->version_label,
                'sort_order' => $handout->sort_order,
                'handout_file' => UploadedFile::fake()->createWithContent(
                    'replace.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="5"/></svg>'
                ),
            ]);

        $response->assertRedirect(route('campaigns.handouts.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]));
        $response->assertSessionHasErrors('handout_file');

        $handout->refresh();
        $media = $handout->getMedia(Handout::HANDOUT_FILE_COLLECTION);

        $this->assertCount(1, $media);
        $this->assertSame($oldMediaId, (int) $media->first()->id);

        $this->actingAs($gm)->get(route('campaigns.handouts.file', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]))->assertOk();
    }

    public function test_update_rejects_non_image_replacement_and_keeps_existing_primary_file(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();
        $handout = $this->createHandoutWithFile($campaign, $gm, $scene);

        $oldMediaId = (int) $handout->getFirstMedia(Handout::HANDOUT_FILE_COLLECTION)?->id;

        $response = $this->actingAs($gm)
            ->from(route('campaigns.handouts.edit', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]))
            ->patch(route('campaigns.handouts.update', [
                'world' => $campaign->world,
                'campaign' => $campaign,
                'handout' => $handout,
            ]), [
                'title' => $handout->title,
                'description' => $handout->description,
                'scene_id' => $scene->id,
                'version_label' => $handout->version_label,
                'sort_order' => $handout->sort_order,
                'handout_file' => UploadedFile::fake()->create('replace.txt', 14, 'text/plain'),
            ]);

        $response->assertRedirect(route('campaigns.handouts.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]));
        $response->assertSessionHasErrors('handout_file');

        $handout->refresh();
        $media = $handout->getMedia(Handout::HANDOUT_FILE_COLLECTION);

        $this->assertCount(1, $media);
        $this->assertSame($oldMediaId, (int) $media->first()->id);

        $this->actingAs($gm)->get(route('campaigns.handouts.file', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]))->assertOk();
    }

    public function test_gm_can_reveal_and_unreveal_handout(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();
        $handout = $this->createHandoutWithFile($campaign, $gm, $scene, false);

        $this->actingAs($gm)->patch(route('campaigns.handouts.reveal', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]))->assertRedirect();

        $this->assertDatabaseHas('handouts', [
            'id' => $handout->id,
        ]);
        $this->assertNotNull($handout->fresh()->revealed_at);

        $this->actingAs($gm)->patch(route('campaigns.handouts.unreveal', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]))->assertRedirect();

        $this->assertNull($handout->fresh()->revealed_at);
    }

    public function test_store_rejects_scene_id_from_other_campaign(): void
    {
        [$campaign, , , $gm] = $this->seedCampaignContext();

        $otherCampaign = Campaign::factory()->create([
            'owner_id' => $gm->id,
            'world_id' => $campaign->world_id,
            'is_public' => false,
            'status' => 'active',
        ]);
        $otherScene = Scene::factory()->create([
            'campaign_id' => $otherCampaign->id,
            'created_by' => $gm->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($gm)
            ->from(route('campaigns.handouts.create', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]))
            ->post(route('campaigns.handouts.store', [
                'world' => $campaign->world,
                'campaign' => $campaign,
            ]), [
                'title' => 'Cross-Campaign Scene',
                'scene_id' => $otherScene->id,
                'handout_file' => UploadedFile::fake()->image('cross-scene.jpg', 1200, 700),
            ]);

        $response->assertRedirect(route('campaigns.handouts.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));
        $response->assertSessionHasErrors('scene_id');

        $this->assertDatabaseMissing('handouts', [
            'title' => 'Cross-Campaign Scene',
        ]);
    }

    public function test_deleted_handout_disappears_from_grid_and_detail_and_file_routes(): void
    {
        [$campaign, $scene, , $gm] = $this->seedCampaignContext();
        $handout = $this->createHandoutWithFile($campaign, $gm, $scene, true, 'Löschbarer Beweis');

        $mediaId = (int) $handout->getFirstMedia(Handout::HANDOUT_FILE_COLLECTION)?->id;

        $this->actingAs($gm)->delete(route('campaigns.handouts.destroy', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout,
        ]))->assertRedirect(route('campaigns.handouts.index', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]));

        $this->assertDatabaseMissing('handouts', [
            'id' => $handout->id,
        ]);

        $this->actingAs($gm)->get(route('campaigns.handouts.index', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ]))
            ->assertOk()
            ->assertDontSee('Löschbarer Beweis');

        $this->actingAs($gm)->get(route('campaigns.handouts.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout->id,
        ]))->assertNotFound();

        $this->actingAs($gm)->get(route('campaigns.handouts.file', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'handout' => $handout->id,
        ]))->assertNotFound();

        if ($mediaId > 0) {
            $this->assertDatabaseMissing('media', [
                'id' => $mediaId,
            ]);
        }
    }

    /**
     * @return array{0: Campaign, 1: Scene, 2: User, 3: User, 4: User}
     */
    private function seedCampaignContext(): array
    {
        $owner = User::factory()->gm()->create();
        $gm = User::factory()->create();
        $player = User::factory()->create();
        $trustedPlayer = User::factory()->create();

        $campaign = Campaign::factory()->create([
            'owner_id' => $owner->id,
            'status' => 'active',
            'is_public' => false,
        ]);

        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $owner->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $gm->id,
            'role' => CampaignMembershipRole::GM->value,
            'assigned_by' => $owner->id,
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $player->id,
            'role' => CampaignMembershipRole::PLAYER->value,
            'assigned_by' => $owner->id,
        ]);
        CampaignMembership::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $trustedPlayer->id,
            'role' => CampaignMembershipRole::TRUSTED_PLAYER->value,
            'assigned_by' => $owner->id,
        ]);

        $scene = Scene::factory()->create([
            'campaign_id' => $campaign->id,
            'created_by' => $owner->id,
            'status' => 'open',
            'allow_ooc' => true,
        ]);

        return [$campaign, $scene, $player, $gm, $trustedPlayer];
    }

    private function createHandoutWithFile(
        Campaign $campaign,
        User $creator,
        ?Scene $scene = null,
        bool $revealed = false,
        ?string $title = null,
    ): Handout {
        $handout = Handout::factory()->create([
            'campaign_id' => $campaign->id,
            'scene_id' => $scene?->id,
            'created_by' => $creator->id,
            'updated_by' => null,
            'title' => $title ?? 'Handout '.uniqid('', true),
            'revealed_at' => $revealed ? now() : null,
        ]);

        $handout
            ->addMedia(UploadedFile::fake()->image('handout-'.$handout->id.'.jpg', 1200, 700))
            ->toMediaCollection(Handout::HANDOUT_FILE_COLLECTION);

        return $handout->fresh(['media']) ?? $handout;
    }
}
