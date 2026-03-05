# Release-Checkliste (Chroniken der Asche)

Ziel: Jeder Release laeuft gleich ab, ohne Raten und ohne vergessene Schritte.

## 1. Lokal vorbereiten

- `git pull --rebase origin main`
- Alle geplanten Aenderungen finalisieren.
- Sicherstellen, dass `APP_VERSION` fuer den Release feststeht (z. B. `v0.13-beta`).

## 2. Qualitaet lokal pruefen

- Tests:
  - `php artisan test`
- Frontend-Build:
  - `npm run build`

Nur wenn beides gruen ist, weiter.

## 3. Version aktualisieren

- Laufende lokale Instanz:
  - `.env`: `APP_VERSION=vX.XX-beta`
- Repo-Vorlage fuer neue Umgebungen:
  - `.env.example`: `APP_VERSION=vX.XX-beta`
- Optional:
  - `.env`: `APP_BUILD=<kurzer commit hash>`

Beispiel fuer `APP_BUILD`:

```bash
git rev-parse --short HEAD
```

## 4. Commit und Push

```bash
git status
git add -A
git commit -m "release: vX.XX-beta"
git push origin main
```

## 5. Deploy auf Plesk

Wenn Git-Webhook aktiv ist: Deployment startet automatisch.  
Wenn manuell noetig: In Plesk Git auf `Bereitstellen` klicken.

Post-Deploy muss mit PHP 8.5 laufen:

```bash
cd /var/www/vhosts/c76.org/rpg.c76.org
PHP_BIN=/opt/plesk/php/8.5/bin/php /bin/bash scripts/plesk_post_deploy.sh
```

## 6. Smoke-Test nach Deploy (5 Minuten)

- Login/Logout funktioniert.
- Dashboard laedt.
- Charakter-Erstellung laedt ohne JS-Fehler.
- GM-Post mit Probe funktioniert (inkl. LE/AE-Update am Zielcharakter).
- Footer zeigt korrekte Version (`Build: vX.XX-beta`).

## 7. Dokumentation aktualisieren

- `docs/PROJEKT-ÜBERSICHT.md` auf aktuellen Stand bringen:
  - Release-Stand
  - wichtige Aenderungen
  - offene Prioritaeten

## 8. Kurzprotokoll (empfohlen)

Fuer jeden Release einmal notieren:
- Version
- Commit-Hash
- Zeitpunkt Deploy
- Ergebnis Smoke-Test
- offene Nacharbeiten
