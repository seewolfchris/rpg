const PROBE_TOKEN_PATTERN = /^[a-f0-9]{64}$/i;

export function hasEnabledProbe(form) {
    const control = form?.querySelector?.('input[name="probe_enabled"]');

    return Boolean(control?.checked);
}

export function hasValidProbeToken(form) {
    const control = form?.querySelector?.('input[name="probe_roll_token"]');
    const value = typeof control?.value === 'string' ? control.value.trim() : '';

    return PROBE_TOKEN_PATTERN.test(value);
}

export function probeSubmissionNeedsPreview(form) {
    return hasEnabledProbe(form) && !hasValidProbeToken(form);
}
