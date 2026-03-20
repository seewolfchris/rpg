import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const alpineCspGlobalScanSnippet = `Object.getOwnPropertyNames(globalThis).forEach((key) => {
  if (key === "styleMedia")
    return;
  globals.add(globalThis[key]);
});`;

const alpineCspGlobalScanPatched = `const skippedGlobalKeys = new Set(["styleMedia", "fullScreen", "InstallTrigger", "onmozfullscreenchange", "onmozfullscreenerror"]);
Object.getOwnPropertyNames(globalThis).forEach((key) => {
  if (skippedGlobalKeys.has(key)) {
    return;
  }

  let value;
  try {
    value = globalThis[key];
  } catch {
    return;
  }

  globals.add(value);
});`;

function patchAlpineCspFirefoxDeprecatedGlobals() {
    return {
        name: 'suppress-firefox-deprecated-globals',
        enforce: 'pre',
        transform(code, id) {
            if (!id.includes('node_modules/@alpinejs/csp/dist/module.esm.js')) {
                return null;
            }

            if (!code.includes(alpineCspGlobalScanSnippet)) {
                return null;
            }

            return code.replace(alpineCspGlobalScanSnippet, alpineCspGlobalScanPatched);
        },
    };
}

function filterKnownThirdPartyEvalWarning(warning, warn) {
    if (
        warning.code === 'EVAL'
        && typeof warning.id === 'string'
        && warning.id.includes('node_modules/htmx.org/dist/htmx.esm.js')
    ) {
        return;
    }

    warn(warning);
}

export default defineConfig({
    plugins: [
        patchAlpineCspFirefoxDeprecatedGlobals(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    optimizeDeps: {
        include: ['alpinejs', 'htmx.org'],
        exclude: ['@alpinejs/csp'],
    },
    build: {
        rollupOptions: {
            onwarn: filterKnownThirdPartyEvalWarning,
        },
    },
});
