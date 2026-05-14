# Live Orphan Media Audit (2026-05)

## Warum dieser Audit existiert

Kampagnen wurden in der Live-App (`https://rpg.c76.org`) gelöscht.
Dabei kann es vorkommen, dass in der `media`-Tabelle Einträge verbleiben, deren referenzierte Modelle (`posts` oder `handouts`) nicht mehr existieren.

Dieser Audit liefert ein read-only Prüfwerkzeug, um solche möglichen Orphans sichtbar zu machen.

## Wichtiger Scope

- Lokale Dateien im Repository sind für die eigentliche Orphan-Frage nicht relevant.
- Die Prüfung muss später auf dem **Live-Server** laufen, wo die produktive Datenbank und produktive Storage-Daten liegen.
- Der Command ist strikt **read-only** und löscht nichts.

## Command

```bash
php artisan rpg:audit-orphan-media
php artisan rpg:audit-orphan-media --json
```

## Was geprüft wird

- DB-Orphans für `App\Models\Post`:
  - `media.model_type = App\Models\Post`
  - `LEFT JOIN posts ON media.model_id = posts.id`
  - Orphan, wenn `posts.id IS NULL`
- DB-Orphans für `App\Models\Handout`:
  - `media.model_type = App\Models\Handout`
  - `LEFT JOIN handouts ON media.model_id = handouts.id`
  - Orphan, wenn `handouts.id IS NULL`
- Pro Orphan: Metadaten + defensiver Check `physical file exists` (`yes` / `no` / `unknown`)

## Betriebshinweis bis zur Klärung

Bis zur vollständigen Klärung möglicher Orphans: Kampagnen mit Medien nicht hart löschen.

## Cleanup-Command

```bash
php artisan rpg:cleanup-orphan-media --dry-run
php artisan rpg:cleanup-orphan-media --execute
```

- Zuerst immer `--dry-run` ausführen.
- `--execute` nur ausführen, wenn die Liste aus dem Dry-Run exakt geprüft wurde.
- Der Command löscht ausschließlich erkannte Post-/Handout-Orphan-Media über Spatie Media Library.
