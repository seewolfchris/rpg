<?php

declare(strict_types=1);

use App\Actions\Notification\UpsertWebPushSubscriptionAction;
use App\Models\PushSubscription;
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

$userId = (int) ($argv[1] ?? 0);
$worldId = (int) ($argv[2] ?? 0);
$endpoint = (string) ($argv[3] ?? '');
$injectDuplicate = ((int) ($argv[4] ?? 0)) === 1;
$duplicateInjected = false;

if ($injectDuplicate) {
    PushSubscription::creating(function (PushSubscription $subscription) use (&$duplicateInjected): void {
        if ($duplicateInjected) {
            return;
        }

        $duplicateInjected = true;

        DB::table('push_subscriptions')->insert([
            'subscribable_type' => (string) $subscription->subscribable_type,
            'subscribable_id' => (int) $subscription->subscribable_id,
            'endpoint' => (string) $subscription->endpoint,
            'public_key' => 'worker-key-old',
            'auth_token' => 'worker-token-old',
            'content_encoding' => 'aesgcm',
            'user_id' => (int) $subscription->user_id,
            'world_id' => (int) $subscription->world_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });
}

try {
    /** @var User $user */
    $user = User::query()->findOrFail($userId);
    /** @var World $world */
    $world = World::query()->findOrFail($worldId);
    /** @var UpsertWebPushSubscriptionAction $action */
    $action = $app->make(UpsertWebPushSubscriptionAction::class);

    $subscription = $action->execute(
        user: $user,
        world: $world,
        endpoint: $endpoint,
        publicKey: 'worker-key-new',
        authToken: 'worker-token-new',
        contentEncoding: 'aes128gcm',
    );

    echo json_encode([
        'status' => 'ok',
        'duplicate_injected' => $duplicateInjected,
        'subscription_id' => (int) $subscription->id,
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

