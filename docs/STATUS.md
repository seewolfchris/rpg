# STATUS - C76-RPG (kanonische Statusquelle)

Diese Datei ist die einzige kanonische Quelle fuer Release-, Entwicklungs-, Live- und Gate-Status.

## Statusachsen

- Statusdatum: **2026-07-26**
- Produktstatus: **Beta (kontrollierte Nutzung/Testbetrieb; weitere Aenderungen moeglich)**
- Audit-Basis dieser Datenschutz-/Technikpruefung: **lokaler Arbeitsstand auf Basis von
  `2224ff14b8b72fd7b7978d16cf369308b2c89a4e`**
- Letztes veröffentlichtes Release: **`v0.32-beta` am 2026-06-03** (Quelle: `CHANGELOG.md`)
- Entwicklungsstand (geprueft am 2026-07-26): **Branch `main`; der gepruefte Audit-Stand ist
  der Commit, der diese Datei enthaelt; seine Basis
  `2224ff14b8b72fd7b7978d16cf369308b2c89a4e` entsprach vor dem Audit `origin/main`;
  eine produktive Veroeffentlichung ist noch nicht belegt**
- Produktive Live-Instanz: **https://rpg.c76.org**
- Produktiver Live-Stand: **unbekannt / extern zu verifizieren**

Die lokale Commit-Serie ist kein veroeffentlichtes Release und darf nicht als live behauptet werden.
Ein produktiver Commit darf hier nur mit Build-Hinweis, Deployment-Protokoll oder `APP_BUILD`
eingetragen werden.

## Review-Stand 2026-07-26

In den lokalen Review-Commits wurden folgende bestaetigte Befunde behoben:

- `pending`/`rejected` Posts sind fuer andere Spieler zentral aus Thread, Pins, Sprungzielen,
  Bookmarks und Read-State ausgeblendet; Autor und Moderatoren behalten Zugriff.
- Szenen- und Mention-Benachrichtigungen entstehen erst bei Freigabe; Scene-Delivery bleibt
  per Ledger idempotent.
- Einzel- und Bulk-Moderation sperren Posts, schreiben Status, Audit und Punkte atomar und
  dispatchen externe Effekte erst nach Commit.
- Co-SL duerfen nur neue Player-Einladungen anlegen; Rollen- und angenommene
  Membership-Aenderungen bleiben Owner/Admin vorbehalten.
- Autoren duerfen Posts in archivierten Szenen nicht mehr bearbeiten oder loeschen.
- Reset-Links sind an `APP_URL` gebunden; Trusted Hosts und `auth.session` sind aktiv;
  Passwortwechsel rotieren Remember-Tokens.
- Alpine-Postformular, Admin-Confirm und PWA-Boot sind CSP-kompatibel; die Offline-Boundary
  stoppt persistente Offline-Funktionen fail-closed, ohne sichere Basisnavigation abzuschalten.
- Private Offline-Speicherung ist ein ausdrueckliches Opt-in. Der Service Worker wird nur fuer
  Offline-Funktionen oder Browser-Push registriert; ohne Opt-in werden keine privaten oder
  statischen Offline-Caches aufgebaut.
- Queue- und Dead-Letter-Datensaetze sind einer Auth-, User- und Welt-Boundary zugeordnet.
  Ausschalten, Logout und Kontowechsel bereinigen Caches, IndexedDB, lokale Entwuerfe und
  sitzungsbezogenen Browserzustand.
- Browser-Push besitzt geraeteweise Verwaltung; Logout und Entfernen widerrufen die aktuelle
  Browser-Subscription und bereinigen die serverseitige Zuordnung.
- Produktions-Deploy verlangt Production-Env, Debug off, HTTPS, Redis, Secure Cookie,
  Trusted Proxies, positives HSTS und eine Web-Push-Endpoint-Allowlist.
- Composer- und npm-Abhaengigkeiten wurden innerhalb bestehender Major-Constraints auf
  advisory-freie Versionen aktualisiert.

Offene Bugs und technische Schulden sind kanonisch in
[`KNOWN_ISSUES.md`](KNOWN_ISSUES.md) und [`TECHNICAL_DEBT.md`](TECHNICAL_DEBT.md) erfasst.

## Verifikations- und Gate-Stand

Vollstaendiger lokaler Abschlusslauf am **2026-07-26**:

- `composer validate --strict`: **PASS**
- `composer analyse`: **PASS, 0 Fehler**
- Architekturpruefungen: **8 Tests, 11 Assertions, PASS**
- PHP ohne MySQL-Gruppen: **1103 Tests, 6169 Assertions, PASS**
- JavaScript: **49 Tests, PASS**
- Chromium-E2E: **8 Tests, PASS**
- `npm run build`: **PASS** (Vite 7.3.6)
- `composer audit --locked`: **0 Advisories, 0 abandoned packages**
- `npm audit`: **0 Vulnerabilities**
- Artisan-Smoke: **PASS**
- Pint fuer alle im Diff beruehrten PHP-Dateien: **PASS**

Nicht lokal ausgefuehrt:

- Gruppe `mysql-concurrency`: **9 Tests**
- Gruppe `mysql-critical`: **5 Tests**
- Grund: PHP besitzt `pdo_mysql`/`mysqli`, aber lokal laufen weder MySQL/MariaDB auf Port 3306
  noch Docker/Podman oder ein MySQL-Client. Diese 14 Tests bleiben vor Release ein verbindliches
  CI-/MySQL-Gate.

- Status- und Config-Drift: **PASS** (Config-Check ohne Warnungen)
- Produktions-Build: **aktuell und erfolgreich erzeugt**
- `git diff --check`: **PASS**

## Pflege-Regel

- `docs/STATUS.md` bleibt kanonisch fuer Release-, Entwicklungs-, Live-, Build- und Gate-Stand.
- README und Roadmaps duerfen nur knapp orientieren und muessen hierher verweisen.
- Historische Releases bleiben in `CHANGELOG.md`.
- Exakte Gate-Befehle bleiben in `docs/RELEASE-CHECKLISTE.md`.
