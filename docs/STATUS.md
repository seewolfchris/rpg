# STATUS - C76-RPG (kanonische Statusquelle)

Diese Datei ist die einzige kanonische Quelle fuer Release-, Entwicklungs-, Live- und Gate-Status.

## Statusachsen

- Statusdatum: **2026-07-11**
- Produktstatus: **Beta (kontrollierte Nutzung/Testbetrieb; weitere Aenderungen moeglich)**
- Audit-Basis dieser Dokumentationspruefung: **Implementierungs-Commits `a1b490d` bis `7cfd52e` auf Basis von `d34e4de`**
- Letztes veröffentlichtes Release: **`v0.32-beta` am 2026-06-03** (Quelle: `CHANGELOG.md`)
- Entwicklungsstand (geprueft am 2026-07-11): **Branch `main`; Commit `1b29d20043b403599acf127dd7d9fd7f1889d9f7` entspricht `origin/main`**
- Produktive Live-Instanz: **https://rpg.c76.org**
- Produktiver Live-Stand: **unbekannt / extern zu verifizieren**

Die lokale Commit-Serie ist kein veroeffentlichtes Release und darf nicht als live behauptet werden.
Ein produktiver Commit darf hier nur mit Build-Hinweis, Deployment-Protokoll oder `APP_BUILD`
eingetragen werden.

## Review-Stand 2026-07-11

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
- Produktions-Deploy verlangt Production-Env, Debug off, HTTPS, Redis, Secure Cookie,
  Trusted Proxies, positives HSTS und eine Web-Push-Endpoint-Allowlist.
- Composer- und npm-Abhaengigkeiten wurden innerhalb bestehender Major-Constraints auf
  advisory-freie Versionen aktualisiert.

Offene Bugs und technische Schulden sind kanonisch in
[`KNOWN_ISSUES.md`](KNOWN_ISSUES.md) und [`TECHNICAL_DEBT.md`](TECHNICAL_DEBT.md) erfasst.

## Verifikations- und Gate-Stand

Vollstaendiger lokaler Abschlusslauf am **2026-07-10**:

- `composer validate --strict`: **PASS**
- `composer analyse`: **PASS, 0 Fehler**
- Architektur-Guardrails: **6 Tests, 8 Assertions, PASS**
- PHP ohne MySQL-Gruppen: **1098 Tests, 6144 Assertions, PASS**
- JavaScript: **43 Tests, PASS**
- Chromium-E2E: **8 Tests, PASS**
- `npm run build`: **PASS** (Vite 7.3.6)
- `composer audit --locked`: **0 Advisories, 0 abandoned packages**
- `npm audit`: **0 Vulnerabilities**
- Artisan-Smoke: **PASS**
- Pint fuer alle im Diff beruehrten PHP-Dateien: **PASS**
- `scripts/release_prepare.sh --version v0.33-beta --build audit --dry-run`: **PASS**, keine Dateiaenderung

Nicht lokal ausgefuehrt:

- Gruppe `mysql-concurrency`: **9 Tests**
- Gruppe `mysql-critical`: **5 Tests**
- Grund: PHP besitzt `pdo_mysql`/`mysqli`, aber lokal laufen weder MySQL/MariaDB auf Port 3306
  noch Docker/Podman oder ein MySQL-Client. Diese 14 Tests bleiben vor Release ein verbindliches
  CI-/MySQL-Gate.

- Status- und Config-Drift: **PASS** (Config-Check ohne Warnungen)
- Finaler Build-Artefakt-Status nach erneutem Vite-Build: **stabil, PASS**
- `git diff --check`: **PASS**

## Pflege-Regel

- `docs/STATUS.md` bleibt kanonisch fuer Release-, Entwicklungs-, Live-, Build- und Gate-Stand.
- README und Roadmaps duerfen nur knapp orientieren und muessen hierher verweisen.
- Historische Releases bleiben in `CHANGELOG.md`.
- Exakte Gate-Befehle bleiben in `docs/RELEASE-CHECKLISTE.md`.
