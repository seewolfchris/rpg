# A3 Invarianten-Report

Stand: 2026-03-31  
Quelle: `tests/Feature/AuthorizationWorldContextMutationMatrixTest.php`

## 1) `campaigns.store`
- Erwarteter Statuscode: `302` fuer `GM`/`Admin`, `403` fuer `Co-GM`/`Player`/`Outsider`.
- Mutationswirkung: bei `302` wird eine Kampagne mit `owner_id = actor` und `world_id = Routenwelt` angelegt; bei `403` keine Anlage.
- Welt-Guard: inaktive Welt ist `404`.

## 2) `campaigns.update`
- Erwarteter Statuscode: `302` fuer `Owner`/`Co-GM`/`Admin`/`fremder GM`, `403` fuer `Player`/`Outsider`.
- Mutationswirkung: bei `302` werden `title`/`slug`/Metadaten aktualisiert; bei `403` bleibt Kampagne unveraendert.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Co-GM-Negativfaelle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 3) `campaigns.destroy`
- Erwarteter Statuscode: `302` fuer `Owner`/`Admin`/`fremder GM`, `403` fuer `Co-GM`/`Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Kampagne geloescht; bei `403` bleibt sie bestehen.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Co-GM-Negativfaelle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 4) `campaigns.invitations.store`
- Erwarteter Statuscode: `302` fuer `Owner`/`Admin`/`fremder GM`, `403` fuer `Co-GM`/`Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Invitation mit erwarteter Rolle/Status angelegt; bei `403` keine Invitation.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.

## 5) `campaigns.invitations.destroy`
- Erwarteter Statuscode: `302` fuer `Owner`/`Admin`/`fremder GM`, `403` fuer `Co-GM`/`Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Invitation geloescht; bei `403` bleibt sie erhalten.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.

## 6) `campaigns.scenes.store`
- Erwarteter Statuscode: `302` fuer `Owner`/`Co-GM`/`Admin`, `403` fuer `Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Szene angelegt; bei `403` keine Szene.
- Welt-Guard: inaktive Welt ist `404`.

## 7) `campaigns.scenes.update`
- Erwarteter Statuscode: `302` fuer `Owner`/`Co-GM`/`Admin`, `403` fuer `Player`/`Outsider`.
- Mutationswirkung: bei `302` werden Szenenfelder aktualisiert; bei `403` unveraendert.
- Welt-Guard: implizit ueber Welt-/Kampagnenbindung abgesichert.
- Co-GM-Negativfaelle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 8) `campaigns.scenes.destroy`
- Erwarteter Statuscode: `302` fuer `Owner`/`Co-GM`/`Admin`, `403` fuer `Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Szene geloescht; bei `403` bleibt sie bestehen.
- Welt-Guard: implizit ueber Welt-/Kampagnenbindung abgesichert.
- Co-GM-Negativfaelle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 9) `campaigns.scenes.posts.store`
- Erwarteter Statuscode: `302` fuer `Owner`/`Co-GM`/`Admin`/`Player`, `403` fuer `Outsider`.
- Mutationswirkung: bei `302` wird Post erzeugt; bei `403` kein Post.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Co-GM-Negativfaelle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 10) `posts.update`
- Erwarteter Statuscode: `302` fuer `Owner`/`Co-GM`/`Admin`/`Autor`, `403` fuer `Outsider`.
- Mutationswirkung: bei `302` wird `content` aktualisiert; bei `403` unveraendert.
- Welt-Guard: ueber Weltkontext-Check im Controller.
- Co-GM-Negativfaelle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 11) `posts.destroy`
- Erwarteter Statuscode: `302` fuer `Owner`/`Co-GM`/`Admin`/`Autor`, `403` fuer `Outsider`.
- Mutationswirkung: bei `302` wird Post geloescht; bei `403` bleibt Post.
- Welt-Guard: ueber Weltkontext-Check im Controller.
- Co-GM-Negativfaelle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 12) `posts.moderate` (klassischer Request)
- Erwarteter Statuscode: `302` fuer `Owner`/`Co-GM`/`Admin`, `403` fuer `Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Moderationsstatus und Approval-Feld gesetzt; bei `403` unveraendert.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.

