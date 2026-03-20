import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { join, relative } from 'node:path';
import test from 'node:test';

const PROJECT_ROOT = fileURLToPath(new URL('../../', import.meta.url));
const VIEWS_DIR = fileURLToPath(new URL('../../resources/views', import.meta.url));
const BOOTSTRAP_JS_PATH = fileURLToPath(new URL('../../resources/js/bootstrap.js', import.meta.url));

const RISKY_TEMPLATE_PATTERNS = [
    {
        name: 'hx-vars',
        regex: /\b(?:data-)?hx-vars\b/i,
    },
    {
        name: 'hx-on event handler',
        regex: /\b(?:data-)?hx-on(?::|=|\b)/i,
    },
    {
        name: 'hx-trigger condition filter ([] expression)',
        regex: /\b(?:data-)?hx-trigger\s*=\s*["'][^"']*\[[^"'\]]+\][^"']*["']/i,
    },
    {
        name: 'js: expression in hx-vals',
        regex: /\b(?:data-)?hx-vals\s*=\s*["']\s*(?:javascript|js):/i,
    },
    {
        name: 'js: expression in hx-headers',
        regex: /\b(?:data-)?hx-headers\s*=\s*["']\s*(?:javascript|js):/i,
    },
];

test('htmx bootstrap disables eval and script-tag execution', async () => {
    const source = await readFile(BOOTSTRAP_JS_PATH, 'utf8');

    assert.match(source, /htmx\.config\.allowEval\s*=\s*false/);
    assert.match(source, /htmx\.config\.allowScriptTags\s*=\s*false/);
    assert.match(source, /htmx\.config\.selfRequestsOnly\s*=\s*true/);
});

test('blade templates avoid eval-dependent htmx attributes', async () => {
    const bladeFiles = await collectBladeFiles(VIEWS_DIR);
    const findings = [];

    for (const bladeFile of bladeFiles) {
        const content = await readFile(bladeFile, 'utf8');

        for (const pattern of RISKY_TEMPLATE_PATTERNS) {
            if (!pattern.regex.test(content)) {
                continue;
            }

            findings.push(`${relativePath(bladeFile)}: ${pattern.name}`);
        }
    }

    assert.deepEqual(findings, []);
});

async function collectBladeFiles(rootDir) {
    const files = [];
    const stack = [rootDir];

    while (stack.length > 0) {
        const currentDir = stack.pop();
        const entries = await readdir(currentDir, { withFileTypes: true });

        for (const entry of entries) {
            const fullPath = join(currentDir, entry.name);

            if (entry.isDirectory()) {
                stack.push(fullPath);
                continue;
            }

            if (entry.isFile() && entry.name.endsWith('.blade.php')) {
                files.push(fullPath);
            }
        }
    }

    return files;
}

function relativePath(filePath) {
    return relative(PROJECT_ROOT, filePath);
}
