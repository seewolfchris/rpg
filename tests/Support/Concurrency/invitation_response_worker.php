<?php

declare(strict_types=1);

use App\Models\CampaignInvitation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

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
    $result = DB::transaction(function () use ($invitationId, $userId, $decision, $holdMillis): array {
        $invitation = CampaignInvitation::query()
            ->whereKey($invitationId)
            ->lockForUpdate()
            ->firstOrFail();

        if ((int) $invitation->user_id !== $userId) {
            return [
                'status' => 'forbidden',
                'final_status' => (string) $invitation->status,
            ];
        }

        if ($holdMillis > 0) {
            usleep($holdMillis * 1000);
        }

        if ($invitation->status !== CampaignInvitation::STATUS_PENDING) {
            return [
                'status' => 'already_closed',
                'final_status' => (string) $invitation->status,
            ];
        }

        $isAccept = $decision === CampaignInvitation::STATUS_ACCEPTED;

        $invitation->status = $isAccept
            ? CampaignInvitation::STATUS_ACCEPTED
            : CampaignInvitation::STATUS_DECLINED;
        $invitation->accepted_at = $isAccept ? now() : null;
        $invitation->responded_at = now();
        $invitation->save();

        return [
            'status' => 'updated',
            'final_status' => (string) $invitation->status,
        ];
    }, 3);

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
