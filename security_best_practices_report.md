# C76-RPG Security Audit 2026 (Laravel 12 / PHP 8.5)

Datum: 2026-04-10  
Scope: kompletter Workspace (`app/`, `config/`, `routes/`, `resources/`, `database/`, `public/`, `tests/`, `composer.json`, `package.json`, `.env.example`)  
Methodik: statischer Code-/Config-Audit + Dependency-Audit (`composer audit`, `npm audit`)  

## Executive Summary

Keine direkte unauthenticated RCE im Laravel-Code gefunden.  
Die kritischsten Risiken liegen in der **Frontend-Supply-Chain (npm advisories)** und in der **Offline/PWA-Caching-Strategie für private Inhalte**.

## Fix-Status (2026-04-10, nach Fix-Run)

- F-01 bis F-06: **behoben** im Workspace (Dependency-Upgrade, SW-/Header-Hardening, Request-Id-Sanitization, Dotfile-Blockade, World-Scope-/Policy-/Upload-/Preview-/Gate-Hardening).
- R-01 (Test-Regression auf neues Queue-Sicherheitsmodell): **behoben** in `tests/js/sw.offline-queue.test.mjs`.
- Verifikation:
  - `php artisan test` -> **377 passed, 7 skipped**
  - `node --test tests/js/*.mjs` -> **19 passed**

### Top-Funde (priorisiert)

1. **[F-01]** Verwundbare Frontend-Abhängigkeiten (`axios`, `vite`) mit bekannten Advisories (`npm audit`): High bis Critical je nach Deploy-/Dev-Exposure.
2. **[F-02]** Service-Worker cached private HTML-Seiten (Szenen/Charaktere) zu aggressiv; keine harte `no-store`-Barriere -> Datenrest-Risiko auf Shared Devices.
3. **[F-03]** Sicherheitsheader werden nur für Laravel-Responses gesetzt; statische Dateien (`offline.html`, SW/JS, Manifest) sind nicht gleichwertig gehärtet.
4. **[F-04]** `X-Request-Id` wird ungefiltert übernommen/gespiegelt -> schwache Log-Integrität/Trace-Kontamination.
5. **[F-05]** Dotfiles im Public-Webroot (`.php-ini`, `.php-version`, `.DS_Store`) -> unnötige Info-Disclosure bei fehlender Server-Dotfile-Blockade.

---

## Findings

## Critical

### [F-01] Verwundbare NPM-Abhängigkeiten (Supply Chain)
- **Datei/Zeilen:** `package.json:19`, `package.json:24`
- **Betroffen:** `axios@1.13.6`, `vite@7.3.1`
- **Audit-Evidenz:** `npm audit --json`
  - Axios: `GHSA-3p68-rc4w-qgx5`
  - Vite: `GHSA-4w7w-66w2-5vf9`, `GHSA-v2wj-q39q-566r`, `GHSA-p9ff-h696-f583`
- **Schweregrad:** **High** (in manchen Betriebsmodi Critical)
- **CVSS-4.0 Begründung:** Netzwerk-angreifbar in relevanten Betriebsmodi (insb. exposed Dev-Server/Build-Umgebungen); Integritäts-/Vertraulichkeitsauswirkung möglich.
- **Angriffsvektor / PoC:**
  - Wenn Vite-Devserver extern erreichbar ist, sind advisory-beschriebene File-Read/BYPASS-Szenarien möglich.
  - Axios-Advisory ist v. a. Node-seitig relevant; bei zukünftiger SSR/Node-Nutzung akut.
- **Warum 2026 nicht akzeptabel:** Ungepatchte bekannte Advisories im Build/Delivery-Stack sind unter aktuellem Supply-Chain-Standard nicht release-fähig.
- **Patch (minimal):**

```diff
*** Begin Patch
*** Update File: package.json
@@
-        "axios": "1.13.6",
+        "axios": "1.15.0",
@@
-        "vite": "7.3.1"
+        "vite": "7.3.2"
*** End Patch
```

Zusätzlich:
- `npm install`
- `npm audit --json`
- CI-Gate: `npm audit --audit-level=high`

---

## High

### [F-02] Private Seiten werden offline aggressiv gecached (PWA Privacy Leak Surface)
- **Datei/Zeilen:**
  - `public/js/sw/runtime-core.js:358-363` (private Offline-Readable Pfade)
  - `public/js/sw/runtime-core.js:472-540` (`networkFirst` + `shouldCache`)
  - `public/js/sw/runtime-core.js:538-540` (`shouldCache` cached jede 200)
  - `resources/js/app/service-worker-runtime.js:50-73` (auto warmup von Links)
- **Schweregrad:** **High**
- **CVSS-4.0 Begründung:** Hoher Confidentiality-Impact bei Geräteteilung/Compromise; niedrige Komplexität, keine besonderen Rechte auf App-Server nötig (lokales Browserprofil genügt).
- **Angriffsvektor / PoC:**
  1. User A öffnet private Szene/Charakterseiten.
  2. SW cached Responses (`status===200` reicht).
  3. Auf Shared Device kann ein späterer Nutzer mit lokalem Zugriff Offline-Artefakte auslesen (oder Browserprofil-Extraktion).
