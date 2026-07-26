# Bekannte Probleme

Stand: 2026-07-26. Diese Liste enthaelt bestaetigte, noch nicht sicher klein behebbare
Produkt- oder Betriebsprobleme. Security-Details stehen zusaetzlich in `docs/SECURITY.md`.

## KI-001: Spaet freigegebener aelterer Post kann als gelesen gelten

- **Schweregrad:** Mittel
- **Auswirkung:** Wird ein Post mit kleinerer ID erst freigegeben, nachdem ein Nutzer bereits
  einen spaeteren Post gelesen hat, kann der ID-basierte Lese-Cursor den neu sichtbaren Post
  nicht als ungelesen markieren. Die Freigabe-Benachrichtigung und der direkte Link werden
  dennoch erzeugt.
- **Naechster Schritt:** Read-State um eine Publikationssequenz oder einen per Post gefuehrten
  Gelesen-Status erweitern; nicht durch Ruecksetzen des monotonen Cursors kaschieren.

## KI-003: Charakter-Editor ist ohne JavaScript nur eingeschraenkt nutzbar

- **Schweregrad:** Niedrig bis Mittel
- **Auswirkung:** Die mobile Navigation hat einen No-JS-Fallback, der mehrstufige
  Charakterbogen-Editor setzt fuer wesentliche Interaktionen weiterhin JavaScript voraus.
- **Naechster Schritt:** Serverseitig validierbare Einzelschritt-Formulare bzw. eine vollstaendige
  progressive Fallback-Ansicht bereitstellen.

## KI-004: Direkte Public-Disk-URLs umgehen Inhaltsberechtigungen

- **Schweregrad:** Mittel bei Fehlklassifikation vertraulicher Dateien
- **Auswirkung:** Immersive Post-Bilder und Szenen-Inhaltsbilder sind ueber direkte Datei-URLs
  unabhaengig von Post-/Szenenrechten erreichbar.
- **Workaround:** Vertrauliche Dateien ausschliesslich als autorisierte Handouts ausliefern.
- **Naechster Schritt:** Private Disk plus autorisierte Download-Route fuer vertrauliche Medien.
