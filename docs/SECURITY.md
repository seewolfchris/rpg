# Security (Technischer Ueberblick)

## Security-Baseline
- Security-Header werden zentral ueber `App\Http\Middleware\ApplySecurityHeaders` gesetzt.
- Privacy-first Defaults fuer private HTML-Responses (`no-store/private`).
- Auth-/Policy-basierte Zugriffskontrolle auf sensible Produktbereiche.
- Trusted Hosts sind auf den exakten Host aus `APP_URL` begrenzt; Passwort-Reset-Links
  verwenden ebenfalls diese kanonische Basis-URL.
- Authentifizierte Routen nutzen Session-Authentizitaetspruefung; Passwortwechsel
  widerrufen bestehende Remember-Tokens.
- Nicht freigegebene Posts folgen einer zentralen Sichtbarkeitsregel: Autor und
  Moderatoren duerfen sie sehen, andere Teilnehmer nicht.
- Live-Instanz: https://rpg.c76.org; Release-, Entwicklungs-, Live- und Build-Status: `STATUS.md`.
- Das Projekt ist proprietaer; diese technische Security-Doku ist keine Nutzungslizenz.

## Medien-Privacy-Grenze
- Immersive Bilder in Spielleitungsbeitraegen (`immersive_images`) und Szenen-Inhaltsbilder (`scene_content_images`) nutzen bewusst die Public-Disk.
- Direkte Datei-URLs dieser Collections sind unabhaengig von Post- oder Szenenberechtigungen erreichbar.
- Diese Grenze gilt fuer atmosphaerische Inline-Bilder, nicht fuer vertrauliche Handouts.
- Vertrauliche oder berechtigungsabhaengige Medien muessen ueber kontrollierte Handouts bzw. autorisierte Auslieferung laufen.

## Crawler / Bot-Schutz
- `public/robots.txt` sperrt Crawling (`Disallow: /`).
- `X-Robots-Tag` wird serverseitig gesetzt.
- Meta-Tags `robots`, `googlebot`, `bingbot` sind auf noindex.
- Bekannte Search-/KI-Bot-User-Agents koennen mit `403` geblockt werden.

## Rate Limiting (mutierende Routen)
- `writes`: 30 Requests/Minute je Nutzer/IP
- `moderation`: 15 Requests/Minute je Nutzer/IP
- `notifications`: 20 Requests/Minute je Nutzer/IP
- `webpush-subscriptions`: 20 Requests/Minute je Nutzer/IP und Welt

## PWA / Offline Security
- Offline-Speicherung ist Opt-in und standardmaessig deaktiviert.
- Offline-Queue speichert keine sensiblen Formkeys.
- Queue akzeptiert nur Same-Origin-`POST`.
- Queue-/Dead-Letter-Datensaetze tragen eine explizite Auth-, User- und Weltzuordnung.
- Auth-Boundary-/Logout-/Opt-out-Cleanup loescht alle verwalteten Caches, Queue-Daten,
  lokale Post-Entwuerfe und sitzungsbezogene UI-Werte.
- HTMX-History und -History-Cache sind deaktiviert.
- Schlaegt die Boundary-Initialisierung fehl, bleiben Service Worker, Offline-Queue und
  Browser-Push fail-closed; nichtpersistente Basisnavigation bleibt bedienbar.
- Details: [PWA_OFFLINE.md](PWA_OFFLINE.md)

## Production- und Dependency-Gates
- Der Deploy-Preflight verlangt Production-Env, `APP_DEBUG=false`, HTTPS, Redis fuer
  Queue/Cache, Secure Cookies, Trusted Proxies, positives HSTS und eine explizite
  `WEBPUSH_ENDPOINT_ALLOWED_HOSTS`-Allowlist.
- Abschlussstand 2026-07-10: `composer audit --locked` und `npm audit` melden jeweils
  keine bekannten Advisories/Vulnerabilities.
- Vollstaendiger Review: [security_best_practices_report.md](../security_best_practices_report.md)

## Bekannte Restrisiken
- Public-Disk-Medien besitzen keine route-basierte Autorisierung.
- MySQL-spezifische Concurrency-/Critical-Gates muessen in CI oder einer lokalen
  MySQL/MariaDB-Umgebung laufen.
- Kanonische Listen: [KNOWN_ISSUES.md](KNOWN_ISSUES.md) und
  [TECHNICAL_DEBT.md](TECHNICAL_DEBT.md).

## Disclosure
- Fuer Schwachstellenmeldungen siehe root [SECURITY.md](../SECURITY.md).
