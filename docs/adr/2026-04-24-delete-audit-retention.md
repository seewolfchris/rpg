# ADR 2026-04-24: Delete-/Audit-/Retention-Semantik für v1

## Status
Accepted

## Kontext
Der aktuelle v1-Ist-Zustand nutzt keine SoftDeletes. Posts, Szenen und Kampagnen werden hart gelöscht.

Beim Post-Delete verschwinden per Cascade auch `post_revisions` und `post_moderation_logs`. Szenen- und Kampagnen-Delete löschen indirekt Story-, Audit- und Kontaktkontext. Kampagnen-Delete entfernt zusätzlich Invitations, Memberships, RoleEvents, SL-Kontakt-Threads und Messages.

Invitations bleiben vorerst Hard Delete, weil Revocation-Audit über `campaign_role_events` existiert. Private SL-Kontakt-Threads brauchen eine separate Privacy-/Retention-Entscheidung.

## Entscheidung
Es gibt jetzt keinen globalen SoftDelete-Umbau.

Normaler Delete von Posts, Szenen und Kampagnen soll perspektivisch Richtung Archivierung, Tombstone oder SoftDelete entwickelt werden. Endgültiges Löschen bleibt als separater Admin-/Privacy-Vorgang möglich.

Moderationslogs und Revisionen sollen künftig bei normaler Löschung nicht still verschwinden. FK-Cascade darf künftig nicht mehr als implizite Produktentscheidung für Audit-/Retention-Verlust gelten.

## Konsequenzen
Kurzfristig bleibt Hard Delete als Ist-Zustand bestehen. Das ist privacy-freundlich, aber audit-schwach.

Künftige PRs müssen Löschsemantik explizit entscheiden. Die technische Umsetzung folgt separat: zuerst Post-Löschung, danach Szenen/Kampagnen, private SL-Kontakte separat.
