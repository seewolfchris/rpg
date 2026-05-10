# C76-RPG Edge-Case Test Matrix Plan (PR-10)

## 1. Executive Summary

Nach den erfolgreichen Low-Risk-Piloten (StoryLog, SceneSubscription, Handout Reveal/Unreveal/Delete) ist die naechste Prioritaet die gezielte Edge-Case-Abdeckung in den verbleibenden Hochrisikobereichen.

Grundsatz:
- Kein Refactoring in Hochrisikobereichen ohne eigene Charakterisierung.
- Diese Datei ist ein Testplan, kein Runtime- oder Architekturumbau.

Live-Status-Hinweis:
- Keine Live-Statusdaten hier duplizieren.
- Fuer aktuellen Release-/Gate-/Versionsstatus gilt `docs/STATUS.md` als Source of Truth.

## 2. P0/P1-priorisierte Risikomatrix

Hinweis:
- Diese Matrix ist im aktuellen Stand bewusst P0/P1-priorisiert.
- P2-Slices werden nach Stabilisierung der P0/P1-Abdeckung nachgezogen.

| Bereich | Prioritaet | Primaere Risikoarten | Primaere Testtier |
|---|---|---|---|
| Campaign Invitation / Membership Lifecycle | P0 | Race Condition, Lost Update, Authorization Leak, Retry/Idempotency | PR-Gate + MySQL-only |
| Post Store/Update/Moderation Hotpath | P0 | Lost Update, Authorization Leak, Retry/Idempotency, Duplicate Delivery | PR-Gate + MySQL-only |
| WebPush Subscription/Delivery | P0 | Duplicate Delivery, Retry/Idempotency, Cross-World Leak | PR-Gate + MySQL-only |
| PWA Offline Queue / Auth Boundary | P1 | Retry/Idempotency, Authorization Leak, Lost Update | E2E + JS + Nightly/Heavy |
| Handout Store/Update/Media Replacement | P1 | Media/Storage Consistency, Lost Update, Authorization Leak | PR-Gate + MySQL-only |
| World-Invarianten | P0 | Race Condition, Cross-World Leak, Lost Update | PR-Gate + MySQL-only |
| BulkUpdateSceneSubscriptionsAction | P1 | Authorization Leak, Cross-World/Cross-Campaign Leak, Lost Update | PR-Gate + MySQL-only |

## 3. Bereichsdetails

### 3.1 Campaign Invitation / Membership Lifecycle

Aktueller Zweck:
- Einladungen erstellen/aktualisieren, annehmen/ablehnen, Membership synchron halten, Rollenwechsel konsistent protokollieren.

Relevante Actions/Services/Controller/Models:
- Actions: `UpsertCampaignInvitationAction`, `RespondToCampaignInvitationAction`, `SyncCampaignMembershipFromInvitationAction`, `UpdateCampaignMembershipRoleAction`, `DeleteCampaignInvitationAction`
- Service/Domain: `CampaignAccess`
- Controller: `CampaignInvitationController`, `CampaignMembershipController`
- Models: `CampaignInvitation`, `CampaignMembership`, `Campaign`, `CampaignRoleEvent`

Bestehende Tests:
- `tests/Feature/CampaignAccessInvitationTest.php`
- `tests/Feature/CampaignInvitationRegisteredUsersTest.php`
- `tests/Feature/CampaignMembershipReadSwitchTest.php`
- `tests/Feature/CampaignMembershipManagementTest.php`
- `tests/Feature/PostInvitationRevocationAuthorizationTest.php`
- `tests/Feature/MySqlConcurrency/CampaignInvitationDuplicateKeyMysqlTest.php`
- `tests/Feature/MySqlConcurrency/InvitationResponseParallelMysqlTest.php`
- `tests/Feature/MySqlCritical/InvitationUpsertMysqlCriticalTest.php`
- `tests/Feature/MySqlCritical/PostInvitationRevocationMysqlCriticalTest.php`

Bekannte Risikoarten:
- Race Condition
- Authorization Leak
- Cross-World/Cross-Campaign Leak
- Lost Update
- Retry/Idempotency

Vorgeschlagene neue Tests:
- `CampaignInvitationLifecycleCharacterizationTest` (accept/decline/revoke/role-change Matrix mit stabilen 403/404-Semantiken).
- `CampaignInvitationAcceptVsRevokeParallelMysqlTest` (gleichzeitiges Accept + Revocation).
- `CampaignMembershipRoleConvergenceMysqlCriticalTest` (mehrstufige Rollenwechsel ohne divergierende Endzustaende).

