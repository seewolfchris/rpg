# A3 Invarianten-Report

Stand: 2026-04-02  
Quelle:
- `tests/Feature/AuthorizationWorldContext/AuthorizationWorldContextMutationTestCase.php`
- `tests/Feature/AuthorizationWorldContext/AuthorizationWorldContextMutationCoreTest.php`
- `tests/Feature/AuthorizationWorldContext/AuthorizationWorldContextMutationScopeTest.php`
- `tests/Feature/AuthorizationWorldContext/AuthorizationWorldContextMutationCrudTest.php`
- `tests/Feature/AuthorizationWorldContext/AuthorizationWorldContextMutationHxTest.php`

## 1) `campaigns.store`
- Erwarteter Statuscode: `302` für `GM`/`Admin`, `403` für `Co-GM`/`Player`/`Outsider`.
- Mutationswirkung: bei `302` wird eine Kampagne mit `owner_id = actor` und `world_id = Routenwelt` angelegt; bei `403` keine Anlage.
- Welt-Guard: inaktive Welt ist `404`.

## 2) `campaigns.update`
- Erwarteter Statuscode: `302` für `Owner`/`Co-GM`/`Admin`/`fremder GM`, `403` für `Player`/`Outsider`.
- Mutationswirkung: bei `302` werden `title`/`slug`/Metadaten aktualisiert; bei `403` bleibt Kampagne unverändert.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Co-GM-Negativfälle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 3) `campaigns.destroy`
- Erwarteter Statuscode: `302` für `Owner`/`Admin`/`fremder GM`, `403` für `Co-GM`/`Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Kampagne gelöscht; bei `403` bleibt sie bestehen.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Co-GM-Negativfälle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 4) `campaigns.invitations.store`
- Erwarteter Statuscode: `302` für `Owner`/`Admin`/`fremder GM`, `403` für `Co-GM`/`Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Invitation mit erwarteter Rolle/Status angelegt; bei `403` keine Invitation.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.

## 5) `campaigns.invitations.destroy`
- Erwarteter Statuscode: `302` für `Owner`/`Admin`/`fremder GM`, `403` für `Co-GM`/`Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Invitation gelöscht; bei `403` bleibt sie erhalten.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.

## 6) `campaigns.scenes.store`
- Erwarteter Statuscode: `302` für `Owner`/`Co-GM`/`Admin`, `403` für `Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Szene angelegt; bei `403` keine Szene.
- Welt-Guard: inaktive Welt ist `404`.

