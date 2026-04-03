<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Scene;
use App\Models\World;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LegacyAuthenticatedRedirectController extends Controller
{
    public function campaignsIndex(Request $request): RedirectResponse
    {
        return redirect()->route('campaigns.index', ['world' => $this->resolveWorldSlug($request)], 301);
    }

    public function campaignsCreate(Request $request): RedirectResponse
    {
        return redirect()->route('campaigns.create', ['world' => $this->resolveWorldSlug($request)], 301);
    }

    public function campaignsShow(Campaign $campaign): RedirectResponse
    {
        return redirect()->route('campaigns.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ], 301);
    }

    public function campaignsEdit(Campaign $campaign): RedirectResponse
    {
        return redirect()->route('campaigns.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ], 301);
    }

    public function campaignScenesCreate(Campaign $campaign): RedirectResponse
    {
        return redirect()->route('campaigns.scenes.create', [
            'world' => $campaign->world,
            'campaign' => $campaign,
        ], 301);
    }

    public function campaignScenesShow(Campaign $campaign, Scene $scene): RedirectResponse
    {
        abort_unless($scene->campaign_id === $campaign->id, 404);

        return redirect()->route('campaigns.scenes.show', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ], 301);
    }

    public function campaignScenesEdit(Campaign $campaign, Scene $scene): RedirectResponse
    {
        abort_unless($scene->campaign_id === $campaign->id, 404);

        return redirect()->route('campaigns.scenes.edit', [
            'world' => $campaign->world,
            'campaign' => $campaign,
            'scene' => $scene,
        ], 301);
    }

    public function sceneSubscriptionsIndex(Request $request): RedirectResponse
    {
        return redirect()->route('scene-subscriptions.index', ['world' => $this->resolveWorldSlug($request)], 301);
    }

    public function bookmarksIndex(Request $request): RedirectResponse
    {
        return redirect()->route('bookmarks.index', ['world' => $this->resolveWorldSlug($request)], 301);
    }

    public function postsEdit(Post $post): RedirectResponse
    {
        /** @var Scene $scene */
        $scene = $post->scene;
        /** @var Campaign $campaign */
        $campaign = $scene->campaign;

        return redirect()->route('posts.edit', [
            'world' => $campaign->world,
            'post' => $post,
        ], 301);
    }

    public function gmModerationIndex(Request $request): RedirectResponse
    {
        return redirect()->route('gm.moderation.index', ['world' => $this->resolveWorldSlug($request)], 301);
    }

    private function resolveWorldSlug(Request $request): string
    {
        $sessionSlug = $request->session()->get('world_slug');

        if (is_string($sessionSlug) && $sessionSlug !== '') {
            return $sessionSlug;
        }

        return World::defaultSlug();
    }
}
