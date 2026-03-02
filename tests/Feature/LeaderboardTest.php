<?php

namespace Tests\Feature;

use App\Models\PointEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_ordered_leaderboard(): void
    {
        $viewer = User::factory()->create(['points' => 15]);
        $high = User::factory()->create(['name' => 'High Player', 'points' => 50]);
        $mid = User::factory()->create(['name' => 'Mid Player', 'points' => 30]);
        $low = User::factory()->create(['name' => 'Low Player', 'points' => 10]);

        PointEvent::query()->create([
            'user_id' => $high->id,
            'source_type' => 'post',
            'source_id' => 101,
            'event_key' => 'approved',
            'points' => 10,
            'meta' => null,
            'created_at' => now(),
        ]);
        PointEvent::query()->create([
            'user_id' => $high->id,
            'source_type' => 'post',
            'source_id' => 102,
            'event_key' => 'approved',
            'points' => 10,
            'meta' => null,
            'created_at' => now(),
        ]);
        PointEvent::query()->create([
            'user_id' => $mid->id,
            'source_type' => 'post',
            'source_id' => 103,
            'event_key' => 'approved',
            'points' => 10,
            'meta' => null,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($viewer)->get(route('leaderboard.index'));

        $response->assertOk();
        $response->assertSee('Rangliste der Chronisten');
        $response->assertSeeInOrder(['High Player', 'Mid Player', 'Low Player']);
        $response->assertSee('Dein aktueller Rang:');
        $response->assertSee('#3');
    }

    public function test_guest_cannot_access_leaderboard(): void
    {
        $response = $this->get(route('leaderboard.index'));

        $response->assertRedirect(route('login'));
    }
}