## 7) `campaigns.scenes.update`
- Erwarteter Statuscode: `302` für `Owner`/`Co-GM`/`Admin`, `403` für `Player`/`Outsider`.
- Mutationswirkung: bei `302` werden Szenenfelder aktualisiert; bei `403` unverändert.
- Welt-Guard: implizit über Welt-/Kampagnenbindung abgesichert.
- Co-GM-Negativfälle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 8) `campaigns.scenes.destroy`
- Erwarteter Statuscode: `302` für `Owner`/`Co-GM`/`Admin`, `403` für `Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Szene gelöscht; bei `403` bleibt sie bestehen.
- Welt-Guard: implizit über Welt-/Kampagnenbindung abgesichert.
- Co-GM-Negativfälle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 9) `campaigns.scenes.posts.store`
- Erwarteter Statuscode: `302` für `Owner`/`Co-GM`/`Admin`/`Player`, `403` für `Outsider`.
- Mutationswirkung: bei `302` wird Post erzeugt; bei `403` kein Post.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Co-GM-Negativfälle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 10) `posts.update`
- Erwarteter Statuscode: `302` für `Owner`/`Co-GM`/`Admin`/`Autor`, `403` für `Outsider`.
- Mutationswirkung: bei `302` wird `content` aktualisiert; bei `403` unverändert.
- Welt-Guard: über Weltkontext-Check im Controller.
- Co-GM-Negativfälle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 11) `posts.destroy`
- Erwarteter Statuscode: `302` für `Owner`/`Co-GM`/`Admin`/`Autor`, `403` für `Outsider`.
- Mutationswirkung: bei `302` wird Post gelöscht; bei `403` bleibt Post.
- Welt-Guard: über Weltkontext-Check im Controller.
- Co-GM-Negativfälle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 12) `posts.moderate` (klassischer Request)
- Erwarteter Statuscode: `302` für `Owner`/`Co-GM`/`Admin`, `403` für `Player`/`Outsider`.
- Mutationswirkung: bei `302` wird Moderationsstatus und Approval-Feld gesetzt; bei `403` unverändert.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.

## 13) `posts.moderate` (`HX-Request=true`)
- Erwarteter Statuscode: `200` für `Owner`/`Co-GM`/`Admin`, `403` für `Player`/`Outsider`.
- Mutationswirkung: bei `200` Moderationsmutation wie oben.
- HTMX-Response-Grenze: bei `HX-Target=post-*` kommt Fragment (`200`), bei anderem `HX-Target` Redirect (`302`).
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Co-GM-Negativfälle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 14) `posts.pin` und `posts.unpin` (`HX-Request=true`)
- Erwarteter Statuscode: `200` für `Owner`/`Co-GM`/`Admin`, `403` für `Player`/`Outsider`.
- Mutationswirkung: `pin` setzt `is_pinned=true` und `pinned_by`; `unpin` setzt `is_pinned=false` und leert `pinned_by`.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Co-GM-Negativfälle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 15) `gm.progression.award-xp`
- Erwarteter Statuscode: `302` für `Owner`/`Co-GM`/`Admin`, `403` für `Player`/`Outsider`.
- Mutationswirkung: bei erfolgreichem Pfad werden XP/Event-Records geschrieben; bei Forbidden kein Write.
- Welt-Guard: inaktive Welt ist `404`; falsche aktive Welt führt über Validierung zu Redirect mit Fehler auf `campaign_id`.
- Ownership-Guard: Nicht-Teilnehmercharakter führt zu Redirect mit Fehler auf `awards.*.character_id`.

## 16) `campaigns.scenes.inventory-quick-action`
- Erwarteter Statuscode: `302` für alle Rollen, aber Mutation nur für `Owner`/`Co-GM`/`Admin`; `Player`/`Outsider` bekommen Redirect mit Validierungsfehler.
- Mutationswirkung: erfolgreiche Rollen erzeugen Inventarmutation + Audit-Log; unberechtigte Rollen ohne Mutation.
- Welt-Guard: falsche aktive Welt ist `404`, inaktive Welt ist `404`.
- Ownership-Guard: Nicht-Teilnehmercharakter führt zu Validierungsfehler.

## 17) `scene-subscriptions.bulk-update`
- Erwarteter Statuscode: `302` für authentifizierte Rollen (`Owner`/`Co-GM`/`Admin`/`Player`/`Outsider`).
- Mutationswirkung: Bulk wirkt nur auf eigene, in der gewählten Welt sichtbare Subscriptions.
- Welt-Guard: inaktive Welt ist `404`.
- Weltisolation: aktive Fremdwelt mutiert nur Datensätze dieser Fremdwelt (keine Cross-World-Mutation).

## 18) `gm.moderation.bulk-update` (klassischer Request)
- Erwarteter Statuscode: `302` für `Owner`/`Co-GM`/`Admin`, `403` für `Player`/`Outsider`.
- Mutationswirkung: bei Erfolg Bulk-Moderation auf erlaubte Posts; bei `403` keine Mutation.
- Welt-Guard: inaktive Welt ist `404`; unzulässige Post-ID außerhalb Scope führt zu `403`.
- Co-GM-Negativfälle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 19) `gm.moderation.bulk-update` (`HX-Request=true`)
- Erwarteter Statuscode: `200` für `Owner`/`Co-GM`/`Admin` bei gesetztem `scene_id`, sonst `403` für `Player`/`Outsider`.
- Mutationswirkung: identisch zum klassischen Pfad auf erlaubte Posts.
- HTMX-Response-Grenze: mit `scene_id` kommt Thread-Fragment (`200`), ohne `scene_id` Redirect (`302`).
- Welt-Guard: falsche aktive Welt ist `404` (bei `scene_id`-Mismatches), inaktive Welt ist `404`.
- Co-GM-Negativfälle: fremde Kampagne in gleicher Welt `403`, Fremdwelt `403`.

## 20) `characters.inline-update`
- Erwarteter Statuscode: `302` für `Owner`/`GM`/`Admin`, `403` für `Outsider`.
- Mutationswirkung: bei `302` werden Character-Felder aktualisiert; bei `403` keine Mutation.
- Welt-Guard: nicht an Weltroute gebunden, daher kein Welt-Slug-Guard an dieser Route.
