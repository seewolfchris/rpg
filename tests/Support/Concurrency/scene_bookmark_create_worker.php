<?php

declare(strict_types=1);

use App\Actions\Scene\ToggleSceneBookmarkAction;
use App\Models\Campaign;
use App\Models\Scene;
use App\Models\SceneBookmark;
use App\Models\User;
use App\Models\World;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$rootPath = dirname(__DIR__, 3);

require $rootPath.'/vendor/autoload.php';

$app = require $rootPath.'/bootstrap/app.php';
/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$worldId = (int) ($argv[1] ?? 0);
$campaignId = (int) ($argv[2] ?? 0);
$sceneId = (int) ($argv[3] ?? 0);
$userId = (int) ($argv[4] ?? 0);
$requestedPostIdRaw = (int) ($argv[5] ?? 0);
$label = (string) ($argv[6] ?? '');
$injectDuplicate = ((int) ($argv[7] ?? 0)) === 1;
$duplicateInjected = false;

if ($injectDuplicate) {
    SceneBookmark::creating(function (SceneBookmark $bookmark) use (&$duplicateInjected): void {
        if ($duplicateInjected) {
            return;
        }

        $duplicateInjected = true;

        DB::table('scene_bookmarks')->insert([
            'user_id' => (int) $bookmark->user_id,
            'scene_id' => (int) $bookmark->scene_id,
            'post_id' => null,
            'label' => 'Injected duplicate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });
}

try {
    /** @var World $world */
    $world = World::query()->findOrFail($worldId);
    /** @var Campaign $campaign */
    $campaign = Campaign::query()->findOrFail($campaignId);
    /** @var Scene $scene */
    $scene = Scene::query()->findOrFail($sceneId);
    /** @var User $user */
    $user = User::query()->findOrFail($userId);
    /** @var ToggleSceneBookmarkAction $action */
    $action = $app->make(ToggleSceneBookmarkAction::class);

    $bookmark = $action->create(
        world: $world,
        campaign: $campaign,
        scene: $scene,
        user: $user,
        requestedPostId: $requestedPostIdRaw > 0 ? $requestedPostIdRaw : null,
        label: $label,
    );

    echo json_encode([
        'status' => 'ok',
        'duplicate_injected' => $duplicateInjected,
        'bookmark_id' => (int) $bookmark->id,
    ], JSON_THROW_ON_ERROR);

    exit(0);
} catch (Throwable $exception) {
    echo json_encode([
        'status' => 'error',
        'duplicate_injected' => $duplicateInjected,
        'message' => $exception->getMessage(),
        'class' => $exception::class,
    ], JSON_THROW_ON_ERROR);

    exit(99);
}

