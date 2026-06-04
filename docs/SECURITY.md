# Security (Technischer Ueberblick)

## Security-Baseline
- Security-Header werden zentral ueber `App\Http\Middleware\ApplySecurityHeaders` gesetzt.
- Privacy-first Defaults fuer private HTML-Responses (`no-store/private`).
- Auth-/Policy-basierte Zugriffskontrolle auf sensible Produktbereiche.
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
- Offline-Queue speichert keine sensiblen Formkeys.
- Queue akzeptiert nur Same-Origin-`POST`.
- Auth-Boundary-/Logout-Cleanup loescht private Caches + Queue-Daten.
- Details: [PWA_OFFLINE.md](PWA_OFFLINE.md)

## Disclosure
- Fuer Schwachstellenmeldungen siehe root [SECURITY.md](../SECURITY.md).
