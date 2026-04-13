<?php

declare(strict_types=1);

use App\Models\CampaignInvitation;
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

$invitationId = (int) ($argv[1] ?? 0);
$userId = (int) ($argv[2] ?? 0);
$decision = (string) ($argv[3] ?? CampaignInvitation::STATUS_DECLINED);
$holdMillis = max(0, (int) ($argv[4] ?? 0));
$startedAt = microtime(true);

if (! in_array($decision, [CampaignInvitation::STATUS_ACCEPTED, CampaignInvitation::STATUS_DECLINED], true)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unsupported decision.',
    ], JSON_THROW_ON_ERROR);
    exit(99);
}

try {
    $invitation = CampaignInvitation::query()
        ->with('campaign.world')
        ->findOrFail($invitationId);

    if ((int) $invitation->user_id !== $userId) {
        echo json_encode([
            'status' => 'forbidden',
            'final_status' => (string) $invitation->status,
            'started_at' => $startedAt,
            'finished_at' => microtime(true),
        ], JSON_THROW_ON_ERROR);
        exit(0);
    }

    $world = $invitation->campaign?->world;
    $worldSlug = is_object($world) ? (string) ($world->slug ?? '') : '';

    if ($worldSlug === '') {
        throw new RuntimeException('Invitation campaign world slug is missing.');
    }

    if ($holdMillis > 0) {
        usleep($holdMillis * 1000);
    }

    $session = app('session')->driver();
    $session->start();

    Auth::guard('web')->loginUsingId($userId);
    $csrfToken = bin2hex(random_bytes(20));
    $session->put('_token', $csrfToken);
    $session->save();

    $endpoint = $decision === CampaignInvitation::STATUS_ACCEPTED
        ? 'accept'
        : 'decline';
    $path = sprintf('/w/%s/campaign-invitations/%d/%s', $worldSlug, $invitationId, $endpoint);
    $request = Request::create($path, 'PATCH');
    $request->headers->set('X-CSRF-TOKEN', $csrfToken);
    $request->cookies->set($session->getName(), $session->getId());

    /** @var HttpKernel $httpKernel */
    $httpKernel = app(HttpKernel::class);
    $response = $httpKernel->handle($request);
    $httpKernel->terminate($request, $response);

    $updatedInvitation = CampaignInvitation::query()->findOrFail($invitationId);
    $result = [
        'status' => (string) $updatedInvitation->status === $decision
            ? 'updated'
            : 'already_closed',
        'final_status' => (string) $updatedInvitation->status,
        'http_status' => $response->getStatusCode(),
        'location' => (string) $response->headers->get('Location', ''),
    ];

    $result['started_at'] = $startedAt;
    $result['finished_at'] = microtime(true);

    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable $exception) {
    echo json_encode([
        'status' => 'error',
        'message' => $exception->getMessage(),
        'class' => $exception::class,
        'started_at' => $startedAt,
        'finished_at' => microtime(true),
    ], JSON_THROW_ON_ERROR);
    exit(99);
}
