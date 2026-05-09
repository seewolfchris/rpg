# C76-RPG Konsolidierungsplan 2026 - PR-01 Audit Baseline

## 1. Executive Summary

Ziel fuer 2026 ist Vereinfachung und Stabilisierung in kleinen, reversiblen Schritten. Dieser Plan beschreibt nur Analyse, Inventar, Risikoeinschaetzung und PR-Reihenfolge.

PR-01 ist absichtlich rein analytisch:
- Es wird nur diese Audit-Datei neu angelegt.
- Keine Runtime-Aenderungen.
- Keine Test-Aenderungen.
- Keine Script- oder CI-Gate-Aenderungen.
- Keine Refactorings und keine Loeschungen.

Nicht-Ziele in PR-01:
- Keine Feature-Expansion.
- Keine Konsolidierung im Action-Layer.
- Keine Rollen-Semantik-Aenderung im laufenden Verhalten.
- Keine Docker-/Compose-Einfuehrung.

## 2. Methodik der Inventarisierung

### 2.1 Datenquellen
- Codebasis: `app/`, `routes/`, `config/`, `scripts/`
- Tests: `tests/Feature/MySqlConcurrency`, `tests/Feature/MySqlCritical`, `tests/e2e`, `tests/js`
- Doku/Runbooks: `README.md`, `docs/README.md`, `docs/ARCHITECTURE.md`, `docs/OPERATIONS_RUNBOOK.md`, `docs/RELEASE-CHECKLISTE.md`
- CI: `.github/workflows/ci.yml`
- Env-Beispiele: `.env.example`, `.env.plesk.example`

### 2.2 Reproduzierbare Kommandos

```bash
# Action-Inventar und Cluster
find app/Actions -type f -name '*Action.php' | wc -l
find app/Actions -type f -name '*Action.php' \
  | sed 's#^app/Actions/##' | cut -d/ -f1 | sort | uniq -c | sort -nr

# Rollen-/Legacy-Begriffe (Treffer + Dateianzahl)
for p in 'isGmOrAdmin\(' 'isGm\(' 'gmOrAdmin' 'global_gm' 'UserRole::GM' \
         'co_gm' 'CampaignMembershipRole::GM' 'trusted_player' 'owner_id'; do
  rg -n --hidden --glob '!.git' "$p"
  rg -l --hidden --glob '!.git' "$p" | wc -l
done

# Testflaechen fuer Concurrency/Critical/E2E/JS
find tests/Feature/MySqlConcurrency -type f | sort
find tests/Feature/MySqlCritical -type f | sort
rg --files tests/e2e | rg 'offline|auth-boundary|webpush|push|queue|retry'
rg --files tests/js | rg 'offline|queue|push|privacy|dead-letter|sw'

# Heuristische Action-Metriken (Transaktion/Lock/Side-Effects/Events/Caller/Testrefs)
# Auswertung ueber rg-Marker pro Action-Datei, aggregiert in TSV.
```

### 2.3 Ableitung der Kennzahlen
- Action-Gesamtzahl: `93`
- Cluster: Scene 10, Post 10, Encyclopedia 10, Campaign 9, Character 8, WorldCharacterOptions 8, SceneSubscription 6, StoryLog 5, Notification 5, Handout 5, CampaignGmContact 5, World 4, PlayerNote 3, Auth 2, Knowledge 1, Dev 1, Admin 1.
- Heuristische Marker ueber alle 93 Actions:
- `66` mit Transaktionsmarker
- `39` mit Lock-Marker
- `62` mit direkter Testreferenz
- `31` ohne direkte Testreferenz
- `13` Low-Complexity-Kandidaten (nur heuristisch, keine Refactoring-Freigabe)

### 2.4 Datenqualitaet (False Positives / False Negatives)
Moegliche False Positives:
- `rg`-Treffer in Kommentaren, Doku, Fixtures oder Testnamen statt Runtime-Pfaden.
- Klassennamen-Treffer koennen nicht immer echte Aufrufer darstellen (Import ohne Nutzung).
- Semantikbegriffe wie `owner_id` sind breit gestreut und nicht automatisch Rollenlogik.

Moegliche False Negatives:
- Indirekte Aufrufe ueber Container/Factory/Resolver ohne direkten Klassennamen.
- Dynamische Methodenauflosung und String-basierte Dispatch-Pfade.
- Verhaltensrelevanz, die nur zur Laufzeit durch Policy/Scope/Request-Flow sichtbar wird.

## 3. Befund je Themenbereich

### 3.1 Action-Layer
Ist-Zustand:
- Hohe Action-Dichte (`93` Klassen), davon viele kleine Wrapper.
- Gleichzeitig zahlreiche sensible Pfade mit Transaktion/Lock/Concurrency-Relevanz.
- Bereits vorhandene MySQL-Concurrency-Tests fuer kritische Race-Pfade.

