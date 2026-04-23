# STATUS - C76-RPG (kanonische Live-Quelle)

Diese Datei ist die einzige kanonische Quelle fuer:
- aktuelle Versionslinie
- operativen Gate-Stand
- letzten dokumentierten Release-Zeitpunkt

## Operativer Live-Status

- Statusdatum: **2026-04-23**
- Produktstatus: **Beta (aktiv entwickelt)**
- Versionslinie: **`v0.30-beta`**
- Letzter Release-Eintrag: **`v0.30-beta` am 2026-04-21** (Quelle: `CHANGELOG.md`)

## Verifikations- und Gate-Stand

- Letzter dokumentierter Vollstand: **2026-04-04**
- Gesamtstatus dieses Vollstands: **gruen**
- Pflichtgates im Vollstand:
  - `php artisan test --without-tty --do-not-cache-result --exclude-group=mysql-concurrency --exclude-group=mysql-critical`
  - `php artisan test --without-tty --do-not-cache-result --group=mysql-concurrency` (CI-MySQL-Job)
  - `php artisan test --without-tty --do-not-cache-result --group=mysql-critical` (CI-MySQL-Job)
  - `node --test tests/js/*.mjs`
  - `npm run test:e2e`
  - `npm run build`
  - `composer analyse`

## Pflege-Regel

- README und ROADMAP enthalten keine Live-Statuszahlen mehr.
- Historische Release-Historie bleibt in `CHANGELOG.md`.
- Exakte Gate-Befehle bleiben in `docs/RELEASE-CHECKLISTE.md`.
