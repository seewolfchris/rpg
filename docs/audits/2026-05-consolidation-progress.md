# C76-RPG Konsolidierung 2026 - PR-09 Zwischenbilanz

## Ziel und Scope

Diese Datei dokumentiert ausschliesslich den Fortschritt nach den abgeschlossenen Pilot-Slices.
Sie ersetzt nicht die PR-01 Audit-Baseline in `docs/audits/2026-05-consolidation-plan.md`.

Hinweis zu Live-Status:
- Keine Live-Statusdaten in dieser Datei duplizieren.
- Fuer aktuellen Status (Version, Release, Gates) gilt `docs/STATUS.md` als Source of Truth.

## Abgeschlossene Schritte

- PR-05a StoryLog Characterization Tests
- PR-06 StoryLog Mutation-Service-Pilot
- PR-07a SceneSubscription Characterization Tests
- PR-07b SceneSubscription Mutation-Service-Pilot
- PR-08a Handout Characterization Tests
- PR-08b Handout Mutation-Service-Pilot

## Validiertes Konsolidierungsmuster

- Charakterisierungstests zuerst.
- Domain-Service-Konsolidierung danach.
- Actions bleiben Adapter.
- Controller/Routes/Policies/Views bleiben unveraendert.
- Oeffentliche HTTP-Vertraege bleiben unveraendert.
- Fokussierte Tests plus `composer analyse` sind Pflicht.

## Weiterhin ausgeschlossene Hochrisiko-Bereiche

- Campaign Invitation / Membership Lifecycle
- Post Store/Update/Moderation Hotpath
- WebPush Subscription/Delivery
- World-Invarianten
- Handout Store/Update/Media-Replacement
- BulkUpdateSceneSubscriptionsAction
- Media Streaming / Storage Authorization

## Empfehlung fuer naechste Schritte

- Keine Adapter-Loeschung unmittelbar nach den Piloten.
- Kein Store/Update/Media-Refactor ohne eigene Charakterisierung.
- Naechste technische Slices nur nach erneuter Testvorbereitung.
- Alternativ Edge-Case-Matrix oder Config-Drift-Warncheck weiter ausbauen.

## PR-Nummerierung ab dieser Zwischenbilanz

- Diese Zwischenbilanz ist PR-09.
- Edge-Case-Matrix-Ausbau ist Folge-Slice PR-10.
- Optionaler Compose-Goldpfad ist Folge-Slice PR-11.

## Pflichtlauf pro Slice

Pflichtlauf pro Slice:
  - Zielgerichtete Feature-Tests fuer den betroffenen Bereich.
  - Bereichssuite (z. B. `php artisan test --filter=<Domain>`).
  - `composer analyse`.
