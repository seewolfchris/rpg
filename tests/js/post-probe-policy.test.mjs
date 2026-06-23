import assert from 'node:assert/strict';
import test from 'node:test';

import {
    hasEnabledProbe,
    hasValidProbeToken,
    probeSubmissionNeedsPreview,
} from '../../resources/js/app/post-probe-policy.js';

function fakeForm({ probeEnabled = false, token = '' } = {}) {
    return {
        querySelector(selector) {
            if (selector === 'input[name="probe_enabled"]') {
                return { checked: probeEnabled };
            }

            if (selector === 'input[name="probe_roll_token"]') {
                return token === null ? null : { value: token };
            }

            return null;
        },
    };
}

test('normal posts remain eligible for offline queueing without a probe token', () => {
    const form = fakeForm({ probeEnabled: false, token: '' });

    assert.equal(hasEnabledProbe(form), false);
    assert.equal(probeSubmissionNeedsPreview(form), false);
});

test('enabled probes require a current 64 character preview token', () => {
    const form = fakeForm({ probeEnabled: true, token: '' });

    assert.equal(hasEnabledProbe(form), true);
    assert.equal(hasValidProbeToken(form), false);
    assert.equal(probeSubmissionNeedsPreview(form), true);
});

test('enabled probes with a valid preview token pass the submit policy', () => {
    const form = fakeForm({ probeEnabled: true, token: 'a'.repeat(64) });

    assert.equal(hasValidProbeToken(form), true);
    assert.equal(probeSubmissionNeedsPreview(form), false);
});

test('malformed or missing token controls do not satisfy the policy', () => {
    assert.equal(hasValidProbeToken(fakeForm({ probeEnabled: true, token: 'not-a-token' })), false);
    assert.equal(hasValidProbeToken(fakeForm({ probeEnabled: true, token: null })), false);
});
