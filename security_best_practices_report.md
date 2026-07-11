# Security-Review 2026-07-10

## Scope und Ergebnis

Dieser Abschlussbericht bezieht sich ausschliesslich auf die lokalen Implementierungs-Commits
`a1b490d` bis `7cfd52e` auf Basis von `d34e4de` und den damit abgeschlossenen Review. Er
behauptet weder einen erneuten repositoryweiten Audit noch einen produktiven Rollout.

Die im Diff bestaetigten Autorisierungs-, Sichtbarkeits-, Transaktions-, CSP- und
Betriebsfehler wurden korrigiert. Die lokal ausfuehrbaren Security-Audits melden danach
keine bekannten Dependency-Advisories.

## Behobene sicherheitsrelevante Befunde

- `pending`/`rejected` Posts sind fuer andere Spieler zentral ausgeblendet; Autor und
  Moderatoren behalten Zugriff. Szenen- und Mention-Benachrichtigungen werden erst nach
  Freigabe erzeugt.
- Einzel- und Bulk-Moderation sperren Datensaetze und schreiben Status, Audit und Punkte
  atomar. Externe Benachrichtigungen laufen erst nach Commit; Punktbuchungen und
  Szenen-Notifications besitzen Idempotenzschutz.
- Co-SL koennen nur neue Player-Einladungen anlegen. Rollenwechsel, privilegierte Rollen
  und angenommene Memberships bleiben Owner/Admin vorbehalten.
- Autoren koennen Posts in archivierten Szenen weder bearbeiten noch loeschen.
- Reset-Links basieren auf `APP_URL`; Trusted Hosts akzeptieren nur dessen exakten Host.
  Authentifizierte Routen validieren die Session und Passwortwechsel rotieren
  `remember_token`.
- Alpine-Formularzustand und Admin-Bestaetigungen sind ohne Inline-Handler CSP-kompatibel.
- Die PWA startet persistente Offline-Funktionen bei fehlerhafter Privacy-Boundary nicht;
  sichere Navigation und Formbedienung bleiben verfuegbar.
- Der Produktions-Preflight verlangt Production-Env, deaktiviertes Debugging, HTTPS,
  Redis fuer Queue/Cache, Secure Cookies, Trusted Proxies, positives numerisches HSTS und
  eine nichtleere Web-Push-Endpoint-Allowlist.

## Dependency-Audits

### Composer

Ausgangslage: 25 Advisories in 12 Paketen. Innerhalb der bestehenden Major-Constraints
wurden gezielt unter anderem Laravel `12.63`, Spatie Media Library `11.23.2`,
Guzzle `7.14`, JWT Framework `4.1.7` und gepatchte Symfony-Komponenten installiert.

Abschluss: `composer audit --locked` meldet **0 Advisories** und **0 abandoned packages**.
Es verbleibt daher kein bekanntes Composer-Advisory. Es wurden keine pauschalen
Major-Upgrades vorgenommen.

### npm

Ausgangslage beim aktuellen Advisory-Stand: 8 Vulnerabilities. Axios, Vite, Concurrently
und transitive Abhaengigkeiten wurden innerhalb der bestehenden Major-Linien aktualisiert.

Abschluss: `npm audit` meldet **0 Vulnerabilities**.

## Verbleibende Risiken

- Immersive Post- und Szenenbilder auf der Public-Disk sind ueber ihre direkte URL nicht
  an Post-/Szenenberechtigungen gebunden. Vertrauliche Medien duerfen dort nicht liegen.
- Eine bestehende Browser-Push-Subscription wird beim App-Logout nicht serverseitig
  widerrufen. Ein fremder Nutzer desselben Browserprofils kann weiter Browser-Push sehen,
  bis Permission/Subscription entfernt wird.
- Die Notification-Zustellung ist trotz Ledger/Retry mindestens-einmal-orientiert;
  externe Kanaele benoetigen weiterhin Monitoring und empfaengerseitige Deduplizierung.
- Medien-Dateisystem und Datenbank koennen nicht als eine gemeinsame Transaktion committen.
- Die 14 MySQL-spezifischen Critical-/Concurrency-Tests liefen lokal nicht, weil kein
  MySQL/MariaDB-Server und keine Container-Runtime verfuegbar war. Sie bleiben Release-Gate.

Details und Nacharbeiten: [`docs/KNOWN_ISSUES.md`](docs/KNOWN_ISSUES.md) und
[`docs/TECHNICAL_DEBT.md`](docs/TECHNICAL_DEBT.md).

## Abschlussverifikation

- Composer-Validierung: PASS
- PHPStan: PASS, 0 Fehler
- Architektur-Guardrails: 6 Tests / 8 Assertions, PASS
- PHP ohne MySQL-Gruppen: 1098 Tests / 6144 Assertions, PASS
- JavaScript: 43 Tests, PASS
- Chromium-E2E: 8 Tests, PASS
- Vite-Build: PASS
- Artisan-/Release-Smoke: PASS
- Pint fuer alle im Diff beruehrten PHP-Dateien: PASS

Die Review-Aenderungen sind lokal committed, aber noch nicht gepusht oder deployed.
