<section class="mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
    <h2 class="font-heading text-2xl text-stone-100">Charakter-Optionen pro Welt</h2>
    <p class="mt-2 text-sm text-stone-300">Importiere eine Vorlage und passe Spezies/Berufungen danach individuell an.</p>

    <form method="POST" action="{{ route('admin.worlds.character-options.import-template', $world) }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
        @csrf
        <div>
            <label for="template_key" class="mb-2 block text-xs font-semibold uppercase tracking-widest text-stone-300">Vorlage</label>
            <select
                id="template_key"
                name="template_key"
                required
                class="w-full rounded-md border border-stone-700/80 bg-black/45 px-4 py-2.5 text-stone-100 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-500/35"
            >
                @foreach ($templateOptions as $templateKey => $templateLabel)
                    <option value="{{ $templateKey }}" @selected(old('template_key', $defaultTemplateKey) === $templateKey)>
                        {{ $templateLabel }} ({{ $templateKey }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="ui-btn ui-btn-accent inline-flex">Vorlage importieren</button>
        </div>
    </form>
</section>

<section class="mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
    <h2 class="font-heading text-2xl text-stone-100">Spezies</h2>

    <div class="mt-4 space-y-4">
        @forelse ($speciesOptions as $speciesOption)
            <article class="rounded-xl border border-stone-800 bg-black/35 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-stone-100">{{ $speciesOption->label }} <span class="text-stone-400">({{ $speciesOption->key }})</span></p>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.worlds.species-options.move', ['world' => $world, 'speciesOption' => $speciesOption, 'direction' => 'up']) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="ui-btn inline-flex">Hoch</button>
                        </form>
                        <form method="POST" action="{{ route('admin.worlds.species-options.move', ['world' => $world, 'speciesOption' => $speciesOption, 'direction' => 'down']) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="ui-btn inline-flex">Runter</button>
                        </form>
                        <form method="POST" action="{{ route('admin.worlds.species-options.toggle', ['world' => $world, 'speciesOption' => $speciesOption]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="ui-btn inline-flex">{{ $speciesOption->is_active ? 'Deaktivieren' : 'Aktivieren' }}</button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.worlds.species-options.update', ['world' => $world, 'speciesOption' => $speciesOption]) }}" class="mt-3 grid gap-3 lg:grid-cols-2">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Key</label>
                        <input name="key" value="{{ old('key', $speciesOption->key) }}" required maxlength="80" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Label</label>
                        <input name="label" value="{{ old('label', $speciesOption->label) }}" required maxlength="120" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
                    </div>
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Beschreibung</label>
                        <textarea name="description" rows="2" maxlength="2000" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">{{ old('description', $speciesOption->description ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Modifikatoren (JSON)</label>
                        <textarea name="modifiers_json" rows="3" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 font-mono text-xs text-stone-100">{{ old('modifiers_json', json_encode($speciesOption->modifiers_json ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) }}</textarea>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">LE-Bonus</label>
                            <input type="number" name="le_bonus" value="{{ old('le_bonus', $speciesOption->le_bonus) }}" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">AE-Bonus</label>
                            <input type="number" name="ae_bonus" value="{{ old('ae_bonus', $speciesOption->ae_bonus) }}" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Position</label>
                            <input type="number" min="0" name="position" value="{{ old('position', $speciesOption->position) }}" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
                        </div>
                    </div>
                    <div class="lg:col-span-2 flex flex-wrap gap-4 text-sm text-stone-200">
                        <label class="inline-flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $speciesOption->is_active))>
                            Aktiv
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="hidden" name="is_magic_capable" value="0">
                            <input type="checkbox" name="is_magic_capable" value="1" @checked(old('is_magic_capable', $speciesOption->is_magic_capable))>
                            Magiebegabt
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="hidden" name="is_template" value="0">
                            <input type="checkbox" name="is_template" value="1" @checked(old('is_template', $speciesOption->is_template))>
                            Template-Eintrag
                        </label>
                    </div>
                    <div class="lg:col-span-2">
                        <button type="submit" class="ui-btn inline-flex">Spezies speichern</button>
                    </div>
                </form>
            </article>
        @empty
            <p class="text-sm text-stone-400">Noch keine Spezies vorhanden.</p>
        @endforelse
    </div>

    <article class="mt-6 rounded-xl border border-stone-800 bg-black/35 p-4">
        <h3 class="font-semibold text-stone-100">Neue Spezies</h3>
        <form method="POST" action="{{ route('admin.worlds.species-options.store', $world) }}" class="mt-3 grid gap-3 lg:grid-cols-2">
            @csrf
            <div>
                <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Key</label>
                <input name="key" required maxlength="80" placeholder="z. B. vampir" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
            </div>
            <div>
                <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Label</label>
                <input name="label" required maxlength="120" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
            </div>
            <div class="lg:col-span-2">
                <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Beschreibung</label>
                <textarea name="description" rows="2" maxlength="2000" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100"></textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Modifikatoren (JSON)</label>
                <textarea name="modifiers_json" rows="3" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 font-mono text-xs text-stone-100">{}</textarea>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">LE-Bonus</label>
                    <input type="number" name="le_bonus" value="0" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">AE-Bonus</label>
                    <input type="number" name="ae_bonus" value="0" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
                </div>
                <div>
                    <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Position</label>
                    <input type="number" min="0" name="position" value="0" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
                </div>
            </div>
            <div class="lg:col-span-2 flex flex-wrap gap-4 text-sm text-stone-200">
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Aktiv
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_magic_capable" value="0">
                    <input type="checkbox" name="is_magic_capable" value="1">
                    Magiebegabt
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_template" value="0">
                    <input type="checkbox" name="is_template" value="1">
                    Template-Eintrag
                </label>
            </div>
            <div class="lg:col-span-2">
                <button type="submit" class="ui-btn ui-btn-accent inline-flex">Spezies anlegen</button>
            </div>
        </form>
    </article>
</section>

<section class="mt-6 rounded-2xl border border-stone-800 bg-neutral-900/60 p-6">
    <h2 class="font-heading text-2xl text-stone-100">Berufungen</h2>

    <div class="mt-4 space-y-4">
        @forelse ($callingOptions as $callingOption)
            <article class="rounded-xl border border-stone-800 bg-black/35 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-stone-100">{{ $callingOption->label }} <span class="text-stone-400">({{ $callingOption->key }})</span></p>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.worlds.calling-options.move', ['world' => $world, 'callingOption' => $callingOption, 'direction' => 'up']) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="ui-btn inline-flex">Hoch</button>
                        </form>
                        <form method="POST" action="{{ route('admin.worlds.calling-options.move', ['world' => $world, 'callingOption' => $callingOption, 'direction' => 'down']) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="ui-btn inline-flex">Runter</button>
                        </form>
                        <form method="POST" action="{{ route('admin.worlds.calling-options.toggle', ['world' => $world, 'callingOption' => $callingOption]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="ui-btn inline-flex">{{ $callingOption->is_active ? 'Deaktivieren' : 'Aktivieren' }}</button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.worlds.calling-options.update', ['world' => $world, 'callingOption' => $callingOption]) }}" class="mt-3 grid gap-3 lg:grid-cols-2">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Key</label>
                        <input name="key" value="{{ old('key', $callingOption->key) }}" required maxlength="80" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Label</label>
                        <input name="label" value="{{ old('label', $callingOption->label) }}" required maxlength="120" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
                    </div>
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Beschreibung</label>
                        <textarea name="description" rows="2" maxlength="2000" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">{{ old('description', $callingOption->description ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Minimums (JSON)</label>
                        <textarea name="minimums_json" rows="3" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 font-mono text-xs text-stone-100">{{ old('minimums_json', json_encode($callingOption->minimums_json ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Bonuses (JSON)</label>
                        <textarea name="bonuses_json" rows="3" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 font-mono text-xs text-stone-100">{{ old('bonuses_json', json_encode($callingOption->bonuses_json ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Position</label>
                        <input type="number" min="0" name="position" value="{{ old('position', $callingOption->position) }}" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm text-stone-200">
                        <label class="inline-flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $callingOption->is_active))>
                            Aktiv
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="hidden" name="is_magic_capable" value="0">
                            <input type="checkbox" name="is_magic_capable" value="1" @checked(old('is_magic_capable', $callingOption->is_magic_capable))>
                            Magiebegabt
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="hidden" name="is_custom" value="0">
                            <input type="checkbox" name="is_custom" value="1" @checked(old('is_custom', $callingOption->is_custom))>
                            Custom-Berufung
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="hidden" name="is_template" value="0">
                            <input type="checkbox" name="is_template" value="1" @checked(old('is_template', $callingOption->is_template))>
                            Template-Eintrag
                        </label>
                    </div>
                    <div class="lg:col-span-2">
                        <button type="submit" class="ui-btn inline-flex">Berufung speichern</button>
                    </div>
                </form>
            </article>
        @empty
            <p class="text-sm text-stone-400">Noch keine Berufungen vorhanden.</p>
        @endforelse
    </div>

    <article class="mt-6 rounded-xl border border-stone-800 bg-black/35 p-4">
        <h3 class="font-semibold text-stone-100">Neue Berufung</h3>
        <form method="POST" action="{{ route('admin.worlds.calling-options.store', $world) }}" class="mt-3 grid gap-3 lg:grid-cols-2">
            @csrf
            <div>
                <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Key</label>
                <input name="key" required maxlength="80" placeholder="z. B. inquisitor" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
            </div>
            <div>
                <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Label</label>
                <input name="label" required maxlength="120" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
            </div>
            <div class="lg:col-span-2">
                <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Beschreibung</label>
                <textarea name="description" rows="2" maxlength="2000" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100"></textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Minimums (JSON)</label>
                <textarea name="minimums_json" rows="3" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 font-mono text-xs text-stone-100">{}</textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Bonuses (JSON)</label>
                <textarea name="bonuses_json" rows="3" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 font-mono text-xs text-stone-100">{"attributes":{}}</textarea>
            </div>
            <div>
                <label class="mb-1 block text-xs uppercase tracking-widest text-stone-400">Position</label>
                <input type="number" min="0" name="position" value="0" class="w-full rounded-md border border-stone-700/80 bg-black/45 px-3 py-2 text-stone-100">
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-stone-200">
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked>
                    Aktiv
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_magic_capable" value="0">
                    <input type="checkbox" name="is_magic_capable" value="1">
                    Magiebegabt
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_custom" value="0">
                    <input type="checkbox" name="is_custom" value="1">
                    Custom-Berufung
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_template" value="0">
                    <input type="checkbox" name="is_template" value="1">
                    Template-Eintrag
                </label>
            </div>
            <div class="lg:col-span-2">
                <button type="submit" class="ui-btn ui-btn-accent inline-flex">Berufung anlegen</button>
            </div>
        </form>
    </article>
</section>