## 13) `posts.moderate` (`HX-Request=true`)
- Erwarteter Statuscode: `200` fuer `Owner`/`Co-GM`/`Admin`, `403` fuer `Player`/`Outsider`.
- Mutationswirkung: bei `200` Moderationsmutation wie oben.
- HTMX-Response-Grenze: bei `HX-Target=post-*` kommt Fragment (`200`), bei anderem `HX-Target` Redirect (`302`).
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Co-GM-Negativfaelle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 14) `posts.pin` und `posts.unpin` (`HX-Request=true`)
- Erwarteter Statuscode: `200` fuer `Owner`/`Co-GM`/`Admin`, `403` fuer `Player`/`Outsider`.
- Mutationswirkung: `pin` setzt `is_pinned=true` und `pinned_by`; `unpin` setzt `is_pinned=false` und leert `pinned_by`.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Co-GM-Negativfaelle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 15) `gm.progression.award-xp`
- Erwarteter Statuscode: `302` fuer `Owner`/`Co-GM`/`Admin`, `403` fuer `Player`/`Outsider`.
- Mutationswirkung: bei erfolgreichem Pfad werden XP/Event-Records geschrieben; bei Forbidden kein Write.
- Welt-Guard: inaktive Welt ist `404`; falsche aktive Welt fuehrt ueber Validierung zu Redirect mit Fehler auf `campaign_id`.
- Ownership-Guard: Nicht-Teilnehmercharakter fuehrt zu Redirect mit Fehler auf `awards.*.character_id`.

## 16) `campaigns.scenes.inventory-quick-action`
- Erwarteter Statuscode: `302` fuer alle Rollen, aber Mutation nur fuer `Owner`/`Co-GM`/`Admin`; `Player`/`Outsider` bekommen Redirect mit Validierungsfehler.
- Mutationswirkung: erfolgreiche Rollen erzeugen Inventarmutation + Audit-Log; unberechtigte Rollen ohne Mutation.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Ownership-Guard: Nicht-Teilnehmercharakter fuehrt zu Validierungsfehler.

## 17) `scene-subscriptions.bulk-update`
- Erwarteter Statuscode: `302` fuer authentifizierte Rollen (`Owner`/`Co-GM`/`Admin`/`Player`/`Outsider`).
- Mutationswirkung: Bulk wirkt nur auf eigene, in der gewaehlten Welt sichtbare Subscriptions.
- Welt-Guard: inaktive Welt ist `404`.
- Weltisolation: aktive Fremdwelt mutiert nur Datensaetze dieser Fremdwelt (keine Cross-World-Mutation).

## 18) `gm.moderation.bulk-update` (klassischer Request)
- Erwarteter Statuscode: `302` fuer `Owner`/`Co-GM`/`Admin`, `403` fuer `Player`/`Outsider`.
- Mutationswirkung: bei Erfolg Bulk-Moderation auf erlaubte Posts; bei `403` keine Mutation.
- Welt-Guard: inaktive Welt ist `404`; unzulaessige Post-ID ausserhalb Scope fuehrt zu `403`.
- Co-GM-Negativfaelle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 19) `gm.moderation.bulk-update` (`HX-Request=true`)
- Erwarteter Statuscode: `200` fuer `Owner`/`Co-GM`/`Admin` bei gesetztem `scene_id`, sonst `403` fuer `Player`/`Outsider`.
- Mutationswirkung: identisch zum klassischen Pfad auf erlaubte Posts.
- HTMX-Response-Grenze: mit `scene_id` kommt Thread-Fragment (`200`), ohne `scene_id` Redirect (`302`).
- Welt-Guard: falsche aktive Welt ist `404` (bei `scene_id`-Mismatches), inaktive Welt ist `404`.
- Co-GM-Negativfaelle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 20) `characters.inline-update`
- Erwarteter Statuscode: `302` fuer `Owner`/`GM`/`Admin`, `403` fuer `Outsider`.
- Mutationswirkung: bei `302` werden Character-Felder aktualisiert; bei `403` keine Mutation.
- Welt-Guard: nicht an Weltroute gebunden, daher kein Welt-Slug-Guard an dieser Route.
