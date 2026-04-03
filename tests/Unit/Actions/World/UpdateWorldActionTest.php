<?php

namespace Tests\Unit\Actions\World;

use App\Actions\World\UpdateWorldAction;
use App\Models\World;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateWorldActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_non_default_world_when_invariants_are_met(): void
    {
        $world = World::factory()->create([
            'slug' => 'neon-run',
            'name' => 'Neon Run',
            'is_active' => true,
            'position' => 220,
        ]);

        app(UpdateWorldAction::class)->execute($world, [
            'name' => 'Neon Run Prime',
            'slug' => 'neon-run-prime',
            'tagline' => 'Cyber Mystery',
            'description' => 'Updated world profile.',
            'position' => 230,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('worlds', [
            'id' => $world->id,
            'name' => 'Neon Run Prime',
            'slug' => 'neon-run-prime',
            'is_active' => true,
            'position' => 230,
        ]);
    }

    public function test_it_rejects_default_world_slug_changes(): void
    {
        $defaultWorld = World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->firstOrFail();

        $this->expectException(ValidationException::class);

        try {
            app(UpdateWorldAction::class)->execute($defaultWorld, [
                'name' => (string) $defaultWorld->name,
                'slug' => 'anderer-default-slug',
                'tagline' => (string) ($defaultWorld->tagline ?? ''),
                'description' => (string) ($defaultWorld->description ?? ''),
                'position' => (int) $defaultWorld->position,
                'is_active' => true,
            ]);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
            throw $exception;
        }
    }

    public function test_it_rejects_default_world_deactivation(): void
    {
        $defaultWorld = World::query()
            ->where('slug', (string) config('worlds.default_slug'))
            ->firstOrFail();
        World::factory()->create([
            'slug' => 'flankenwelt',
            'is_active' => true,
            'position' => 1200,
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(UpdateWorldAction::class)->execute($defaultWorld, [
                'name' => (string) $defaultWorld->name,
                'slug' => (string) $defaultWorld->slug,
                'tagline' => (string) ($defaultWorld->tagline ?? ''),
                'description' => (string) ($defaultWorld->description ?? ''),
                'position' => (int) $defaultWorld->position,
                'is_active' => false,
            ]);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_active', $exception->errors());
            throw $exception;
        }
    }

    public function test_it_rejects_deactivation_when_it_would_leave_no_active_world(): void
    {
        $targetWorld = World::factory()->create([
            'slug' => 'einsame-welt',
            'is_active' => true,
            'position' => 1700,
        ]);
        World::query()
            ->whereKeyNot($targetWorld->id)
            ->update(['is_active' => false]);

        $this->expectException(ValidationException::class);

        try {
            app(UpdateWorldAction::class)->execute($targetWorld, [
                'name' => (string) $targetWorld->name,
                'slug' => (string) $targetWorld->slug,
                'tagline' => (string) ($targetWorld->tagline ?? ''),
                'description' => (string) ($targetWorld->description ?? ''),
                'position' => (int) $targetWorld->position,
                'is_active' => false,
            ]);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_active', $exception->errors());
            throw $exception;
        }
    }

    public function test_it_rejects_update_when_configured_default_world_is_missing(): void
    {
        config(['worlds.default_slug' => 'fehlende-standardwelt']);

        $world = World::factory()->create([
            'slug' => 'night-harbor',
            'is_active' => true,
            'position' => 2100,
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(UpdateWorldAction::class)->execute($world, [
                'name' => (string) $world->name,
                'slug' => 'night-harbor-prime',
                'tagline' => (string) ($world->tagline ?? ''),
                'description' => (string) ($world->description ?? ''),
                'position' => (int) $world->position,
                'is_active' => true,
            ]);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('slug', $exception->errors());
            throw $exception;
        }
    }
}
