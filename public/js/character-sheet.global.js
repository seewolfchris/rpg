(() => {
    const toInt = (value, fallback = 0) => {
        const parsed = Number.parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    };

    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

    const normalizeStringArray = (values) => {
        if (!Array.isArray(values)) {
            return [];
        }

        return values
            .map((value) => String(value ?? '').trim())
            .filter((value) => value.length > 0);
    };

    const withMinimumEmptyRows = (values, minimum) => {
        const list = [...normalizeStringArray(values)];

        while (list.length < minimum) {
            list.push('');
        }

        return list;
    };

    window.characterSheetForm = function characterSheetForm(payload = {}) {
        return {
            config: payload.config ?? {},
            isEdit: Boolean(payload.isEdit),
            attributeKeys: Array.isArray(payload.attributeKeys) ? payload.attributeKeys : [],

            origin: String(payload.initial?.origin ?? ''),
            species: String(payload.initial?.species ?? ''),
            calling: String(payload.initial?.calling ?? ''),
            callingCustomName: String(payload.initial?.callingCustomName ?? ''),
            callingCustomDescription: String(payload.initial?.callingCustomDescription ?? ''),

            attributes: payload.initial?.attributes ?? {},
            attributeNotes: payload.initial?.attributeNotes ?? {},

            advantages: [],
            disadvantages: [],

            init() {
                this.advantages = withMinimumEmptyRows(payload.initial?.advantages ?? [], this.traitsMin);
                this.disadvantages = withMinimumEmptyRows(payload.initial?.disadvantages ?? [], this.traitsMin);

                if (!this.species) {
                    this.species = Object.keys(this.speciesOptions)[0] ?? '';
                }

                if (!this.calling) {
                    this.calling = Object.keys(this.callingOptions)[0] ?? '';
                }

                if (!this.origin) {
                    this.origin = Object.keys(this.originOptions)[0] ?? '';
                }

                this.attributeKeys.forEach((key) => {
                    const min = this.attributeBounds(key).min;
                    const max = this.attributeBounds(key).max;
                    const current = toInt(this.attributes?.[key], min);

                    this.attributes[key] = clamp(current, min, max);
                });
            },

            get originOptions() {
                return this.config.origins ?? {};
            },

            get speciesOptions() {
                return this.config.species ?? {};
            },

            get callingOptions() {
                return this.config.callings ?? {};
            },

            get selectedSpecies() {
                return this.speciesOptions[this.species] ?? null;
            },

            get selectedCalling() {
                return this.callingOptions[this.calling] ?? null;
            },

            get selectedCallingLabel() {
                return this.selectedCalling?.label ?? 'Unbekannte Berufung';
            },

            get selectedCallingDescription() {
                return this.selectedCalling?.description ?? 'Keine Beschreibung vorhanden.';
            },

            get callingMinimums() {
                return this.selectedCalling?.minimums ?? {};
            },

            get averageMax() {
                return toInt(this.config.average_max, 50);
            },

            get traitsMin() {
                return toInt(this.config.traits?.min, 1);
            },

            get traitsMax() {
                return toInt(this.config.traits?.max, 3);
            },

            get requiresCustomCalling() {
                return this.calling === 'eigene';
            },

            get customCallingValid() {
                if (!this.requiresCustomCalling) {
                    return true;
                }

                return this.callingCustomName.trim().length > 0 && this.callingCustomDescription.trim().length > 0;
            },

            attributeBounds(key) {
                const meta = this.config.attributes?.[key] ?? {};

                return {
                    min: toInt(meta.min, 30),
                    max: toInt(meta.max, 60),
                };
            },

            attributeLabel(key) {
                return this.config.attributes?.[key]?.label ?? key.toUpperCase();
            },

            attributeValue(key) {
                const bounds = this.attributeBounds(key);
                const current = toInt(this.attributes?.[key], bounds.min);

                return clamp(current, bounds.min, bounds.max);
            },

            get attributeSum() {
                return this.attributeKeys.reduce((sum, key) => sum + this.attributeValue(key), 0);
            },

            get attributeAverage() {
                if (this.attributeKeys.length === 0) {
                    return 0;
                }

                return this.attributeSum / this.attributeKeys.length;
            },

            get averageFormatted() {
                return `${this.attributeAverage.toFixed(1)} %`;
            },

            get averageValid() {
                return this.attributeAverage <= this.averageMax;
            },

            get averageProgress() {
                if (this.averageMax <= 0) {
                    return 0;
                }

                return Math.min(100, (this.attributeAverage / this.averageMax) * 100);
            },

            get speciesModifiers() {
                return this.selectedSpecies?.modifiers ?? {};
            },

            get effectiveAttributes() {
                const effective = {};

                this.attributeKeys.forEach((key) => {
                    const modifier = toInt(this.speciesModifiers[key], 0);
                    effective[key] = this.attributeValue(key) + modifier;
                });

                return effective;
            },

            get callingRequirementEntries() {
                return Object.entries(this.callingMinimums).map(([key, requiredValue]) => {
                    const currentValue = toInt(this.effectiveAttributes[key], 0);
                    const required = toInt(requiredValue, 0);

                    return {
                        key,
                        label: this.attributeLabel(key),
                        current: currentValue,
                        required,
                        met: currentValue >= required,
                    };
                });
            },

            get callingRequirementsValid() {
                return this.callingRequirementEntries.every((entry) => entry.met);
            },

            get callingBonuses() {
                return this.selectedCalling?.bonuses ?? {};
            },

            get leBase() {
                const ko = toInt(this.effectiveAttributes.ko, 0);
                const kk = toInt(this.effectiveAttributes.kk, 0);
                const mu = toInt(this.effectiveAttributes.mu, 0);

                return Math.round((ko + kk + mu) / 3);
            },

            get aeBase() {
                const kl = toInt(this.effectiveAttributes.kl, 0);
                const intuition = toInt(this.effectiveAttributes.in, 0);
                const ch = toInt(this.effectiveAttributes.ch, 0);

                return Math.round((kl + intuition + ch) / 3);
            },

            get leMax() {
                const speciesBonus = toInt(this.selectedSpecies?.le_bonus, 0);
                const callingFlat = toInt(this.callingBonuses.le_flat, 0);

                return Math.max(1, this.leBase + speciesBonus + callingFlat);
            },

            get aeMax() {
                const speciesBonus = toInt(this.selectedSpecies?.ae_bonus, 0);
                const callingFlat = toInt(this.callingBonuses.ae_flat, 0);
                const callingPercent = toInt(this.callingBonuses.ae_percent, 0);
                const percentBonus = Math.round(this.aeBase * (callingPercent / 100));

                return Math.max(0, this.aeBase + speciesBonus + callingFlat + percentBonus);
            },

            get traitsValid() {
                const advantageCount = this.advantages.length;
                const disadvantageCount = this.disadvantages.length;

                if (advantageCount !== disadvantageCount) {
                    return false;
                }

                if (advantageCount < this.traitsMin || advantageCount > this.traitsMax) {
                    return false;
                }

                if (disadvantageCount < this.traitsMin || disadvantageCount > this.traitsMax) {
                    return false;
                }

                return true;
            },

            addTrait(type) {
                const key = type === 'disadvantages' ? 'disadvantages' : 'advantages';

                if (this[key].length >= this.traitsMax) {
                    return;
                }

                this[key].push('');
            },

            removeTrait(type, index) {
                const key = type === 'disadvantages' ? 'disadvantages' : 'advantages';

                if (this[key].length <= this.traitsMin) {
                    return;
                }

                this[key].splice(index, 1);
            },

            formatSpeciesModifiers(speciesKey) {
                const species = this.speciesOptions[speciesKey] ?? null;

                if (!species) {
                    return '';
                }

                const parts = [];
                const modifiers = species.modifiers ?? {};

                Object.entries(modifiers).forEach(([key, value]) => {
                    const label = this.attributeLabel(key);
                    const amount = toInt(value, 0);
                    const sign = amount >= 0 ? '+' : '';
                    parts.push(`${sign}${amount} ${label}`);
                });

                const leBonus = toInt(species.le_bonus, 0);
                const aeBonus = toInt(species.ae_bonus, 0);

                if (leBonus !== 0) {
                    parts.push(`${leBonus >= 0 ? '+' : ''}${leBonus} LE`);
                }

                if (aeBonus !== 0) {
                    parts.push(`${aeBonus >= 0 ? '+' : ''}${aeBonus} AE`);
                }

                return parts.length ? parts.join(' | ') : 'Keine Modifikatoren';
            },
        };
    };
})();
