# ADR 2026-04-24: Delete-/Audit-/Retention-Semantik für v1

## Status
Accepted

Amended: 2026-06-04

Implementation status: Partially implemented

## Kontext
Der urspruengliche v1-Ist-Zustand nutzte keine SoftDeletes. Dieser Stand ist ueberholt.

Aktueller Ist-Stand am 2026-06-04:
- Posts nutzen `SoftDeletes` und speichern `deleted_by`.
- Normaler Post-Delete tombstoned den Post; Revisionen und Moderationslogs bleiben nicht mehr durch den normalen Post-Delete still per FK-Cascade verloren.
- Szenen werden weiterhin hart geloescht. Dadurch verschwinden szenengebundene Posts, Dice Rolls, Subscriptions, Bookmarks, Handouts, Story-Log-Eintraege, Player Notes, Kampfphasen und Konfliktakteure gemaess FK-Regeln.
- Kampagnen werden weiterhin hart geloescht. `DeleteCampaignAction` bereinigt vorher Medien fuer Handouts, immersive Post-Bilder, Szenen-Inhaltsbilder und Szenen-Header; DB-Cascades entfernen Szenen, Invitations, Memberships, RoleEvents, SL-Kontakt-Threads und Messages.

Invitations bleiben vorerst Hard Delete, weil Revocation-Audit über `campaign_role_events` existiert. Private SL-Kontakt-Threads brauchen eine separate Privacy-/Retention-Entscheidung.

## Entscheidung
Es gibt keinen globalen SoftDelete-Umbau.

Post-Loeschung ist als SoftDelete/Tombstone umgesetzt. Szenen und Kampagnen bleiben vorerst Hard Delete und muessen bei kuenftigen Aenderungen explizit in Richtung Archivierung, Tombstone oder SoftDelete entschieden werden. Endgueltiges Loeschen bleibt als separater Admin-/Privacy-Vorgang moeglich.

Moderationslogs und Revisionen sollen künftig bei normaler Löschung nicht still verschwinden. FK-Cascade darf künftig nicht mehr als implizite Produktentscheidung für Audit-/Retention-Verlust gelten.

## Konsequenzen
Post-Loeschung ist audit-staerker als der urspruengliche Hard-Delete-Stand. Szenen- und Kampagnenloeschung bleiben privacy-freundlich, aber audit-schwach.

Künftige PRs müssen Löschsemantik explizit entscheiden. Offene Entscheidungen bleiben: Szenen/Kampagnen, Medien-Retention, Revisionen/Moderationslogs bei ForceDelete, private SL-Kontakte separat.