Konsolidierungskandidaten (spaeter, nach Charakterisierung):
- StoryLog: Reveal/Unreveal/Delete als kleiner Pilot-Slice.
- Handout: Reveal/Unreveal/Delete als alternative Pilotflaeche.
- SceneSubscription: MarkUnread/Subscribe/Unsubscribe als zweiter Pilot.

No-Go fuer fruehe Refactors:
- Invitation/Membership-Upsert- und Response-Pfade mit Concurrency-Absicherung.
- WebPush Subscription Upsert/Delete und Delivery-Retry-nahe Pfade.
- World- und Post-Update-Pfade mit bestehender MySQL-Critical/Concurrency-Abdeckung.

### 3.2 Rollenmodell / Legacy-Semantik
Ist-Zustand:
- Plattformrollen: `App\\Enums\\UserRole` mit `PLAYER`, `ADMIN`.
- Kampagnenrollen: `App\\Enums\\CampaignMembershipRole` mit `PLAYER`, `TRUSTED_PLAYER`, `GM`.
- Legacy-Bruecke: `User::isGmOrAdmin()` existiert und mappt aktuell effektiv auf Admin-Semantik.

Einordnung:
- Rolle und Berechtigung sind bereits in Policy-/Access-Komponenten verteilt.
- Legacy-Begriffe (`global_gm`, `co_gm`, alte Migrationen) sind teils historisch, teils noch runtime-relevant.

Deprecation-then-remove Pfad (spaeter):
- PR-02 markiert Legacy-Methode im Warnmodus und dokumentiert Ersatzpfad.
- Removal erst nach Stabilitaetsfenster und Nachweis ohne Runtime-Nutzung (PR-08).

### 3.3 Entwickler-Onboarding / EN Summary
Ist-Zustand:
- Kanonische Projektdoku ist ueberwiegend deutschsprachig.
- Internationale Contributor erhalten keinen kompakten Einstieg mit Source-of-Truth-Verweisen.

Plan:
- `docs/en/QUICKSTART.md`
- `docs/en/ARCHITECTURE_SUMMARY.md`
- `docs/en/OPERATIONS_SUMMARY.md`
- `docs/en/RELEASE_FLOW_SUMMARY.md`

Prinzipien:
- Nur Summary, keine zweite Wahrheit.
- Jede EN-Datei verlinkt auf kanonische DE/Originalquelle.
- Jede EN-Datei fuehrt `Last synced commit: <sha>`.

### 3.4 Edge-Case-Testlandschaft
Ist-Zustand:
- Bereits vorhanden: MySQL-Concurrency- und Critical-Suites plus E2E/JS fuer Offline/Auth-Boundary.
- Noch ausbaubar: Characterization fuer StoryLog/Handout-Flows vor Action-Konsolidierung.

Zielmatrix fuer Ausbau:
- Campaign-Membership-Races
- Invitation Accept/Decline/Revocation/Role Update
- Post-Update/Moderation/Pin/Revision
- PWA Offline Queue
- Auth-Boundary waehrend Offline-Retry
- WebPush Multi-World Subscriptions
- Duplicate Delivery / Retry

### 3.5 Konfigurations-Drift
Ist-Zustand:
- Konfigurationsquellen verteilt ueber `config/*`, Env-Beispiele, Runbook, Release-Checklist, CI, Scripts.
- Relevante Unterschiede lokal vs production in Queue/Cache/Session/Filesystem.

Kritische Schluessel:
- `APP_ENV`, `APP_DEBUG`, `APP_URL`
- `DB_CONNECTION`
- `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER`, `QUEUE_AFTER_COMMIT`
- `REDIS_*`
- `TRUSTED_PROXIES`
- `FILESYSTEM_DISK`
- Security-/Proxy-/PWA-/WebPush-relevante Werte

Plan:
- PR-03 fuehrt nur Warnmodus-Drift-Check ein (keine Hard-Gates).

### 3.6 Polyglot-Stack / Optionaler Compose-Goldpfad
Ist-Zustand:
- Native Entwicklung ist funktional und bleibt first-class.
- Kein verpflichtender Compose-Pfad fuer Produktivbetrieb.

Plan:
- Compose nur optional, erst nach Config-Konsolidierung.
- Moegliche Services spaeter: app/php, mysql oder mariadb, redis, optional mailpit, optional node.

## 4. Korrigierte PR-Reihenfolge

1. PR-01 Audit Baseline
2. PR-02 Rollenmodell Deprecation/Warnmodus
3. PR-03 Config-Drift Warnmodus
4. PR-04 EN Summary Bootstrap
5. PR-05 StoryLog/Handout Characterization Tests
6. PR-06 Pilot Slice A: StoryLog Reveal/Unreveal/Delete
7. PR-07 Pilot Slice B: SceneSubscription oder Handout
8. PR-08 Rollenmodell Removal nach Stabilitaetsfenster
9. PR-09 Edge-Case Matrix Ausbau
10. PR-10 Optional Compose Goldpfad