Testtier:
- PR-Gate + MySQL-only

Prioritaet:
- P0

Nicht-Ziele:
- Keine Aenderung an Einladungs- oder Membership-Semantik.
- Keine Runtime-Konsolidierung in diesem Slice.

### 3.2 Post Store/Update/Moderation Hotpath

Aktueller Zweck:
- Beitrag erstellen/aktualisieren inkl. Moderationsstatus, Revisionen, Reaktionen, Notifications und Retry-Pfad.

Relevante Actions/Services/Controller/Models:
- Actions: `UpdatePostAction`, `DeletePostAction`, `ApplyPostModerationTransitionAction`, `BulkModeratePostsAction`, `CreatePostReactionAction`, `DeletePostReactionAction`
- Services: `StorePostService`, `PostModerationService`, `PostNotificationOrchestrator`, `ScenePostNotificationService`, `ScenePostNotificationDeliveryLedger`
- Controller: `PostController`, `PostReactionController`
- Jobs: `RetryScenePostNotificationsJob`
- Models: `Post`, `PostRevision`, `PostModerationLog`, `PostReaction`, `PostSceneNotificationDelivery`

Bestehende Tests:
- `tests/Feature/CampaignScenePostWorkflowTest.php`
- `tests/Feature/PostModerationDefaultBehaviorTest.php`
- `tests/Feature/PostModerationAuditTest.php`
- `tests/Feature/PostRevisionHistoryTest.php`
- `tests/Feature/PostSceneNotificationIdempotencyTest.php`
- `tests/Feature/PostUpdateNotificationFailureTest.php`
- `tests/Feature/MySqlConcurrency/PostReactionDuplicateKeyMysqlTest.php`
- `tests/Unit/Actions/Post/UpdatePostActionTest.php`
- `tests/Unit/Jobs/Post/RetryScenePostNotificationsJobTest.php`

Bekannte Risikoarten:
- Lost Update
- Authorization Leak
- Duplicate Delivery
- Retry/Idempotency
- Race Condition

Vorgeschlagene neue Tests:
- `PostUpdateModerationInterleaveCharacterizationTest` (gleichzeitige inhaltliche Updates vs. Moderationswechsel).
- `PostNotificationRetryDuplicateSuppressionMysqlTest` (Retry + teilweise erfolgreiche Zustellung ohne doppelte Delivery).
- `PostBulkModerationScopeLeakCharacterizationTest` (Batch-Mutation mit harten Scope-Grenzen).

Testtier:
- PR-Gate + MySQL-only

Prioritaet:
- P0

Nicht-Ziele:
- Kein Refactor von `StorePostService`/`UpdatePostAction`.
- Keine neue Moderationslogik.

### 3.3 WebPush Subscription/Delivery

Aktueller Zweck:
- World-gebundene WebPush-Subscriptions upsert/delete, Post-Notifications zustellen und ueber Retry absichern.

Relevante Actions/Services/Controller/Models:
- Actions: `UpsertWebPushSubscriptionAction`, `DeleteWebPushSubscriptionAction`
- Controller: `Api/WebPushSubscriptionController`
- Services/Jobs: `ScenePostNotificationService`, `PostNotificationOrchestrator`, `RetryScenePostNotificationsJob`
- Models: `PushSubscription`, `PostSceneNotificationDelivery`, `User`, `World`

Bestehende Tests:
- `tests/Feature/WebPushSubscriptionControllerTest.php`
- `tests/Feature/WebPushDispatchTest.php`
- `tests/Feature/MySqlConcurrency/WebPushSubscriptionDuplicateKeyMysqlTest.php`
- `tests/Unit/Actions/Notification/WebPushSubscriptionActionsTest.php`
- `tests/Unit/Jobs/Post/RetryScenePostNotificationsJobTest.php`

Bekannte Risikoarten:
- Duplicate Delivery
- Retry/Idempotency
- Cross-World/Cross-Campaign Leak
- Authorization Leak

Vorgeschlagene neue Tests:
- `WebPushSubscriptionEndpointReassignmentCharacterizationTest` (gleiches Endpoint zwischen Usern/Welten).
- `WebPushUnsubscribeVsRetryMysqlTest` (Unsubscribe waehrend Retry-Fenster).
- `WebPushDeliveryLedgerIdempotencyMysqlCriticalTest` (Mehrfachretry ohne doppelte Zustellung).

