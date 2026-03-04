const MODE_ADVANTAGE = 'advantage';
const MODE_DISADVANTAGE = 'disadvantage';

const normalizeModifier = (value) => {
    const parsed = Number.parseInt(value, 10);

    if (Number.isNaN(parsed)) {
        return 0;
    }

    return parsed;
};

const randomRollValue = () => {
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
        const buffer = new Uint32Array(1);
        window.crypto.getRandomValues(buffer);

        return (buffer[0] % 100) + 1;
    }

    return Math.floor(Math.random() * 100) + 1;
};

const keepRoll = (mode, rolls) => {
    if (mode === MODE_ADVANTAGE) {
        return Math.max(...rolls);
    }

    if (mode === MODE_DISADVANTAGE) {
        return Math.min(...rolls);
    }

    return rolls[0];
};

const formatSigned = (value) => (value >= 0 ? `+${value}` : `${value}`);

const summarizeRoll = (rolls, keptRollValue, modifier, total, mode) => {
    const modeLabel =
        mode === MODE_ADVANTAGE ? 'Vorteil' : mode === MODE_DISADVANTAGE ? 'Nachteil' : 'Normal';
    const rollsLabel = `[${rolls.join(', ')}]`;
    const keepLabel = rolls.length > 1 ? ` -> ${keptRollValue}` : `${keptRollValue}`;

    return `Probe ${modeLabel}: ${rollsLabel}${keepLabel} ${formatSigned(modifier)} = ${total}`;
};

const renderLiveMessage = (element, message, variant = 'neutral') => {
    if (!element) {
        return;
    }

    const classesByVariant = {
        neutral: 'mt-4 rounded-md border border-stone-700/80 bg-black/35 px-3 py-2 text-sm text-stone-300',
        success: 'mt-4 rounded-md border border-emerald-700/80 bg-emerald-900/20 px-3 py-2 text-sm text-emerald-200',
        error: 'mt-4 rounded-md border border-red-700/80 bg-red-900/20 px-3 py-2 text-sm text-red-200',
    };

    element.className = classesByVariant[variant] ?? classesByVariant.neutral;
    element.textContent = message;
};

const resetDiceDefaults = (form) => {
    form.reset();

    const modifierField = form.querySelector('[name="dice_modifier"]');
    if (modifierField) {
        modifierField.value = '0';
    }

    const modeField = form.querySelector('[name="dice_roll_mode"]');
    if (modeField) {
        modeField.value = 'normal';
    }
};

const bindDiceForm = (form) => {
    if (form.dataset.diceBound === '1') {
        return;
    }

    form.dataset.diceBound = '1';

    const liveTarget = form.querySelector('[data-dice-live]');
    const submitButton = form.querySelector('[data-dice-submit]');
    const listId = form.dataset.diceLogTarget || '';

    form.addEventListener('submit', async (event) => {
        if (typeof window.fetch !== 'function') {
            return;
        }

        event.preventDefault();

        const formData = new FormData(form);
        const mode = String(formData.get('dice_roll_mode') ?? 'normal');
        const modifier = normalizeModifier(String(formData.get('dice_modifier') ?? '0'));

        const localRolls = [randomRollValue()];
        if (mode === MODE_ADVANTAGE || mode === MODE_DISADVANTAGE) {
            localRolls.push(randomRollValue());
        }

        const localKeptRoll = keepRoll(mode, localRolls);
        const localTotal = localKeptRoll + modifier;
        renderLiveMessage(liveTarget, `Lokal: ${summarizeRoll(localRolls, localKeptRoll, modifier, localTotal, mode)}`);

        if (submitButton) {
            submitButton.disabled = true;
        }

        const csrfToken = form.querySelector('input[name="_token"]')?.value;
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers,
                body: formData,
            });

            const payload = await response.json().catch(() => null);

            if (!response.ok) {
                if (response.status === 422 && payload && payload.errors) {
                    const firstError = Object.values(payload.errors)[0];
                    const message =
                        Array.isArray(firstError) && firstError.length > 0
                            ? String(firstError[0])
                            : 'Validierung fehlgeschlagen.';

                    renderLiveMessage(liveTarget, message, 'error');
                    return;
                }

                throw new Error('dice-roll-request-failed');
            }

            if (payload && typeof payload.html === 'string' && payload.html.trim() !== '' && listId) {
                const logList = document.getElementById(listId);

                if (logList) {
                    logList.insertAdjacentHTML('afterbegin', payload.html);
                }
            }

            const emptyState = document.querySelector('[data-dice-empty]');
            if (emptyState) {
                emptyState.remove();
            }

            if (payload && payload.roll) {
                const roll = payload.roll;
                const rolls = Array.isArray(roll.rolls) ? roll.rolls : [];
                const keptRollValue = Number.parseInt(String(roll.kept_roll), 10) || 0;
                const serverModifier = Number.parseInt(String(roll.modifier), 10) || 0;
                const total = Number.parseInt(String(roll.total), 10) || 0;
                const serverMode = String(roll.mode ?? 'normal');

                renderLiveMessage(
                    liveTarget,
                    `Gespeichert: ${summarizeRoll(rolls, keptRollValue, serverModifier, total, serverMode)}`,
                    'success',
                );
            } else {
                renderLiveMessage(liveTarget, 'Wurf gespeichert.', 'success');
            }

            resetDiceDefaults(form);
        } catch (error) {
            console.error(error);
            renderLiveMessage(liveTarget, 'Wurf konnte nicht gespeichert werden. Bitte erneut versuchen.', 'error');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });
};

export const initDiceRoller = () => {
    const forms = document.querySelectorAll('[data-dice-form]');
    forms.forEach(bindDiceForm);
};