## 5. No-Go-Bereiche (verbindlich)

- Keine Aenderungen an HTTP-Routen.
- Keine Aenderungen an URIs.
- Keine Aenderungen an Middleware-Vertraegen.
- Keine Aenderungen an Response-Contracts.
- Keine Aenderungen an Policies.
- Keine Runtime-Aenderung in PR-01.

## 6. Pilot-Voraussetzungen (verbindlich vor jedem Action-Pilot)

Vor jedem Action-Pilot muessen Charakterisierungstests vorhanden sein fuer:
- Berechtigung
- Sichtbarkeit
- Welt-/Kampagnenkontext
- Redirect/Back-Navigation
- 403/404-Verhalten
- HTMX/Response-Vertrag, sofern betroffen

## 7. Risikoanalyse

High-Risk:
- Membership/Invitation-Concurrency, WebPush-Subscription-Pfade, World/Post-Kernupdates.
- Aufwand: hoch (2-4 PR-Zyklen je Bereich inkl. Charakterisierung und Nachbeobachtung).

Medium-Risk:
- Action-Konsolidierung in kleinen StoryLog/Handout/SceneSubscription-Slices.
- Aufwand: mittel (1-2 kleine PRs pro Slice bei stabilen Charakterisierungstests).

Low-Risk:
- Audit, Doku-Summaries, Warnmodus-Checks ohne Gate.
- Aufwand: niedrig (kleine additive PRs ohne Runtime-Semantikwechsel).

## 8. Betroffene Dateien/Ordner

- `app/Actions/**`
- `app/Models/User.php`
- `app/Enums/UserRole.php`
- `app/Enums/CampaignMembershipRole.php`
- `app/Domain/Campaign/**`
- `tests/Feature/MySqlConcurrency/**`
- `tests/Feature/MySqlCritical/**`
- `tests/e2e/**`
- `tests/js/**`
- `config/**`
- `.env.example`
- `.env.plesk.example`
- `phpunit.xml`
- `.github/workflows/ci.yml`
- `scripts/**`
- `docs/ARCHITECTURE.md`
- `docs/OPERATIONS_RUNBOOK.md`
- `docs/RELEASE-CHECKLISTE.md`
- `README.md`

## 9. Offene Fragen

- Soll PR-02 Warnmodus primair ueber Runtime-Deprecation-Logging oder zunaechst nur ueber Doku/Static-Hinweise laufen?
- Welche minimale Characterization-Suite wird fuer PR-05 als Merge-Kriterium akzeptiert (nur Feature oder zusaetzlich E2E-Smokefall)?
- Welche Drift-Keys sind in PR-03 verpflichtend, welche nur informativ?
- Soll `docs/en/*` in PR-04 bereits eine PR-Template-Checkbox enthalten oder erst in Folge-PR?

## 10. Teststrategie (inkrementell)

PR-Gate:
- Bestehende schnelle Unit/Feature-Suiten unveraendert.
- Keine neuen Hard-Gates in PR-01 bis PR-04.

MySQL-only:
- Bestehende Concurrency/Critical-Suiten als gezielte Validierung fuer betroffene Slices.

E2E:
- Offline/Auth-Boundary und Queue-Retry-Flows beibehalten, spaeter matrixorientiert erweitern.

Nightly/Heavy:
- Race- und Retry-Stresstests schrittweise ausbauen, erst nach stabilen Pilot-Slices.

## 11. Empfohlene Pilot-Slices

Pilot Slice A (PR-06):
- StoryLog Reveal/Unreveal/Delete.
- Begruendung: begrenzte Flaeche, gute Charakterisierbarkeit, niedrigeres seiteneffekt-Risiko.

Pilot Slice B (PR-07):
- SceneSubscription oder alternativ Handout.
- Begruendung: ebenfalls begrenzte API-Flaeche, gute Vergleichbarkeit zum ersten Pilot.

## 12. Klare Empfehlung fuer die naechsten 3 kleinsten PRs

1. PR-01 Audit Baseline
- Nur `docs/audits/2026-05-consolidation-plan.md`.
- Kein Runtime-/Test-/Script-/CI-Verhalten wird geaendert.

2. PR-02 Rollenmodell Deprecation/Warnmodus
- Deprecation-Pfad fuer `User::isGmOrAdmin()` dokumentieren und Warnmodus vorbereiten.
- Kein Semantikwechsel in Policies, Routen oder Responses.

3. PR-03 Config-Drift Warnmodus
- Drift-Check im Warnmodus (nicht blockierend), fokussiert auf kritische Schluessel.
- Keine neuen CI-Hard-Gates.