- **Warum 2026 nicht akzeptabel:** Private narrative Inhalte/Charakterdaten offline ohne harte Cache-Policy ist nicht mit Privacy-by-Default vereinbar.
- **Patch (minimal, sicherheitsorientiert):**

```diff
*** Begin Patch
*** Update File: public/js/sw/runtime-core.js
@@
 function shouldCache(response) {
-    return Boolean(response) && response.status === 200;
+    if (!response || response.status !== 200) {
+        return false;
+    }
+
+    const cacheControl = String(response.headers.get('Cache-Control') || '').toLowerCase();
+    if (cacheControl.includes('no-store') || cacheControl.includes('private')) {
+        return false;
+    }
+
+    return true;
 }
*** End Patch
```

```diff
*** Begin Patch
*** Update File: app/Http/Middleware/ApplySecurityHeaders.php
@@
         $response->headers->set(
             'Permissions-Policy',
             (string) config(
                 'security.permissions_policy',
                 'accelerometer=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
             )
         );
+
+        // Private HTML responses should not be persisted by browser or service worker caches.
+        if ($request->user() && str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')) {
+            $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
+            $response->headers->set('Pragma', 'no-cache');
+            $response->headers->set('Expires', '0');
+        }
*** End Patch
```

Optional (weniger hart, mehr UX): nur explizit markierte Seiten mit `X-Offline-Cache: allow` cachen.

---

## Medium

### [F-03] Security-Header-Härtung deckt statische Assets nicht zuverlässig ab
- **Datei/Zeilen:**
  - `app/Http/Middleware/ApplySecurityHeaders.php:15-57` (nur App-Responses)
  - `public/.htaccess:1-3` (setzt nur `X-Robots-Tag`)
- **Schweregrad:** **Medium**
- **CVSS-4.0 Begründung:** Mittlerer Exploitability- und Impact-Wert durch inkonsistente Browser-Sicherheitsrichtlinien auf statischen Entry Points (`offline.html`, SW-Dateien).
- **Angriffsvektor / PoC:** `GET /offline.html` erhält ohne Webserver-Härtung kein vollständiges Header-Set (CSP/XCTO/etc.), obwohl Seite aktiv JS ausführt.
- **Warum 2026 nicht akzeptabel:** Security Controls müssen konsistent über dynamische und statische Oberflächen gelten.
- **Patch (minimal, Webserver-seitig):**

```diff
*** Begin Patch
*** Update File: public/.htaccess
@@
 <IfModule mod_headers.c>
     Header always set X-Robots-Tag "noindex, nofollow, noarchive, nosnippet, noimageindex, max-snippet:0, max-image-preview:none, max-video-preview:0"
+    Header always set X-Content-Type-Options "nosniff"
+    Header always set X-Frame-Options "SAMEORIGIN"
+    Header always set Referrer-Policy "strict-origin-when-cross-origin"
+    Header always set Permissions-Policy "accelerometer=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()"
 </IfModule>
*** End Patch
```

---

### [F-04] Ungefilterte Übernahme/Reflexion von `X-Request-Id`
- **Datei/Zeilen:**
  - `app/Http/Middleware/AttachRequestId.php:20-23,29`
  - `tests/Feature/RequestIdHeaderTest.php:21-29` (zementiert Echo-Verhalten)
- **Schweregrad:** **Medium**
- **CVSS-4.0 Begründung:** Primär Integritätsimpact auf Logging/Tracing; Exploitability niedrig (nur Header setzen), keine Privilegien nötig.
- **Angriffsvektor / PoC:**
  - Angreifer sendet manipulierte/korrelierte IDs (`X-Request-Id: victim-trace-123`) -> Telemetrie/Forensik kontaminiert.
- **Warum 2026 nicht akzeptabel:** Request-Korrelation muss vertrauenswürdig und format-strikt sein.
- **Patch (minimal):**

```diff
*** Begin Patch
*** Update File: app/Http/Middleware/AttachRequestId.php
@@
 class AttachRequestId
 {
+    private const REQUEST_ID_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._-]{7,79}\z/';
@@
-        $incoming = trim((string) $request->headers->get('X-Request-Id', ''));
-        $requestId = $incoming !== ''
-            ? Str::limit($incoming, 80, '')
-            : (string) Str::uuid();
+        $incoming = trim((string) $request->headers->get('X-Request-Id', ''));
+        $candidate = Str::limit($incoming, 80, '');
+        $requestId = preg_match(self::REQUEST_ID_PATTERN, $candidate) === 1
+            ? $candidate
+            : (string) Str::uuid();
*** End Patch
```

Empfohlen ergänzend: invaliden Input explizit verwerfen und optional in separatem Feld (`upstream_request_id`) loggen.

---

## Low

