<?php

declare(strict_types=1);

use App\Actions\Campaign\UpsertCampaignInvitationAction;
use App\Actions\Campaign\UpsertCampaignInvitationInput;
use App\Models\Campaign;
use App\Models\CampaignInvitation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$rootPath = dirname(__DIR__, 3);

require $rootPath.'/vendor/autoload.php';

$app = require $rootPath.'/bootstrap/app.php';
/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$campaignId = (int) ($argv[1] ?? 0);
$inviteeUserId = (int) ($argv[2] ?? 0);
$inviterUserId = (int) ($argv[3] ?? 0);
$requestedRole = (string) ($argv[4] ?? CampaignInvitation::ROLE_PLAYER);
$injectDuplicate = ((int) ($argv[5] ?? 0)) === 1;
$duplicateInjected = false;

if ($injectDuplicate) {
    CampaignInvitation::creating(function (CampaignInvitation $invitation) use (&$duplicateInjected): void {
        if ($duplicateInjected) {
            return;
        }

        $duplicateInjected = true;

        DB::table('campaign_invitations')->insert([
            'campaign_id' => (int) $invitation->campaign_id,
            'user_id' => (int) $invitation->user_id,
            'invited_by' => (int) $invitation->invited_by,
            'status' => CampaignInvitation::STATUS_PENDING,
            'role' => CampaignInvitation::ROLE_PLAYER,
            'accepted_at' => null,
            'responded_at' => null,
        ]);
    });
}

try {
    /** @var Campaign $campaign */
    $campaign = Campaign::query()->findOrFail($campaignId);
    /** @var UpsertCampaignInvitationAction $action */
    $action = $app->make(UpsertCampaignInvitationAction::class);

    $result = $action->execute(new UpsertCampaignInvitationInput(
        campaign: $campaign,
        inviteeUserId: $inviteeUserId,
        inviterUserId: $inviterUserId,
        requestedRole: $requestedRole,
    ));

    echo json_encode([
        'status' => 'ok',
        'duplicate_injected' => $duplicateInjected,
        'is_new' => $result->isNew,
        'was_accepted' => $result->wasAccepted,
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