Testtier:
- PR-Gate + MySQL-only

Prioritaet:
- P0

Nicht-Ziele:
- Keine Aenderung an WebPush-Provider/Queue-Konfiguration.
- Keine neuen CI-Hard-Gates.

### 3.4 PWA Offline Queue / Auth Boundary

Aktueller Zweck:
- Offline-Queue fuer Post-Submissions mit Auth-Boundary, Dead-Letter-UI und Wiederanlauf bei Reconnect.

Relevante Actions/Services/Controller/Models:
- Frontend Runtime: `resources/js/immersion/queue.js`, `resources/js/offline-dead-letter.mjs`, `resources/js/app/service-worker-runtime.js`, `public/sw.js`
- Boundary/Preference: `resources/js/app/privacy-boundary.js`, `NotificationController`, `UpdateNotificationPreferencesRequest`
- Model: `User.offline_queue_enabled`

Bestehende Tests:
- `tests/e2e/offline-auth-boundary.spec.mjs`
- `tests/e2e/offline-queue-retry.spec.mjs`
- `tests/js/sw.offline-queue.test.mjs`
- `tests/js/offline.dead-letter-ui.test.mjs`
- `tests/Feature/AuthUserBoundaryMetaTest.php`

Bekannte Risikoarten:
- Retry/Idempotency
- Authorization Leak
- Lost Update
- Duplicate Delivery

Vorgeschlagene neue Tests:
- `offline-queue-session-rotation.spec.mjs` (Queue-Eintraege ueber Auth-Wechsel/Boundary-Reset).
- `offline-queue-multi-tab-replay.spec.mjs` (Doppelversand durch mehrere Tabs verhindern).
- `sw.offline-queue.auth-boundary.test.mjs` (Service-Worker-Queue bei Boundary-Wechsel sauber invalidieren).

Testtier:
- E2E + Nightly/Heavy (JS-Layer teilweise PR-Gate)

Prioritaet:
- P1

Nicht-Ziele:
- Kein Umbau des Service-Worker-Runtimes.
- Keine neue Offline-Produktlogik.

### 3.5 Handout Store/Update/Media Replacement

Aktueller Zweck:
- Handouts erstellen/aktualisieren inkl. Datei-Upload, Primärdatei-Ersatz und kontrollierter Dateiauslieferung.

Relevante Actions/Services/Controller/Models:
- Actions: `StoreHandoutAction`, `UpdateHandoutAction`
- Services: `HandoutMediaService`
- Controller: `HandoutController`
- Models: `Handout` (+ Media-Beziehung)

Bestehende Tests:
- `tests/Feature/HandoutManagementTest.php`
- `tests/Feature/HandoutVisibilityTest.php`
- `tests/Feature/HandoutBackNavigationTest.php`
- `tests/Feature/SceneHandoutPanelTest.php`

Bekannte Risikoarten:
- Media/Storage Consistency
- Lost Update
- Authorization Leak
- Cross-World/Cross-Campaign Leak

Vorgeschlagene neue Tests:
- `HandoutStoreUpdateCharacterizationTest` (Store/Update-End-to-End mit Fehlerpfad-Konsistenz).
- `HandoutMediaReplacementParallelMysqlTest` (parallel replacement, single primary file invariant).
- `HandoutFileAuthorizationAfterReplacementTest` (Dateizugriff nach Replace + Reveal/Unreveal Zustand).

Testtier:
- PR-Gate + MySQL-only

Prioritaet:
- P1

Nicht-Ziele:
- Keine Runtime-Konsolidierung von Store/Update.
- Kein Storage-/Streaming-Refactor.

### 3.6 World-Invarianten

Aktueller Zweck:
- Welt-Konfiguration konsistent halten (Default-Welt-Schutz, Aktivierungsinvarianten, Kontextgrenzen).

Relevante Actions/Services/Controller/Models:
- Actions: `UpdateWorldAction`, `CreateWorldAction`, `DeleteWorldAction`, `ReorderWorldsAction`
- Controller: `WorldAdminController`, `WorldController`
- Middleware/Context: `ApplyWorldContext`, `EnsuresWorldContext`
- Models: `World`, `Campaign`

Bestehende Tests:
- `tests/Feature/MySqlCritical/WorldInvariantsMysqlCriticalTest.php`
- `tests/Feature/MySqlConcurrency/WorldUpdateToggleParallelMysqlTest.php`
- `tests/Feature/WorldAdminUpdateInvariantTest.php`
- `tests/Feature/WorldActivationTest.php`
- `tests/Feature/WorldContextActivationGuardTest.php`

