@php
    $character = $character ?? null;
    $isEdit = $mode === 'edit';
    $worldOptions = isset($worlds)
        ? collect($worlds)
        : \App\Models\World::query()->active()->ordered()->get(['id', 'name', 'slug']);
    $sheetResolver = app(\App\Support\CharacterSheetResolver::class);
    $worldConfigs = $sheetResolver->resolveForWorlds($worldOptions);

    $selectedWorldId = (int) old(
        'world_id',
        $character?->world_id
            ?? ($selectedWorld->id ?? $worldOptions->first()?->id ?? \App\Models\World::resolveDefaultId())
    );

    if ($isEdit && $character) {
        $selectedWorldId = (int) $character->world_id;
    }

    if (! array_key_exists($selectedWorldId, $worldConfigs)) {
        $selectedWorldId = (int) (array_key_first($worldConfigs) ?? 0);
    }

    $sheet = $selectedWorldId > 0
        ? (array) ($worldConfigs[$selectedWorldId] ?? $sheetResolver->resolveForWorldId($selectedWorldId))
        : (array) config('character_sheet', []);

    if ($isEdit && $character && (int) $character->world_id === $selectedWorldId) {
        $legacySpeciesKey = trim((string) $character->species);
        $legacyCallingKey = trim((string) $character->calling);

        if ($legacySpeciesKey !== '' && ! array_key_exists($legacySpeciesKey, (array) data_get($sheet, 'species', []))) {
            $sheet['species'][$legacySpeciesKey] = [
                'label' => 'Legacy: '.str_replace('_', ' ', ucfirst($legacySpeciesKey)),
                'description' => 'Diese Spezies ist in der Welt nicht mehr aktiv, bleibt für diesen bestehenden Charakter aber gültig.',
                'modifiers' => [],
                'le_bonus' => 0,
                'ae_bonus' => 0,
            ];
        }

        if ($legacyCallingKey !== '' && ! array_key_exists($legacyCallingKey, (array) data_get($sheet, 'callings', []))) {
            $sheet['callings'][$legacyCallingKey] = [
                'label' => 'Legacy: '.str_replace('_', ' ', ucfirst($legacyCallingKey)),
                'description' => 'Diese Berufung ist in der Welt nicht mehr aktiv, bleibt für diesen bestehenden Charakter aber gültig.',
                'minimums' => [],
                'bonuses' => ['attributes' => []],
                'custom' => false,
            ];
        }

        $worldConfigs[$selectedWorldId] = $sheet;
    }

    $attributeMeta = (array) data_get($sheet, 'attributes', []);
    $attributeKeys = array_keys($attributeMeta);

    $speciesOptions = (array) data_get($sheet, 'species', []);
    $callingOptions = (array) data_get($sheet, 'callings', []);
    $originOptions = (array) data_get($sheet, 'origins', []);

    $legacyMap = (array) data_get($sheet, 'legacy_column_map', []);
    $legacyColumnByAttribute = array_flip($legacyMap);

    $traitsMin = (int) data_get($sheet, 'traits.min', 1);
    $traitsMax = (int) data_get($sheet, 'traits.max', 3);
    $statusOptions = (array) config('characters.statuses', []);

    $defaultOrigin = array_key_exists('native_vhaltor', $originOptions) ? 'native_vhaltor' : (array_key_first($originOptions) ?? '');
    $defaultSpecies = array_key_exists('mensch', $speciesOptions) ? 'mensch' : (array_key_first($speciesOptions) ?? '');
    $defaultCalling = array_key_exists('abenteurer', $callingOptions) ? 'abenteurer' : (array_key_first($callingOptions) ?? '');

    $selectedWorldName = (string) ($worldOptions->firstWhere('id', $selectedWorldId)->name ?? 'C76-RPG');

    $selectedOrigin = (string) old('origin', $character?->origin ?? $defaultOrigin);
    $selectedSpecies = (string) old('species', $character?->species ?? $defaultSpecies);
    $selectedCalling = (string) old('calling', $character?->calling ?? $defaultCalling);
    $selectedStatus = (string) old('status', $character?->status ?? (string) config('characters.default_status', 'active'));

    $legacyToPercent = function (?int $rawValue): int {
        if ($rawValue === null) {
            return 40;
        }

        $value = $rawValue <= 20 ? (int) round($rawValue * 5) : $rawValue;

        return max(30, min(60, $value));
    };
    $legacyWeaponDamageToInt = function (mixed $rawDamage): int|string {
        if ($rawDamage === null || $rawDamage === '') {
            return '';
        }

        if (is_numeric($rawDamage)) {
            return max(1, min(999, (int) $rawDamage));
        }

        $value = trim((string) $rawDamage);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d+)\s*[wWdD]\s*(\d+)\s*([+-]\s*\d+)?$/', $value, $matches) === 1) {
            $count = (int) ($matches[1] ?? 0);
            $faces = (int) ($matches[2] ?? 0);
            $bonus = (int) str_replace(' ', '', (string) ($matches[3] ?? '0'));
            $estimated = (int) round(($count * (($faces + 1) / 2)) + $bonus);

            return max(1, min(999, $estimated));
        }

        if (preg_match('/-?\d+/', $value, $matches) === 1) {
            return max(1, min(999, (int) $matches[0]));
        }

        return '';
    };

    $initialAttributes = [];
    $initialAttributeNotes = [];

    foreach ($attributeKeys as $key) {
        $storedAttributeValue = $character?->{$key};
        $legacyColumn = $legacyColumnByAttribute[$key] ?? null;
        $legacyValue = ($legacyColumn && $character) ? $character->{$legacyColumn} : null;
        $baseAttribute = $storedAttributeValue !== null
            ? (int) $storedAttributeValue
            : $legacyToPercent($legacyValue !== null ? (int) $legacyValue : null);

        $initialAttributes[$key] = (int) old($key, $baseAttribute);
        $initialAttributeNotes[$key] = (string) old($key.'_note', (string) ($character?->{$key.'_note'} ?? ''));
    }

    $initialAdvantages = old('advantages', $character?->advantages ?? []);
    $initialDisadvantages = old('disadvantages', $character?->disadvantages ?? []);

    if (is_string($initialAdvantages)) {
        $initialAdvantages = preg_split('/[\r\n,]+/', $initialAdvantages) ?: [];
    }

    if (is_string($initialDisadvantages)) {
        $initialDisadvantages = preg_split('/[\r\n,]+/', $initialDisadvantages) ?: [];
    }

    $initialAdvantages = is_array($initialAdvantages)
        ? array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $initialAdvantages), static fn (string $value): bool => $value !== ''))
        : [];

    $initialDisadvantages = is_array($initialDisadvantages)
        ? array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $initialDisadvantages), static fn (string $value): bool => $value !== ''))
        : [];

    if ($initialAdvantages === []) {
        $initialAdvantages = array_fill(0, $traitsMin, '');
    }

    if ($initialDisadvantages === []) {
        $initialDisadvantages = array_fill(0, $traitsMin, '');
    }

    $initialInventory = old('inventory', $character?->inventory ?? []);
    if (is_string($initialInventory)) {
        $initialInventory = preg_split('/[\r\n,]+/', $initialInventory) ?: [];
    }
    $initialInventory = is_array($initialInventory)
        ? array_values(array_map(static function ($entry): array {
            if (is_string($entry)) {
                return [
                    'name' => trim($entry),
                    'quantity' => 1,
                    'equipped' => false,
                ];
            }

            if (! is_array($entry)) {
                return [
                    'name' => '',
                    'quantity' => 1,
                    'equipped' => false,
                ];
            }

            $rawQuantity = $entry['quantity'] ?? $entry['qty'] ?? 1;

            return [
                'name' => trim((string) ($entry['name'] ?? $entry['item'] ?? '')),
                'quantity' => is_numeric($rawQuantity) ? max(1, min(999, (int) $rawQuantity)) : 1,
                'equipped' => (bool) ($entry['equipped'] ?? false),
            ];
        }, $initialInventory))
        : [];
    if ($initialInventory === []) {
        $initialInventory = [[
            'name' => '',
            'quantity' => 1,
            'equipped' => false,
        ]];
    }

    $initialWeapons = old('weapons', $character?->weapons ?? []);
    $initialWeapons = is_array($initialWeapons)
        ? array_values(array_map(function ($weapon) use ($legacyWeaponDamageToInt): array {
            if (! is_array($weapon)) {
                return [
                    'name' => '',
                    'attack' => '',
                    'parry' => '',
                    'damage' => '',
                    'equipped' => false,
                ];
            }

            return [
                'name' => trim((string) ($weapon['name'] ?? '')),
                'attack' => ($weapon['attack'] ?? '') === '' ? '' : (int) $weapon['attack'],
                'parry' => ($weapon['parry'] ?? '') === '' ? '' : (int) $weapon['parry'],
                'damage' => $legacyWeaponDamageToInt($weapon['damage'] ?? ''),
                'equipped' => (bool) ($weapon['equipped'] ?? false),
            ];
        }, $initialWeapons))
        : [];
    if ($initialWeapons === []) {
        $initialWeapons = [[
            'name' => '',
            'attack' => '',
            'parry' => '',
            'damage' => '',
            'equipped' => false,
        ]];
    }

    $initialArmors = old('armors', $character?->armors ?? []);
    $initialArmors = is_array($initialArmors)
        ? array_values(array_map(static function ($armor): array {
            if (! is_array($armor)) {
                if (is_string($armor)) {
                    return [
                        'name' => trim($armor),
                        'protection' => 0,
                        'equipped' => false,
                    ];
                }

                return [
                    'name' => '',
                    'protection' => 0,
                    'equipped' => false,
                ];
            }

            return [
                'name' => trim((string) ($armor['name'] ?? $armor['item'] ?? '')),
                'protection' => max(0, min(99, (int) ($armor['protection'] ?? $armor['rs'] ?? 0))),
                'equipped' => (bool) ($armor['equipped'] ?? false),
            ];
        }, $initialArmors))
        : [];
    $initialArmors = array_values(array_filter(
        $initialArmors,
        static fn (array $armor): bool => ($armor['name'] ?? '') !== ''
    ));
    if ($initialArmors === []) {
        $initialArmors = [[
            'name' => '',
            'protection' => 0,
            'equipped' => false,
        ]];
    }

    $componentPayload = [
        'config' => $sheet,
        'worldConfigs' => $worldConfigs,
        'isEdit' => $isEdit,
        'attributeKeys' => $attributeKeys,
        'initial' => [
            'worldId' => (string) $selectedWorldId,
            'origin' => $selectedOrigin,
            'species' => $selectedSpecies,
            'calling' => $selectedCalling,
            'callingCustomName' => (string) old('calling_custom_name', $character?->calling_custom_name ?? ''),
            'callingCustomDescription' => (string) old('calling_custom_description', $character?->calling_custom_description ?? ''),
            'concept' => (string) old('concept', $character?->concept ?? ''),
            'worldConnection' => (string) old('world_connection', $character?->world_connection ?? ''),
            'gmSecret' => (string) old('gm_secret', $character?->gm_secret ?? ''),
            'gmNote' => (string) old('gm_note', $character?->gm_note ?? ''),
            'attributes' => $initialAttributes,
            'attributeNotes' => $initialAttributeNotes,
            'advantages' => $initialAdvantages,
            'disadvantages' => $initialDisadvantages,
            'inventory' => $initialInventory,
            'weapons' => $initialWeapons,
            'armors' => $initialArmors,
        ],
    ];
    $backUrl = is_string($backUrl ?? null) && $backUrl !== '' ? $backUrl : null;
    $returnTo = is_string($returnTo ?? null) && $returnTo !== '' ? $returnTo : null;
    $cancelUrl = is_string($cancelUrl ?? null) && $cancelUrl !== ''
        ? $cancelUrl
        : ($backUrl ?? (isset($character) ? route('characters.show', $character) : route('characters.index')));
    $useWizard = ! $isEdit;
    $wizardSteps = [
        'basics' => 'Welt & Grunddaten',
        'options' => 'Herkunft & Berufung',
        'attributes' => 'Attribute',
        'story' => 'Biografie & Weltbezug',
        'gear' => 'Ausrüstung & Avatar',
        'summary' => 'Übersicht & Speichern',
    ];
    $wizardGuidance = [
        'basics' => [
            'title' => 'Welt & Grunddaten',
            'task' => 'Fülle nur Name, optionalen Beinamen, Welt und Status aus. Damit ist klar, wo die Figur gespielt wird.',
            'why' => 'Die Welt bestimmt die verfügbaren Optionen und verhindert, dass Figuren versehentlich in der falschen Spielwelt landen.',
            'later' => 'Name, Beiname und Status kannst du später bearbeiten. Die Welt ist nach der Erstellung bewusst kein schneller Spielerwechsel.',
            'skip' => 'Kampagnenbeitritt, Regeln, Ausrüstung und Hintergrunddetails sind hier noch nicht nötig.',
        ],
        'options' => [
            'title' => 'Herkunft & Berufung',
            'task' => 'Wähle Herkunft, Spezies und Berufung. Lies die Kurztexte und nimm die Option, die dein Spielbild am besten trifft.',
            'why' => 'Diese Auswahl erklärt, woher die Figur kommt, was sie grundsätzlich kann und welche Werte automatisch angepasst werden.',
            'later' => 'Vor dem Speichern kannst du jederzeit zurück. Nach Spielstart sollten Änderungen an Spezies oder Berufung mit der Spielleitung abgestimmt werden.',
            'skip' => 'Du musst kein Startpaket optimieren und keine eigene Regelmaschine verstehen. Die bestehende Validierung bleibt maßgeblich.',
        ],
        'attributes' => [
            'title' => 'Attribute',
            'task' => 'Verteile acht Werte zwischen 30 und 60 und achte auf den erlaubten Durchschnitt.',
            'why' => 'Attribute beschreiben die Grundkompetenz deiner Figur und fließen später in Proben, LE und AE ein.',
            'later' => 'Narrative Notizen kannst du später pflegen. Die Zahlen selbst werden nach der Erstellung über Progression verändert.',
            'skip' => 'Du musst keine perfekte Verteilung finden. Wichtig ist ein plausibles Profil, nicht mathematische Optimierung.',
        ],
        'story' => [
            'title' => 'Biografie & Weltbezug',
            'task' => 'Schreibe eine kurze Biografie, ein klares Konzept, eine Verbindung zur Welt und bei Bedarf ein SL-Geheimnis.',
            'why' => 'Diese Angaben geben der Spielleitung Anknüpfungspunkte und helfen anderen, deine Figur in Szenen einzuordnen.',
            'later' => 'Texte und Absprachen können wachsen, sobald die Figur gespielt wird.',
            'skip' => 'Du brauchst keine vollständige Romanbiografie und keine komplette Regelkenntnis. Ein spielbarer Ansatz reicht.',
        ],
        'gear' => [
            'title' => 'Ausrüstung & Avatar',
            'task' => 'Trage nur wichtige Gegenstände ein und lade optional ein Portrait hoch.',
            'why' => 'Ausrüstung und Bild machen die Figur in Szenen greifbarer, sind für den ersten Speichervorgang aber nicht der Kern.',
            'later' => 'Inventar, Waffen, Rüstung und Avatar können im Spiel weiter gepflegt werden.',
            'skip' => 'Du musst jetzt keinen vollständigen Einkaufszettel bauen und keine Kampfausrüstung ausoptimieren.',
        ],
        'summary' => [
            'title' => 'Übersicht & Speichern',
            'task' => 'Prüfe die wichtigsten Eckdaten und speichere erst, wenn Welt, Konzept und Werte stimmig wirken.',
            'why' => 'Beim Speichern läuft weiterhin die kanonische Servervalidierung aus dem bestehenden Store-Vertrag.',
            'later' => 'Nach dem Speichern geht es zu Charakterdetails, offenen Einladungen oder sichtbaren Kampagnen der aktiven Welt.',
            'skip' => 'Hier wird keine fachlich falsche Kampagnenanmeldung ausgelöst.',
        ],
    ];
    $errorKeys = $errors->getBag('default')->keys();
    $firstErrorField = (string) ($errorKeys[0] ?? '');
