# Architecture Standards (Laravel 12)

## Core Standard
- Actions-first for mutations.
- Model-first action signatures by default.
- Mutating actions are `final`.
- Controllers stay thin: `authorize()` + action call + response.
- No controller-layer persistence or transaction/lock logic.

## Action Signatures

Bad (primitive ID bundle, weak intent):

```php
public function execute(int $worldId, int $campaignId, int $sceneId, int $userId, array $payload): void
```

Good (model-first, explicit context):

```php
public function execute(World $world, Campaign $campaign, Scene $scene, User $actor, array $payload): void
```

## Controller Boundaries

Allowed controller method shape:

```php
public function store(StoreSceneBookmarkRequest $request, World $world, Campaign $campaign, Scene $scene): RedirectResponse
{
    $this->authorize('view', $scene);

    $this->toggleSceneBookmarkAction->create(
        world: $world,
        campaign: $campaign,
        scene: $scene,
        user: $this->authenticatedUser($request),
        requestedPostId: $request->integer('post_id'),
        label: $request->string('label')->toString(),
    );

    return back()->with('status', 'Bookmark gespeichert.');
}
```

Forbidden in controllers:

```php
$user->save();
Campaign::query()->create($data);
DB::transaction(fn () => ...);
$query->lockForUpdate();
```

## Guardrail V2 (PHPStan)

`phpstan.neon.dist` registers two controller guard rules:

```neon
parameters:
    # temporary technical debt, reduce slice-by-slice
    controllerGuardrailWhitelist:
        []

services:
    -
        class: App\StaticAnalysis\PHPStan\Rules\NoDirectPersistenceInControllersRule
        arguments:
            - %currentWorkingDirectory%
            - %controllerGuardrailWhitelist%
        tags:
            - phpstan.rules.rule
    -
        class: App\StaticAnalysis\PHPStan\Rules\NoTransactionInControllersRule
        arguments:
            - %currentWorkingDirectory%
            - %controllerGuardrailWhitelist%
        tags:
            - phpstan.rules.rule
```

Policy:
- Whitelist is temporary technical debt.
- Every controller refactor slice must reduce this list.
- `composer analyse` is a mandatory CI gate.

## Migration Progress
- Migrated in this batch (Slice 1 + Slice 2):
  - `CampaignInvitationController` mutation path (`DeleteCampaignInvitationAction` model-first).
  - `PostController` mutations (`StorePostService`, `UpdatePostAction`, `DeletePostAction` model-first).
  - `EncyclopediaWorkflowController` mutations (`Store/Update/Approve/Reject` proposal actions model-first).
- Migrated in this batch (Legacy-Controller Cleanup Slice):
  - `CampaignController`
  - `EncyclopediaCategoryController`
  - `EncyclopediaEntryController`
  - `PostReactionController`
  - `AdminUserModerationController`
  - `Api/WebPushSubscriptionController`
  - `WorldAdminController`
  - `WorldSpeciesOptionAdminController`
  - `WorldCallingOptionAdminController`
  - `Auth/RegisteredUserController`
  - `Auth/NewPasswordController`
- Current whitelist size in `phpstan.neon.dist`: `0` controllers.
- Standard is now full Actions-first coverage for controller mutations.

## Concurrency Hardening Status
- Post reactions are protected by DB constraints plus idempotent create/delete actions.
- Scene bookmark toggle path is covered by transaction-based race/idempotence tests.
- WebPush upsert/delete path is covered by duplicate-key concurrency tests in `tests/Feature/MySqlConcurrency/`.
- UpdatePost mutation flow is split into focused internal responsibilities (normalization, moderation semantics, revision/update orchestration) while preserving behavior.