Bekannte Risikoarten:
- Race Condition
- Cross-World/Cross-Campaign Leak
- Lost Update
- Authorization Leak

Vorgeschlagene neue Tests:
- `WorldDefaultSlugProtectionCharacterizationTest` (kein Drift bei Default-Slug/Inaktivierung).
- `WorldToggleVsDeleteParallelMysqlTest` (parallel toggle/delete invariant).
- `WorldContextFallbackConsistencyTest` (Context-Wechsel ohne Leaks in Mutationspfaden).

Testtier:
- PR-Gate + MySQL-only

Prioritaet:
- P0

Nicht-Ziele:
- Kein Umbau der World-Konfigurationssemantik.
- Kein Routing-/Middleware-Refactor.

### 3.7 BulkUpdateSceneSubscriptionsAction

Aktueller Zweck:
- Sichtbarkeitsgebundene Massenmutationen fuer Scene-Subscriptions (mute/unmute/unfollow nach Filter/Scope).

Relevante Actions/Services/Controller/Models:
- Action: `BulkUpdateSceneSubscriptionsAction`
- Controller: `SceneSubscriptionController::bulkUpdate`
- Request: `BulkUpdateSceneSubscriptionRequest`
- Models/Scope: `SceneSubscription`, `Campaign` (`visibleTo`)

Bestehende Tests:
- `tests/Unit/Actions/SceneSubscription/BulkUpdateSceneSubscriptionsActionTest.php`
- `tests/Feature/SceneSubscriptionDashboardTest.php`

Bekannte Risikoarten:
- Authorization Leak
- Cross-World/Cross-Campaign Leak
- Lost Update
- Retry/Idempotency

Vorgeschlagene neue Tests:
- `SceneSubscriptionBulkUpdateCharacterizationTest` (alle Bulk-Action-Codes inkl. Filterkombi).
- `SceneSubscriptionBulkUpdateScopeLeakMysqlTest` (nur sichtbare Campaigns werden mutiert).
- `SceneSubscriptionBulkUpdateConcurrentMutationsMysqlTest` (parallel mute/unfollow ohne Drift).

Testtier:
- PR-Gate + MySQL-only

Prioritaet:
- P1

Nicht-Ziele:
- Kein Domain-Service-Refactor fuer Bulk-Action.
- Keine Erweiterung der Bulk-Feature-Semantik.

## 4. Konkrete Empfehlung fuer die ersten drei Test-Slices

1. Slice A (P0): Campaign Invitation / Membership Lifecycle
- Fokus: Accept/Decline/Revocation/Role-Update Interleavings.
- Warum zuerst: Hoher Berechtigungs- und Race-Einfluss auf Kernzugriff.
- Umsetzung: PR-Gate Charakterisierung + gezielte MySQL-Concurrency-Faelle.
- Erster Umsetzungsschnitt: nur Campaign Invitation/Membership Characterization im PR-Gate.
- MySQL-Concurrency-Faelle folgen als separater Slice.

2. Slice B (P0): Post Store/Update/Moderation Hotpath
- Fokus: Lost-Update, Moderation-Interleave, Notification-Retry-Idempotenz.
- Warum zweitens: Hoechste Mutationsfrequenz und Nebenwirkungstiefe.
- Umsetzung: Kleine PR-Gate-Tests zuerst, schwere Interleavings als MySQL-only.
- Post Store/Update/Moderation und Notification-Retry duerfen nicht in einem einzigen PR gebuendelt werden.
- Zuerst Moderation/Update-Characterization, danach Notification-Retry/Idempotency separat.

3. Slice C (P1): BulkUpdateSceneSubscriptionsAction
- Fokus: Scope-/Filter-Konsistenz unter Bulk-Mutationen.
- Warum drittens: Begrenzte Flaeche, hohe Leckage-Relevanz, gut isolierbar.
- Umsetzung: Feature-Charakterisierung plus 1-2 MySQL-Concurrency-Faelle.

## 5. Guardrails

- Keine Refactors in Hochrisikobereichen ohne vorherige Charakterisierung.
- Keine neuen CI-Hard-Gates ohne Warn-/Report-Phase.
- Keine Testflakiness in PR-Gates akzeptieren.
- Heavy/Stress-Tests zunaechst nur nightly/report-only.