@endphp

<section class="mx-auto w-full max-w-7xl rounded-3xl border border-stone-800 bg-black/40 p-5 shadow-2xl shadow-black/50 backdrop-blur-sm sm:p-8"
    x-data="characterSheetForm({{ json_encode($componentPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }})"
>
    @if ($backUrl)
        <x-navigation.back-link :href="$backUrl" label="Zurück" />
    @endif

    <header class="rounded-2xl border border-stone-800 bg-gradient-to-br from-stone-950 via-stone-900 to-red-950/35 p-6">
        <p class="text-xs uppercase tracking-[0.2em] text-red-300/80">{{ $selectedWorldName }}</p>
        <h1 class="mt-2 font-heading text-3xl text-stone-100 sm:text-4xl">
            {{ $isEdit ? 'Charakter aktualisieren' : 'Neuen Charakter erstellen' }}
        </h1>
        <p class="mt-3 max-w-4xl text-sm leading-relaxed text-stone-300 sm:text-base">
            Definiere Figur, Hintergrund und Werte für die gewählte Welt.
            Zahlen stützen die Geschichte, sie ersetzen sie nicht.
        </p>
    </header>

    @if ($errors->any())
        <article
            class="mt-6 rounded-xl border border-red-700/60 bg-red-950/35 p-4 text-sm text-red-200"
            @if ($firstErrorField !== '') data-validation-error-field="{{ $firstErrorField }}" @endif
        >
            <h2 class="font-semibold uppercase tracking-widest">Validierung fehlgeschlagen</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </article>
    @endif

    @if ($useWizard)
        <section class="mt-6 rounded-2xl border border-stone-800 bg-neutral-950/75 p-5" aria-label="Charakter-Assistent">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.14em] text-amber-400/80">Charakter-Assistent</p>
                    <h2 class="mt-1 font-heading text-2xl text-stone-100">Schritt für Schritt zur Spielfigur</h2>
                    <p class="mt-2 text-sm text-stone-300">
                        Jeder Schritt erklärt, was jetzt wichtig ist und was du bewusst noch ignorieren kannst.
                        Ohne JavaScript bleibt alles sichtbar und absendbar.
                    </p>
                </div>
                <p class="rounded-md border border-stone-700/80 bg-black/35 px-3 py-2 text-xs font-semibold uppercase tracking-[0.1em] text-stone-300" x-text="wizardProgressLabel">
                    1 / 6
                </p>
            </div>

            <div class="mt-4 h-2 overflow-hidden rounded-full bg-stone-800/80">
                <div class="h-full rounded-full bg-amber-500/80 transition-all duration-200" :style="'width: ' + wizardProgressPercent + '%'"></div>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
                @foreach ($wizardSteps as $wizardStepKey => $wizardStepLabel)
                    <x-ui.action
                        type="button"
                        size="compact"
                        x-bind:class="currentWizardStep === '{{ $wizardStepKey }}' ? 'border-amber-500/70 bg-amber-500/20 text-amber-100' : 'border-stone-700/80 bg-black/35 text-stone-300 hover:border-stone-500 hover:text-stone-100'"
                        @click="setWizardStep('{{ $wizardStepKey }}')"
                    >
                        {{ $wizardStepLabel }}
                    </x-ui.action>
                @endforeach
            </div>

            <noscript>
                <p class="mt-4 rounded-lg border border-stone-700/80 bg-black/35 p-3 text-sm text-stone-300">
                    JavaScript ist deaktiviert. Das Formular wird vollständig angezeigt und kann normal gespeichert werden.
                </p>
            </noscript>
        </section>
    @endif

    <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mt-6 space-y-8">
        @csrf
        @if ($returnTo)
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
        @endif
        @if (($method ?? 'POST') !== 'POST')
            @method($method)
        @endif

        @if ($useWizard)
            @include('characters.partials.wizard-step-guidance', [
                'wizardStepKey' => 'basics',
                'guidance' => $wizardGuidance['basics'],
            ])
            @include('characters.partials.wizard-step-guidance', [
                'wizardStepKey' => 'story',
                'guidance' => $wizardGuidance['story'],
            ])
        @endif

        <section class="grid gap-6 lg:grid-cols-2">
            <article
                class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5"
                @if ($useWizard) x-show="wizardShowsStep('basics')" @endif
            >
                <h2 class="font-heading text-2xl text-stone-100">Identität</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Name</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name', $character->name ?? '') }}"
                            maxlength="120"
                            required
                            @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-red-400 focus:ring-2 focus:ring-red-500/35"
                            placeholder="z. B. Vaelis vom zerbrochenen Siegel"
                        >
                        @error('name')
                            <p id="name-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="epithet" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Beiname</label>
                        <input
                            id="epithet"
                            name="epithet"
                            type="text"
                            value="{{ old('epithet', $character->epithet ?? '') }}"
                            maxlength="120"
                            @error('epithet') aria-invalid="true" aria-describedby="epithet-error" @enderror
                            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-red-400 focus:ring-2 focus:ring-red-500/35"
                            placeholder="z. B. Die Aschenspur"
                        >
                        @error('epithet')
                            <p id="epithet-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="world_id" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Welt</label>
                        @if ($isEdit && $character)
                            <input type="hidden" name="world_id" value="{{ (int) $character->world_id }}">
                        @endif
                        <select
                            id="world_id"
                            name="world_id"
                            required
                            x-model="worldId"
                            @disabled($isEdit)
                            @error('world_id') aria-invalid="true" aria-describedby="world-id-error" @enderror
                            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-500/35"
                        >
                            @foreach ($worldOptions as $worldOption)
                                <option value="{{ $worldOption->id }}" @selected($selectedWorldId === (int) $worldOption->id)>
                                    {{ $worldOption->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('world_id')
                            <p id="world-id-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="status" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Status</label>
                        <select
                            id="status"
                            name="status"
                            required
                            @error('status') aria-invalid="true" aria-describedby="status-error" @enderror
                            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-500/35"
                        >
                            @foreach ($statusOptions as $statusKey => $statusMeta)
                                <option value="{{ $statusKey }}" @selected($selectedStatus === $statusKey)>
                                    {{ $statusMeta['label'] ?? ucfirst((string) $statusKey) }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p id="status-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </article>

            <article
                class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5"
                @if ($useWizard) x-show="wizardShowsStep('story')" @endif
            >
                <h2 class="font-heading text-2xl text-stone-100">Narrative Kerndaten</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <label for="bio" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Biografie</label>
                        <textarea
                            id="bio"
                            name="bio"
                            rows="6"
                            required
                            minlength="20"
                            maxlength="5000"
                            @error('bio') aria-invalid="true" aria-describedby="bio-error" @enderror
                            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-red-400 focus:ring-2 focus:ring-red-500/35"
                            placeholder="Wer war deine Figur, bevor die Geschichte begann?"
                        >{{ old('bio', $character->bio ?? '') }}</textarea>
                        @error('bio')
                            <p id="bio-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="concept" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Kurzes Konzept (1 Satz)</label>
                        <textarea
                            id="concept"
                            name="concept"
                            rows="2"
                            minlength="8"
                            maxlength="180"
                            x-model="concept"
                            @error('concept') aria-invalid="true" aria-describedby="concept-error" @enderror
                            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-red-400 focus:ring-2 focus:ring-red-500/35"
                            placeholder="Wer bist du in einem einzigen, klaren Satz?"
                        >{{ old('concept', $character->concept ?? '') }}</textarea>
                        @error('concept')
                            <p id="concept-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="world_connection" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Besondere Verbindung zur Welt</label>
                        <textarea
                            id="world_connection"
                            name="world_connection"
                            rows="3"
                            minlength="10"
                            maxlength="2000"
                            x-model="worldConnection"
                            @error('world_connection') aria-invalid="true" aria-describedby="world-connection-error" @enderror
                            class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-red-400 focus:ring-2 focus:ring-red-500/35"
                            placeholder="Fraktion, Ort, Blutlinie, Schuld oder Schwur"
                        >{{ old('world_connection', $character->world_connection ?? '') }}</textarea>
                        @error('world_connection')
                            <p id="world-connection-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="gm_secret" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Geheimnis (nur SL)</label>
                        <textarea
                            id="gm_secret"
                            name="gm_secret"
                            rows="3"
                            minlength="10"
                            maxlength="3000"
                            x-model="gmSecret"
                            @error('gm_secret') aria-invalid="true" aria-describedby="gm-secret-error" @enderror
                            class="w-full rounded-md border border-red-800/70 bg-red-950/25 px-4 py-3 text-red-100 outline-none transition placeholder:text-red-300/70 focus:border-red-400 focus:ring-2 focus:ring-red-500/35"
                            placeholder="Was darf die Gruppe vorerst nicht wissen?"
                        >{{ old('gm_secret', $character->gm_secret ?? '') }}</textarea>
                        @error('gm_secret')
                            <p id="gm-secret-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </article>
        </section>

        @if ($useWizard)
            @include('characters.partials.wizard-step-guidance', [
                'wizardStepKey' => 'options',
                'guidance' => $wizardGuidance['options'],
            ])
        @endif

        <section
            class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5"
            @if ($useWizard) x-show="wizardShowsStep('options')" @endif
        >
            <h2 class="font-heading text-2xl text-stone-100">Spezies</h2>
            <p class="mt-2 text-sm text-stone-300">Spezies-Boni werden sofort auf effektive Mindestwerte und LE/AE angewendet.</p>
            <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500" x-show="origin === 'real_world_beginner'" x-cloak>
                Herkunft "Real-World Anfänger": Nur Mensch ist verfügbar.
            </p>

            <div class="mt-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Herkunft</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($originOptions as $originKey => $originLabel)
                        <label class="rounded-lg border border-stone-700/80 bg-black/35 px-3 py-2 text-sm text-stone-200 transition hover:border-stone-500"
                            :class="origin === '{{ $originKey }}' ? 'border-red-500/70 bg-red-500/10 text-red-100' : ''"
                        >
                            <input type="radio" class="sr-only" name="origin" value="{{ $originKey }}" x-model="origin" @checked($selectedOrigin === $originKey)>
                            {{ $originLabel }}
                        </label>
                    @endforeach
                </div>
                @error('origin')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-3">
                <template x-if="visibleSpeciesEntries.length === 0">
                    <article class="rounded-xl border border-amber-700/70 bg-amber-950/30 p-4 text-sm text-amber-100">
                        Für diese Welt sind aktuell keine Spezies aktiviert. Bitte im Welt-Admin eine Vorlage importieren oder Einträge anlegen.
                    </article>
                </template>
                <template x-for="entry in visibleSpeciesEntries" :key="entry.key">
                    <label class="rounded-xl border border-stone-700/80 bg-black/40 p-4 transition"
                        :class="species === entry.key ? 'border-red-500/70 bg-red-500/10' : 'hover:border-stone-500'"
                    >
                        <input class="sr-only" type="radio" name="species" :value="entry.key" x-model="species">
                        <h3 class="font-heading text-lg text-stone-100" x-text="entry.meta.label || entry.key"></h3>
                        <p class="mt-2 text-sm leading-relaxed text-stone-300" x-text="entry.meta.description || 'Keine Beschreibung in der Konfiguration.'"></p>
                        <p class="mt-3 text-xs uppercase tracking-widest text-red-200/90" x-text="formatSpeciesModifiers(entry.key)"></p>
                    </label>
                </template>
            </div>
            @error('species')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </section>

        @if ($useWizard)
            @include('characters.partials.wizard-step-guidance', [
                'wizardStepKey' => 'attributes',
                'guidance' => $wizardGuidance['attributes'],
            ])
        @endif

        <section
            class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5"
            @if ($useWizard) x-show="wizardShowsStep('attributes')" @endif
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-heading text-2xl text-stone-100">Grundeigenschaften</h2>
                    @if ($isEdit)
                        <p class="mt-1 text-sm text-stone-300">
                            Nach der Erstellung sind Grundeigenschaften schreibgeschützt.
                            Erhöhungen erfolgen ausschließlich über Stufenaufstieg und Attributpunkte.
                        </p>
                    @else
                        <p class="mt-1 text-sm text-stone-300">8 Werte zwischen 30 und 60. Durchschnitt darf maximal {{ data_get($sheet, 'average_max', 50) }} % sein.</p>
                    @endif
                </div>
                <div class="rounded-lg border border-stone-700/80 bg-black/45 px-4 py-2 text-right">
                    <p class="text-xs uppercase tracking-[0.12em] text-stone-400">Durchschnitt</p>
                    <p class="font-heading text-2xl" :class="averageValid ? 'text-emerald-300' : 'text-red-300'" x-text="averageFormatted"></p>
                </div>
            </div>

            <div class="mt-4 h-2 overflow-hidden rounded-full bg-stone-800/80">
                <div class="h-full transition-all duration-200" :class="averageValid ? 'bg-emerald-500/80' : 'bg-red-500/80'" :style="'width: ' + averageProgress + '%'"></div>
            </div>

            <p class="mt-2 text-sm" :class="averageValid ? 'text-emerald-200' : 'text-red-300'">
                <span x-show="averageValid">Die Verteilung bleibt im erlaubten Bereich.</span>
                <span x-show="!averageValid">Warnung: Der Durchschnitt liegt über {{ data_get($sheet, 'average_max', 50) }} %.</span>
            </p>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($attributeMeta as $key => $meta)
                    @php
                        $label = (string) ($meta['label'] ?? strtoupper($key));
                        $min = (int) ($meta['min'] ?? 30);
                        $max = (int) ($meta['max'] ?? 60);
                        $description = (string) ($meta['description'] ?? '');
                    @endphp
                    <article class="rounded-xl border border-stone-700/80 bg-black/45 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <label for="attr-{{ $key }}" class="text-sm font-semibold uppercase tracking-widest text-stone-200" @if ($description !== '') title="{{ $description }}" @endif>{{ $label }}</label>
                            <span class="rounded border border-stone-600/80 bg-stone-800/60 px-2 py-0.5 text-xs text-stone-300"
                                x-text="effectiveAttributes['{{ $key }}'] + ' % effektiv'"
                            ></span>
                        </div>
                        @if ($description !== '')
                            <p class="mt-2 text-xs text-stone-400">{{ $description }}</p>
                        @endif

                        <input
                            id="attr-{{ $key }}"
                            name="{{ $key }}"
                            type="number"
                            min="{{ $min }}"
                            max="{{ $max }}"
                            required
                            :value="attributes['{{ $key }}']"
                            @input="setAttributeValue('{{ $key }}', $event.target.value)"
                            @readonly($isEdit)
                            @error($key) aria-invalid="true" aria-describedby="attr-{{ $key }}-error" @enderror
                            class="mt-3 w-full rounded-md border border-stone-600/80 bg-stone-900/70 px-3 py-2 text-stone-100 outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-500/30"
                        >
                        @error($key)
                            <p id="attr-{{ $key }}-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror

                        <label for="attr-note-{{ $key }}" class="mt-3 block text-[0.68rem] font-semibold uppercase tracking-widest text-stone-400">Narrative Ausprägung</label>
                        <textarea
                            id="attr-note-{{ $key }}"
                            name="{{ $key }}_note"
                            rows="2"
                            maxlength="800"
                            :value="attributeNotes['{{ $key }}'] || ''"
                            @input="setAttributeNote('{{ $key }}', $event.target.value)"
                            @error($key.'_note') aria-invalid="true" aria-describedby="attr-{{ $key }}-note-error" @enderror
                            class="mt-1 w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-sm text-stone-200 outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-500/30"
                            placeholder="Wie zeigt sich {{ $label }} bei deiner Figur?"
                        ></textarea>
                        @error($key.'_note')
                            <p id="attr-{{ $key }}-note-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                        @enderror
                    </article>
                @endforeach
            </div>
        </section>

        <section
            class="grid gap-6 lg:grid-cols-[1.4fr_1fr]"
            @if ($useWizard) x-show="wizardShowsStep('options')" @endif
        >
            <article class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5">
                <h2 class="font-heading text-2xl text-stone-100">Berufung</h2>
                <p class="mt-2 text-sm text-stone-300">Mindestwerte werden gegen deine effektiven Attribute (inkl. Spezies) geprüft.</p>
                <p class="mt-1 text-xs uppercase tracking-[0.08em] text-stone-500" x-show="isRealWorldOrigin()" x-cloak>
                    Herkunft "Real-World Anfänger": Es werden nur realweltliche Berufungen oder "Eigene" angeboten.
                </p>

                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <template x-if="visibleCallingEntries.length === 0">
                        <article class="rounded-xl border border-amber-700/70 bg-amber-950/30 p-4 text-sm text-amber-100">
                            Für diese Welt sind aktuell keine Berufungen aktiviert. Bitte im Welt-Admin eine Vorlage importieren oder Einträge anlegen.
                        </article>
                    </template>
                    <template x-for="entry in visibleCallingEntries" :key="entry.key">
                        <label class="rounded-xl border border-stone-700/80 bg-black/40 p-4 transition"
                            :class="calling === entry.key ? 'border-red-500/70 bg-red-500/10' : 'hover:border-stone-500'"
                        >
                            <input class="sr-only" type="radio" name="calling" :value="entry.key" x-model="calling">
                            <h3 class="font-heading text-lg text-stone-100" x-text="entry.meta.label || entry.key"></h3>
                            <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-stone-300" x-text="entry.meta.description || 'Keine Beschreibung vorhanden.'"></p>
                        </label>
                    </template>
                </div>
                @error('calling')
                    <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror

                <div class="mt-5 rounded-xl border border-stone-700/80 bg-black/45 p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Gewählte Berufung</h3>
                    <p class="mt-2 font-heading text-xl text-red-200" x-text="selectedCallingLabel"></p>
                    <p class="mt-2 text-sm leading-relaxed text-stone-300" x-text="selectedCallingDescription"></p>

                    <div class="mt-4 rounded-lg border border-stone-700/70 bg-stone-950/80 p-3">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-stone-400">Mindestwerte</p>
                        <template x-if="callingRequirementEntries.length === 0">
                            <p class="mt-2 text-sm text-stone-300">Keine festen Mindestwerte in der Konfiguration.</p>
                        </template>
                        <ul class="mt-2 space-y-1 text-sm">
                            <template x-for="requirement in callingRequirementEntries" :key="requirement.key">
                                <li :class="requirement.met ? 'text-emerald-200' : 'text-red-300'">
                                    <span x-text="requirement.label"></span>
                                    <span class="text-stone-400"> | </span>
                                    <span x-text="requirement.current + ' / ' + requirement.required + ' %'"></span>
                                </li>
                            </template>
                        </ul>
                        <p class="mt-3 text-sm" :class="callingRequirementsValid ? 'text-emerald-200' : 'text-red-300'">
                            <span x-show="callingRequirementsValid">Mindestwerte sind erfüllt.</span>
                            <span x-show="!callingRequirementsValid">Warnung: Berufungsvoraussetzungen sind nicht vollständig erfüllt.</span>
                        </p>
                    </div>

                    <div x-show="requiresCustomCalling" x-cloak class="mt-4 grid gap-3 md:grid-cols-2">
                        <div>
                            <label for="calling_custom_name" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Eigene Berufung (Name)</label>
                            <input
                                id="calling_custom_name"
                                name="calling_custom_name"
                                type="text"
                                maxlength="120"
                                x-model="callingCustomName"
                                x-bind:required="requiresCustomCalling"
                                @error('calling_custom_name') aria-invalid="true" aria-describedby="calling-custom-name-error" @enderror
                                class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-stone-100 outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-500/30"
                            >
                            @error('calling_custom_name')
                                <p id="calling-custom-name-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="calling_custom_description" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">Eigene Berufung (Beschreibung)</label>
                            <textarea
                                id="calling_custom_description"
                                name="calling_custom_description"
                                rows="3"
                                maxlength="2000"
                                x-model="callingCustomDescription"
                                x-bind:required="requiresCustomCalling"
                                @error('calling_custom_description') aria-invalid="true" aria-describedby="calling-custom-description-error" @enderror
                                class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-stone-100 outline-none transition focus:border-red-400 focus:ring-2 focus:ring-red-500/30"
                            ></textarea>
                            @error('calling_custom_description')
                                <p id="calling-custom-description-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <p class="md:col-span-2 text-sm" :class="customCallingValid ? 'text-emerald-200' : 'text-red-300'">
                            <span x-show="customCallingValid">Eigene Berufung ist ausreichend beschrieben.</span>
                            <span x-show="!customCallingValid">Warnung: Für "Eigene" müssen Name und Beschreibung gesetzt sein.</span>
                        </p>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5">
                <h2 class="font-heading text-2xl text-stone-100">LE / AE</h2>
                <p class="mt-2 text-sm text-stone-300">Automatisch aus Konfiguration, Spezies und Berufung berechnet.</p>

                <div class="mt-4 space-y-4">
                    <div class="rounded-lg border border-red-800/70 bg-red-950/25 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold uppercase tracking-widest text-red-100">Lebensenergie</p>
                            <p class="font-heading text-2xl text-red-100"><span x-text="leMax"></span> / <span x-text="leMax"></span></p>
                        </div>
                        <div class="mt-2 h-2 rounded-full bg-red-950/70">
                            <div class="h-full rounded-full bg-red-500/80" style="width: 100%"></div>
                        </div>
                        <p class="mt-2 text-xs text-red-200/85">LE = runde((KO + KK + MU) / 3) + Spezies/Berufung</p>
                    </div>

                    <div class="rounded-lg border border-indigo-800/70 bg-indigo-950/25 p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold uppercase tracking-widest text-indigo-100">Astralenergie</p>
                            <p class="font-heading text-2xl text-indigo-100"><span x-text="aeMax"></span> / <span x-text="aeMax"></span></p>
                        </div>
                        <div class="mt-2 h-2 rounded-full bg-indigo-950/70">
                            <div class="h-full rounded-full bg-indigo-500/80" style="width: 100%"></div>
                        </div>
                        <p class="mt-2 text-xs text-indigo-200/85" x-show="hasAstralAccess">AE = runde((KL + IN + CH) / 3) + Spezies/Berufung</p>
                        <p class="mt-2 text-xs text-indigo-200/85" x-show="!hasAstralAccess">Keine Astralenergie ohne Magiebegabung (z. B. Elf, Magier, Geistlicher).</p>
                    </div>
                </div>
            </article>
        </section>

        <section
            class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5"
            @if ($useWizard) x-show="wizardShowsStep('story')" @endif
        >
            <h2 class="font-heading text-2xl text-stone-100">Vorteile und Nachteile (1:1)</h2>
            <p class="mt-2 text-sm text-stone-300">
                Wähle {{ $traitsMin }} bis {{ $traitsMax }} Vorteile und exakt gleich viele Nachteile.
            </p>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <article class="rounded-xl border border-stone-700/80 bg-black/40 p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold uppercase tracking-widest text-emerald-200">Vorteile</h3>
                        <button
                            type="button"
                            class="rounded-md border border-emerald-500/50 bg-emerald-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.08em] text-emerald-100 disabled:opacity-40"
                            @click="addTrait('advantages')"
                            :disabled="advantages.length >= traitsMax"
                        >
                            Vorteil +
                        </button>
                    </div>

                    <div class="mt-3 space-y-2">
                        <template x-for="(entry, index) in advantages" :key="'adv-' + index">
                            <div class="flex items-center gap-2">
                                <input
                                    :name="'advantages[' + index + ']'"
                                    :value="advantages[index]"
                                    @input="setTraitValue('advantages', index, $event.target.value)"
                                    type="text"
                                    maxlength="120"
                                    class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/30"
                                    placeholder="z. B. Blutpforten-Sinn"
                                >
                                <button
                                    type="button"
                                    class="rounded-md border border-stone-600/80 px-2 py-2 text-xs text-stone-300 hover:border-stone-400"
                                    @click="removeTrait('advantages', index)"
                                    :disabled="advantages.length <= traitsMin"
                                >
                                    x
                                </button>
                            </div>
                        </template>
                    </div>
                </article>

                <article class="rounded-xl border border-stone-700/80 bg-black/40 p-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold uppercase tracking-widest text-amber-200">Nachteile</h3>
                        <button
                            type="button"
                            class="rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.08em] text-amber-100 disabled:opacity-40"
                            @click="addTrait('disadvantages')"
                            :disabled="disadvantages.length >= traitsMax"
                        >
                            Nachteil +
                        </button>
                    </div>

                    <div class="mt-3 space-y-2">
                        <template x-for="(entry, index) in disadvantages" :key="'dis-' + index">
                            <div class="flex items-center gap-2">
                                <input
                                    :name="'disadvantages[' + index + ']'"
                                    :value="disadvantages[index]"
                                    @input="setTraitValue('disadvantages', index, $event.target.value)"
                                    type="text"
                                    maxlength="120"
                                    class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/30"
                                    placeholder="z. B. Aschesucht"
                                >
                                <button
                                    type="button"
                                    class="rounded-md border border-stone-600/80 px-2 py-2 text-xs text-stone-300 hover:border-stone-400"
                                    @click="removeTrait('disadvantages', index)"
                                    :disabled="disadvantages.length <= traitsMin"
                                >
                                    x
                                </button>
                            </div>
                        </template>
                    </div>
                </article>
            </div>

            <div class="mt-4 rounded-lg border px-4 py-3"
                :class="traitsValid ? 'border-emerald-700/70 bg-emerald-900/15 text-emerald-200' : 'border-red-700/70 bg-red-950/25 text-red-300'"
            >
                <p class="text-sm">
                    Vorteile: <span class="font-semibold" x-text="advantages.length"></span>
                    | Nachteile: <span class="font-semibold" x-text="disadvantages.length"></span>
                </p>
                <p class="mt-1 text-sm" x-show="!traitsValid">Warnung: Vorteile und Nachteile müssen 1:1 und im erlaubten Bereich liegen.</p>
                <p class="mt-1 text-sm" x-show="traitsValid">Paarung ist gültig.</p>
            </div>
            @error('advantages')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
            @error('advantages.*')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
            @error('disadvantages')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
            @error('disadvantages.*')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror

            <div class="mt-4">
                <label for="gm_note" class="mb-2 block text-xs font-semibold uppercase tracking-[0.12em] text-stone-300">SL-Notiz (Verhandlung Vorteile/Nachteile)</label>
                <textarea
                    id="gm_note"
                    name="gm_note"
                    rows="3"
                    maxlength="2000"
                    x-model="gmNote"
                    @error('gm_note') aria-invalid="true" aria-describedby="gm-note-error" @enderror
                    class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-3 text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-red-400 focus:ring-2 focus:ring-red-500/35"
                    placeholder="Absprachen, Grenzen, Balance-Notizen"
                >{{ old('gm_note', $character->gm_note ?? '') }}</textarea>
                @error('gm_note')
                    <p id="gm-note-error" class="mt-2 text-sm text-red-300">{{ $message }}</p>
                @enderror
            </div>
        </section>

        @if ($useWizard)
            @include('characters.partials.wizard-step-guidance', [
                'wizardStepKey' => 'gear',
                'guidance' => $wizardGuidance['gear'],
            ])
        @endif

        <section
            class="space-y-6"
            @if ($useWizard) x-show="wizardShowsStep('gear')" @endif
        >
            <article class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-heading text-2xl text-stone-100">Inventar</h2>
                        <p class="mt-1 text-sm text-stone-300">Gegenstände, die dein Held besitzt oder findet.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-emerald-500/50 bg-emerald-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.08em] text-emerald-100 disabled:opacity-40"
                        @click="addInventoryItem()"
                        :disabled="inventory.length >= inventoryMax"
                    >
                        Gegenstand +
                    </button>
                </div>

                <div class="mt-4 space-y-2">
                    <template x-for="(entry, index) in inventory" :key="'inv-' + index">
                        <div class="grid gap-2 rounded-lg border border-stone-700/70 bg-black/25 p-3 md:grid-cols-[1fr_6.5rem_auto_auto] md:items-center">
                            <input
                                :name="'inventory[' + index + '][name]'"
                                :value="inventory[index].name"
                                @input="setInventoryField(index, 'name', $event.target.value)"
                                type="text"
                                maxlength="180"
                                class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/30"
                                placeholder="z. B. Seil 10m lang"
                            >
                            <input
                                :name="'inventory[' + index + '][quantity]'"
                                :value="inventory[index].quantity"
                                @input="setInventoryField(index, 'quantity', $event.target.value)"
                                type="number"
                                min="1"
                                max="999"
                                step="1"
                                class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-sm text-stone-100 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/30"
                                title="Menge"
                            >
                            <label class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.08em] text-stone-300">
                                <input
                                    :name="'inventory[' + index + '][equipped]'"
                                    :checked="inventory[index].equipped"
                                    @change="setInventoryField(index, 'equipped', $event.target.checked)"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-emerald-500 focus:ring-emerald-500/60"
                                >
                                Ausger.
                            </label>
                            <button
                                type="button"
                                class="rounded-md border border-stone-600/80 px-2 py-2 text-xs text-stone-300 hover:border-stone-400"
                                @click="removeInventoryItem(index)"
                                :disabled="inventory.length <= inventoryMin"
                            >
                                x
                            </button>
                        </div>
                    </template>
                </div>
                <p class="mt-2 text-xs text-stone-500">Leere Zeilen werden beim Speichern ignoriert. Gleiche Gegenstände werden als Stack zusammengeführt.</p>
            </article>

            <article class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-heading text-2xl text-stone-100">Waffen</h2>
                        <p class="mt-1 text-sm text-stone-300">Angriff/Parade in %, plus Schadenspunkte.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-amber-500/50 bg-amber-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.08em] text-amber-100 disabled:opacity-40"
                        @click="addWeapon()"
                        :disabled="weapons.length >= weaponsMax"
                    >
                        Waffe +
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <template x-for="(weapon, index) in weapons" :key="'weapon-' + index">
                        <div class="rounded-lg border border-stone-700/80 bg-black/35 p-3">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-400">Name</label>
                                    <input
                                        :name="'weapons[' + index + '][name]'"
                                        :value="weapon.name"
                                        @input="setWeaponField(index, 'name', $event.target.value)"
                                        type="text"
                                        maxlength="120"
                                        class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/30"
                                        placeholder="z. B. Krummschwert"
                                    >
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.08em] text-stone-300">
                                        <input
                                            type="radio"
                                            name="weapons_equipped_index"
                                            :value="index"
                                            :checked="Boolean(weapon.equipped)"
                                            @change="setActiveWeapon(index)"
                                            class="h-4 w-4 border-stone-500 bg-neutral-900 text-amber-500 focus:ring-amber-500/60"
                                        >
                                        Aktive Waffe (im Kampf verwenden)
                                    </label>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-400">Angriff (%)</label>
                                    <input
                                        :name="'weapons[' + index + '][attack]'"
                                        :value="weapon.attack"
                                        @input="setWeaponField(index, 'attack', $event.target.value)"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/30"
                                    >
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-400">Parade (%)</label>
                                    <input
                                        :name="'weapons[' + index + '][parry]'"
                                        :value="weapon.parry"
                                        @input="setWeaponField(index, 'parry', $event.target.value)"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-sm text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/30"
                                    >
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-400">Schadenspunkte</label>
                                    <input
                                        :name="'weapons[' + index + '][damage]'"
                                        :value="weapon.damage"
                                        @input="setWeaponField(index, 'damage', $event.target.value)"
                                        type="number"
                                        min="0"
                                        max="999"
                                        step="1"
                                        class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-500/30"
                                        placeholder="z. B. 12"
                                    >
                                </div>
                            </div>

                            <button
                                type="button"
                                class="mt-3 rounded-md border border-stone-600/80 px-2 py-1 text-xs text-stone-300 hover:border-stone-400"
                                @click="removeWeapon(index)"
                                :disabled="weapons.length <= weaponsMin"
                            >
                                Waffe entfernen
                            </button>
                        </div>
                    </template>
                </div>
                <p class="mt-2 text-xs text-stone-500">Schaden als feste Schadenspunkte (kein Würfelcode). Leere Waffenzeilen werden beim Speichern ignoriert.</p>
            </article>

            <article class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-heading text-2xl text-stone-100">Rüstung</h2>
                        <p class="mt-1 text-sm text-stone-300">Rüstungsschutz (RS) wird bei Angriffsschaden von LE-Verlusten abgezogen.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-sky-500/50 bg-sky-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.08em] text-sky-100 disabled:opacity-40"
                        @click="addArmor()"
                        :disabled="armors.length >= armorsMax"
                    >
                        Rüstung +
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <template x-for="(armor, index) in armors" :key="'armor-' + index">
                        <div class="rounded-lg border border-stone-700/80 bg-black/35 p-3">
                            <div class="grid gap-2 sm:grid-cols-[1fr_7rem_auto] sm:items-center">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-400">Name</label>
                                    <input
                                        :name="'armors[' + index + '][name]'"
                                        :value="armor.name"
                                        @input="setArmorField(index, 'name', $event.target.value)"
                                        type="text"
                                        maxlength="120"
                                        class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-sm text-stone-100 outline-none transition placeholder:text-stone-500 focus:border-sky-400 focus:ring-2 focus:ring-sky-500/30"
                                        placeholder="z. B. Lederrüstung"
                                    >
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] text-stone-400">RS</label>
                                    <input
                                        :name="'armors[' + index + '][protection]'"
                                        :value="armor.protection"
                                        @input="setArmorField(index, 'protection', $event.target.value)"
                                        type="number"
                                        min="0"
                                        max="99"
                                        step="1"
                                        class="w-full rounded-md border border-stone-600/80 bg-black/45 px-3 py-2 text-sm text-stone-100 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-500/30"
                                    >
                                </div>
                                <label class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.08em] text-stone-300">
                                    <input
                                        :name="'armors[' + index + '][equipped]'"
                                        :checked="armor.equipped"
                                        @change="setArmorField(index, 'equipped', $event.target.checked)"
                                        value="1"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-sky-500 focus:ring-sky-500/60"
                                    >
                                    Ausger.
                                </label>
                            </div>

                            <button
                                type="button"
                                class="mt-3 rounded-md border border-stone-600/80 px-2 py-1 text-xs text-stone-300 hover:border-stone-400"
                                @click="removeArmor(index)"
                                :disabled="armors.length <= armorsMin"
                            >
                                Rüstung entfernen
                            </button>
                        </div>
                    </template>
                </div>
                <p class="mt-2 text-xs text-stone-500">
                    Wenn keine Rüstung explizit als ausgerüstet markiert ist, wird die Summe aller RS-Einträge verwendet.
                </p>
            </article>
        </section>

        <section
            class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5"
            @if ($useWizard) x-show="wizardShowsStep('gear')" @endif
        >
            <h2 class="font-heading text-2xl text-stone-100">Avatar</h2>
            <p class="mt-2 text-sm text-stone-300">Porträt deiner Figur für Szenenansicht und Steckbrief.</p>

            <div class="mt-4 space-y-3">
                <input
                    id="avatar"
                    type="file"
                    name="avatar"
                    accept="image/jpeg,image/png,image/webp,image/avif"
                    @error('avatar') aria-invalid="true" aria-describedby="avatar-error" @enderror
                    class="block w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-sm text-stone-200 file:mr-3 file:rounded file:border-0 file:bg-red-500/20 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:uppercase file:tracking-[0.08em] file:text-red-100 hover:file:bg-red-500/35"
                >
                <p class="text-xs text-stone-400">Erlaubt: JPG, PNG, WEBP, AVIF bis 3 MB.</p>
                @error('avatar')
                    <p id="avatar-error" class="text-sm text-red-300">{{ $message }}</p>
                @enderror

                @if ($isEdit && !empty($character?->avatar_path))
                    <div class="flex flex-wrap items-center gap-4 rounded-md border border-stone-700/80 bg-black/35 p-3">
                        <a
                            href="{{ $character->avatarUrl() }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Aktuelles Charakterbild von {{ $character->name }} in voller Größe öffnen"
                            class="block"
                        >
                            <img src="{{ $character->avatarUrl() }}" alt="Aktuelles Charakterbild" class="h-24 w-20 rounded object-cover">
                        </a>
                        <label class="flex items-center gap-2 text-sm text-stone-200">
                            <input type="checkbox" name="remove_avatar" value="1" @checked(old('remove_avatar')) class="h-4 w-4 rounded border-stone-500 bg-neutral-900 text-red-500 focus:ring-red-500/60">
                            Aktuelles Bild entfernen
                        </label>
                    </div>
                @endif
            </div>
        </section>

        @if ($useWizard)
            @include('characters.partials.wizard-step-guidance', [
                'wizardStepKey' => 'summary',
                'guidance' => $wizardGuidance['summary'],
            ])

            <section class="rounded-2xl border border-stone-800 bg-neutral-950/75 p-5" x-show="wizardShowsStep('summary')">
                <h2 class="font-heading text-2xl text-stone-100">Übersicht & Speichern</h2>
                <p class="mt-2 text-sm text-stone-300">
                    Prüfe die Eckdaten. Die verbindliche Prüfung passiert weiterhin serverseitig beim Speichern.
                </p>

                <dl class="mt-5 grid gap-3 md:grid-cols-2">
                    <div class="rounded-xl border border-stone-800 bg-black/35 p-4">
                        <dt class="text-xs uppercase tracking-[0.12em] text-stone-400">Welt</dt>
                        <dd class="mt-1 font-semibold text-stone-100">{{ $selectedWorldName }}</dd>
                    </div>
                    <div class="rounded-xl border border-stone-800 bg-black/35 p-4">
                        <dt class="text-xs uppercase tracking-[0.12em] text-stone-400">Herkunft</dt>
                        <dd class="mt-1 font-semibold text-stone-100" x-text="selectedOriginLabel"></dd>
                    </div>
                    <div class="rounded-xl border border-stone-800 bg-black/35 p-4">
                        <dt class="text-xs uppercase tracking-[0.12em] text-stone-400">Spezies</dt>
                        <dd class="mt-1 font-semibold text-stone-100" x-text="selectedSpeciesLabel"></dd>
                    </div>
                    <div class="rounded-xl border border-stone-800 bg-black/35 p-4">
                        <dt class="text-xs uppercase tracking-[0.12em] text-stone-400">Berufung</dt>
                        <dd class="mt-1 font-semibold text-stone-100" x-text="selectedCallingLabel"></dd>
                    </div>
                    <div class="rounded-xl border border-stone-800 bg-black/35 p-4">
                        <dt class="text-xs uppercase tracking-[0.12em] text-stone-400">Attribute</dt>
                        <dd class="mt-1 font-semibold" :class="averageValid ? 'text-emerald-200' : 'text-red-300'" x-text="averageFormatted"></dd>
                    </div>
                    <div class="rounded-xl border border-stone-800 bg-black/35 p-4">
                        <dt class="text-xs uppercase tracking-[0.12em] text-stone-400">LE / AE</dt>
                        <dd class="mt-1 font-semibold text-stone-100"><span x-text="leMax"></span> LE / <span x-text="aeMax"></span> AE</dd>
                    </div>
                    <div class="rounded-xl border border-stone-800 bg-black/35 p-4">
                        <dt class="text-xs uppercase tracking-[0.12em] text-stone-400">Vorteile / Nachteile</dt>
                        <dd class="mt-1 font-semibold" :class="traitsValid ? 'text-emerald-200' : 'text-red-300'">
                            <span x-text="advantages.length"></span> / <span x-text="disadvantages.length"></span>
                        </dd>
                    </div>
                    <div class="rounded-xl border border-stone-800 bg-black/35 p-4">
                        <dt class="text-xs uppercase tracking-[0.12em] text-stone-400">Ausrüstung</dt>
                        <dd class="mt-1 font-semibold text-stone-100">
                            <span x-text="inventory.length"></span> Gegenstände,
                            <span x-text="weapons.length"></span> Waffen,
                            <span x-text="armors.length"></span> Rüstungen
                        </dd>
                    </div>
                </dl>
            </section>
        @endif

        @if ($useWizard)
            <nav class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-stone-800 bg-black/35 p-4" aria-label="Charakter-Assistent Navigation">
                <x-ui.action
                    type="button"
                    @click="previousWizardStep()"
                    x-bind:disabled="currentWizardIndex === 0"
                >
                    <span x-text="previousWizardButtonLabel">Zurück im Assistenten</span>
                </x-ui.action>
                <p
                    class="order-last w-full text-sm text-red-300"
                    x-show="wizardValidationMessage !== ''"
                    x-text="wizardValidationMessage"
                    aria-live="assertive"
                ></p>
                <x-ui.action
                    type="button"
                    variant="accent"
                    @click="validateCurrentWizardStep($event)"
                    x-bind:disabled="currentWizardIndex >= wizardSteps.length - 1"
                >
                    <span x-text="nextWizardButtonLabel">Weiter</span>
                </x-ui.action>
            </nav>
        @endif

        <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-800 pt-6">
            <x-ui.action :href="$cancelUrl" size="large">
                Abbrechen
            </x-ui.action>

            <x-ui.action
                type="submit"
                variant="danger"
                size="large"
            >
                {{ $submitLabel }}
            </x-ui.action>
        </footer>
    </form>
</section>
