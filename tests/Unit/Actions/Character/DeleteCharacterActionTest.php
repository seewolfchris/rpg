<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Character;

use App\Actions\Character\DeleteCharacterAction;
use App\Models\Character;
use App\Models\Post;
use App\Models\User;
use App\Models\World;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteCharacterActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_character_and_detaches_post_reference_and_avatar(): void
    {
        Storage::fake('public');

        $world = World::resolveDefault();
        $owner = User::factory()->create();

        $avatarPath = 'character-avatars/delete-action-avatar.jpg';
        Storage::disk('public')->put($avatarPath, 'avatar');

        $character = Character::factory()->create([
            'user_id' => $owner->id,
            'world_id' => $world->id,
            'avatar_path' => $avatarPath,
        ]);

        $post = Post::factory()->create([
            'user_id' => $owner->id,
            'character_id' => $character->id,
        ]);

        $action = app(DeleteCharacterAction::class);
        $action->execute($character);

        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'character_id' => null]);
        Storage::disk('public')->assertMissing($avatarPath);
    }

    public function test_it_throws_model_not_found_for_non_persisted_character(): void
    {
        $character = new Character();
        $character->id = 999999;

        $action = app(DeleteCharacterAction::class);

        $this->expectException(ModelNotFoundException::class);

        $action->execute($character);
    }
}
