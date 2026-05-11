<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$rootPath = dirname(__DIR__, 3);

require $rootPath.'/vendor/autoload.php';

$app = require $rootPath.'/bootstrap/app.php';
/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$mode = (string) ($argv[1] ?? '');
$worldSlug = (string) ($argv[2] ?? '');
$postId = (int) ($argv[3] ?? 0);
$actorUserId = (int) ($argv[4] ?? 0);
$targetStatus = (string) ($argv[5] ?? 'approved');
$moderationNote = trim((string) ($argv[6] ?? ''));
$holdMillis = max(0, (int) ($argv[7] ?? 0));
$startedAt = microtime(true);

if (! in_array($mode, ['single', 'bulk'], true)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unsupported mode.',
        'started_at' => $startedAt,
        'finished_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);
    exit(99);
}

if ($worldSlug === '' || $postId <= 0 || $actorUserId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required arguments.',
        'started_at' => $startedAt,
        'finished_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);
    exit(99);
}

if (! in_array($targetStatus, ['pending', 'approved', 'rejected'], true)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unsupported moderation status.',
        'started_at' => $startedAt,
        'finished_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);
    exit(99);
}

try {
    if ($holdMillis > 0) {
        usleep($holdMillis * 1000);
    }

    $session = app('session')->driver();
    $session->start();

    Auth::guard('web')->loginUsingId($actorUserId);
    $csrfToken = bin2hex(random_bytes(20));
    $session->put('_token', $csrfToken);
    $session->save();

    if ($mode === 'single') {
        $path = sprintf('/w/%s/posts/%d/moderate', $worldSlug, $postId);
        $request = Request::create($path, 'PATCH', [
            'moderation_status' => $targetStatus,
            'moderation_note' => $moderationNote,
        ]);
    } else {
        $path = sprintf('/w/%s/gm/moderation/bulk', $worldSlug);
        $request = Request::create($path, 'PATCH', [
            'moderation_status' => $targetStatus,
            'moderation_note' => $moderationNote,
            'status' => 'pending',
            'q' => '',
            'post_ids' => [$postId],
        ]);
    }

    $request->headers->set('referer', sprintf('/w/%s/gm/moderation?status=pending', $worldSlug));
    $request->headers->set('X-CSRF-TOKEN', $csrfToken);
    $request->cookies->set($session->getName(), $session->getId());

    /** @var HttpKernel $httpKernel */
    $httpKernel = app(HttpKernel::class);
    $response = $httpKernel->handle($request);
    $httpKernel->terminate($request, $response);

    echo json_encode([
        'mode' => $mode,
        'status' => 'ok',
        'http_status' => $response->getStatusCode(),
        'location' => (string) $response->headers->get('Location', ''),
        'started_at' => $startedAt,
        'finished_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable $exception) {
    echo json_encode([
        'mode' => $mode,
        'status' => 'error',
        'message' => $exception->getMessage(),
        'class' => $exception::class,
        'started_at' => $startedAt,
        'finished_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);
    exit(99);
}