### [F-05] Dotfiles im Public-Webroot (Info Disclosure Surface)
- **Datei/Zeilen:**
  - `public/.php-ini:1`
  - `public/.php-version:1`
  - `public/.DS_Store`
- **Schweregrad:** **Low**
- **CVSS-4.0 Begründung:** Primär niedriger Confidentiality-Impact, aber unnötige Recon-Unterstützung.
- **Angriffsvektor / PoC:** Bei fehlender Dotfile-Blockade können Angreifer Laufzeit-/Pfad-Informationen direkt abrufen.
- **Warum 2026 nicht akzeptabel:** Harte Angriffsflächenreduktion verlangt Entfernen/Blockieren solcher Dateien im DocumentRoot.
- **Patch (minimal):**

```diff
*** Begin Patch
*** Update File: public/.htaccess
@@
     RewriteEngine On
+
+    # Deny direct access to dotfiles (e.g. .php-version, .php-ini, .env-like files)
+    RewriteRule "(^|/)\." - [F,L]
*** End Patch
```

Zusätzlich: `.DS_Store` aus Repo entfernen und via `.gitignore` blocken.

---

## Dependency Security Summary

### Composer
- Befehl: `composer audit --locked --format=json`
- Ergebnis: **keine advisories**, **keine abandoned packages**.

### NPM
- Befehl: `npm audit --json`
- Ergebnis: **3 Vulnerability Buckets** (`critical:1`, `high:2`)
  - Direkt betroffen: `axios`, `vite`
  - Transitiv betroffen: `picomatch`

Empfohlene Pipeline-Regeln:
- `composer audit --locked`
- `npm audit --audit-level=high`
- Renovate/Dependabot mit Auto-PRs + Security-Label

---

## .env / Production-Defaults (Sollzustand)

### Hart empfohlene Production-Werte

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://<deine-domain>`
- `LOG_LEVEL=warning` (oder `error`)
- `SESSION_DRIVER=redis`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_HTTP_ONLY=true`
- `SESSION_SAME_SITE=lax` (oder `strict`, falls UX-konform)
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `BROADCAST_CONNECTION=log` nur wenn bewusst; sonst echter Broker
- `SECURITY_HSTS_MAX_AGE=31536000` nur bei sauberem HTTPS-Ende-zu-Ende + Proxy-Trust

### Ergänzende Security-Env-Flags (neu, empfohlen)

- `SECURITY_REQUIRE_STRICT_REQUEST_ID=true`
- `SECURITY_DISABLE_PRIVATE_PAGE_OFFLINE_CACHE=true`
- `SECURITY_CACHE_CONTROL_PRIVATE_HTML="no-store, private, max-age=0"`

---

## Zusätzliche Erweiterungen (konkret)

1. **Neue Middleware `PreventPrivateResponseCaching`**
   - Setzt `Cache-Control/Pragma/Expires` für authentifizierte HTML-Responses.

2. **Policy/Authorization Hardening Tests**
   - Property-basierte Tests für World-Isolation (jede Mutationsroute gegen fremde `world`/`campaign`/`scene` Kombinationen).

3. **PHPStan Security Ruleset**
   - Ergänze Security-Extension + custom forbidden-sink rules (`whereRaw`, `DB::raw`, `orderByRaw` nur mit Whitelist).

4. **Pennant Feature-Flags für sensitive Pfade**
   - Flags für `offline-private-cache`, `webpush`, `editor-preview`, `outbox-spike-log-candidates`.

5. **Sanctum Best Practice (falls API später aktiviert wird)**
   - Strict stateful domains, rotierende tokens, ability-scoped tokens, separate API guard.

6. **Scout/Index Security (falls Suchindexing kommt)**
   - Keine Rohinhalte sensibler Felder indexieren (`gm_secret`, interne Notizen); field-level allowlist erzwingen.

---

## Kategorie-Mapping (Kurzfazit)

- **Injection:** aktuell keine direkte High-Risk SQL/Command Injection gefunden.
- **Broken Auth/Session:** Basis solide; Session-Regeln env-abhängig, weiter härten.
- **XSS:** Markdown/BBCode Renderer sind gut abgesichert (`html_input=strip`, `allow_unsafe_links=false`).
- **Broken Access Control:** insgesamt solide, viele World-Context-/Policy-Checks vorhanden.
- **Security Misconfiguration:** Header- und static-surface Inkonsistenz bleibt relevant.
- **Vulnerable Components:** klarer Handlungsbedarf (`npm audit`).
- **Logging/Monitoring:** Request-ID-Integrität verbessern.
- **PWA-Security:** Privacy-Risiko durch Cache-Strategie, trotz Boundary-Cleanup-Mechanismen.

---

## Gesamtbewertung

- **Security Posture Score:** **6.2 / 10**
- **Brutal ehrliches Go/No-Go:**
  - **So wie jetzt: kein uneingeschränktes Go für Public Production.**
  - **Go mit Auflagen** nach F-01 und F-02 (plus F-03/F-04 zeitnah) ist realistisch.
