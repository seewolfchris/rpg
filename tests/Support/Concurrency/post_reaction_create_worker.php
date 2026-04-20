<?php

declare(strict_types=1);

use App\Actions\Post\CreatePostReactionAction;
use App\Models\Post;
use App\Models\PostReaction;
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
$postId = (int) ($argv[2] ?? 0);
$reactorId = (int) ($argv[3] ?? 0);
$emoji = (string) ($argv[4] ?? 'heart');
$injectDuplicate = ((int) ($argv[5] ?? 0)) === 1;
$duplicateInjected = false;

if ($injectDuplicate) {
    PostReaction::creating(function (PostReaction $reaction) use (&$duplicateInjected): void {
        if ($duplicateInjected) {
            return;
        }

        $duplicateInjected = true;

        DB::table('post_reactions')->insert([
            'post_id' => (int) $reaction->post_id,
            'user_id' => (int) $reaction->user_id,
            'emoji' => (string) $reaction->emoji,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });
}

try {
    /** @var World $world */
    $world = World::query()->findOrFail($worldId);
    /** @var Post $post */
    $post = Post::query()->findOrFail($postId);
    /** @var User $reactor */
    $reactor = User::query()->findOrFail($reactorId);
    /** @var CreatePostReactionAction $action */
    $action = $app->make(CreatePostReactionAction::class);

    $action->execute($world, $post, $reactor, $emoji);

    echo json_encode([
        'status' => 'ok',
        'duplicate_injected' => $duplicateInjected,
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

