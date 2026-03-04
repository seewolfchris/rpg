import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(scriptDir, '..');
const sourcePath = resolve(projectRoot, 'resources/js/character-sheet.js');
const targetPath = resolve(projectRoot, 'public/js/character-sheet.global.js');

const source = readFileSync(sourcePath, 'utf8');

const transformed = source
    .replace(/^export function characterSheetForm/m, 'function characterSheetForm')
    .replace(/^export function registerCharacterSheetComponent/m, 'function registerCharacterSheetComponent');

if (/\bexport\s+/.test(transformed)) {
    throw new Error('Die Global-Datei kann nicht erzeugt werden: Unerwartete export-Anweisung gefunden.');
}

const indent = (value, size = 4) => {
    const padding = ' '.repeat(size);

    return value
        .split('\n')
        .map((line) => (line.length > 0 ? `${padding}${line}` : line))
        .join('\n');
};

const output = [
    '/* Auto-generated from resources/js/character-sheet.js. Do not edit directly. */',
    '(() => {',
    indent(transformed.trimEnd(), 4),
    '})();',
    '',
].join('\n');

writeFileSync(targetPath, output, 'utf8');
console.log(`Synced: ${targetPath}`);
