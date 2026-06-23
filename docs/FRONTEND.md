# Frontend-Konventionen

## Aktionen

Für primäre Seitenaktionen wird die Blade-Komponente `x-ui.action` verwendet.

- `href`: rendert einen Link; ohne `href` wird ein echtes `button`-Element gerendert.
- `type`: Button-Typ, standardmäßig `button`.
- `variant`: `default`, `accent`, `success` oder `danger`.
- `size`: `default`, `compact` oder `large`.
- `disabled`: deaktiviert Buttons nativ und Links über `aria-disabled` ohne Ziel-URL.
- `full-width`: setzt die Aktion auf volle verfügbare Breite.

Standardaktionen haben auf kleinen Viewports mindestens 44 × 44 Pixel. `compact` darf
erst ab dem Desktop-Breakpoint kleiner werden. Links bleiben Navigation, Buttons lösen
Aktionen aus; klickbare `div`-Elemente sind nicht zulässig.

Beispiele:

```blade
<x-ui.action :href="route('campaigns.index')" variant="accent">
    Kampagnen öffnen
</x-ui.action>

<x-ui.action type="submit" variant="danger" :disabled="$locked">
    Löschen
</x-ui.action>
```

## Progressive Bereiche

Dashboard-Bereiche mit niedrigerer Priorität verwenden native `details`/`summary`.
Wichtige nächste Aktionen und der aktive Weltkontext bleiben dauerhaft sichtbar.
Aufklappzustände werden nicht im Browser gespeichert.

## Formularvalidierung

Die Servervalidierung bleibt kanonisch. Der Charakter-Assistent prüft beim Schrittwechsel
zusätzlich native Feldregeln und die bereits vorhandenen fachlichen Regeln für Attribute,
Berufung sowie Vor-/Nachteile. Serverfehler werden am betroffenen Feld und zusätzlich in
der Fehlerübersicht ausgegeben.
