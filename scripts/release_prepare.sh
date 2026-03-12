#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

usage() {
  cat <<'USAGE'
Usage:
  scripts/release_prepare.sh --version vX.XX-beta [--build <build>] [--update-dotenv] [--dry-run]

Examples:
  scripts/release_prepare.sh --version v0.22-beta
  scripts/release_prepare.sh --version v0.22-beta --build "$(git rev-parse --short HEAD)"
  scripts/release_prepare.sh --version v0.22-beta --update-dotenv
USAGE
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "ERROR: required command not found: $1"
    exit 1
  fi
}

VERSION=""
BUILD=""
UPDATE_DOTENV="0"
DRY_RUN="0"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --version)
      VERSION="${2:-}"
      shift 2
      ;;
    --build)
      BUILD="${2:-}"
      shift 2
      ;;
    --update-dotenv)
      UPDATE_DOTENV="1"
      shift
      ;;
    --dry-run)
      DRY_RUN="1"
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "ERROR: unknown argument '$1'"
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$VERSION" ]]; then
  echo "ERROR: --version is required."
  usage
  exit 1
fi

if ! [[ "$VERSION" =~ ^v[0-9]+(\.[0-9]+){1,2}(-[0-9A-Za-z._-]+)?$ ]]; then
  echo "ERROR: invalid version format '$VERSION' (expected e.g. v0.22-beta)"
  exit 1
fi

if [[ -z "$BUILD" ]]; then
  BUILD="$(git rev-parse --short HEAD 2>/dev/null || true)"
fi

if [[ -z "$BUILD" ]]; then
  echo "ERROR: could not resolve build hash. Pass --build explicitly."
  exit 1
fi

if ! [[ "$BUILD" =~ ^[0-9A-Za-z._-]+$ ]]; then
  echo "ERROR: invalid build value '$BUILD'"
  exit 1
fi

require_cmd php

export RELEASE_VERSION="$VERSION"
export RELEASE_BUILD="$BUILD"
export RELEASE_UPDATE_DOTENV="$UPDATE_DOTENV"
export RELEASE_DRY_RUN="$DRY_RUN"

php <<'PHP'
<?php

$projectRoot = getcwd();
$version = (string) getenv('RELEASE_VERSION');
$build = (string) getenv('RELEASE_BUILD');
$updateDotenv = (string) getenv('RELEASE_UPDATE_DOTENV') === '1';
$dryRun = (string) getenv('RELEASE_DRY_RUN') === '1';

$changed = [];
$unchanged = [];

$replaceRegex = static function (string $relativePath, string $pattern, string $replacement, string $label) use ($projectRoot, $dryRun, &$changed, &$unchanged): void {
    $path = $projectRoot.'/'.$relativePath;

    if (! is_file($path)) {
        fwrite(STDERR, "ERROR: file not found: {$relativePath}\n");
        exit(1);
    }

    $original = file_get_contents($path);

    if (! is_string($original)) {
        fwrite(STDERR, "ERROR: could not read file: {$relativePath}\n");
        exit(1);
    }

    $updated = preg_replace($pattern, $replacement, $original, -1, $count);

    if (! is_string($updated)) {
        fwrite(STDERR, "ERROR: regex failure for {$relativePath} ({$label})\n");
        exit(1);
    }

    if ($count === 0) {
        fwrite(STDERR, "ERROR: pattern not found in {$relativePath} ({$label})\n");
        exit(1);
    }

    if ($updated === $original) {
        $unchanged[] = $relativePath;

        return;
    }

    if (! $dryRun) {
        file_put_contents($path, $updated);
    }

    $changed[] = $relativePath;
};

$upsertEnvKey = static function (string $relativePath, string $key, string $value) use ($projectRoot, $dryRun, &$changed, &$unchanged): void {
    $path = $projectRoot.'/'.$relativePath;

    if (! is_file($path)) {
        return;
    }

    $original = file_get_contents($path);

    if (! is_string($original)) {
        fwrite(STDERR, "ERROR: could not read file: {$relativePath}\n");
        exit(1);
    }

    $pattern = '/^'.preg_quote($key, '/').'=.*/m';
    $replacement = $key.'='.$value;
    $updated = preg_replace($pattern, $replacement, $original, 1, $count);

    if (! is_string($updated)) {
        fwrite(STDERR, "ERROR: env replace failure in {$relativePath} for {$key}\n");
        exit(1);
    }

    if ($count === 0) {
        $updated = rtrim($original, "\n")."\n".$replacement."\n";
    }

    if ($updated === $original) {
        $unchanged[] = $relativePath;

        return;
    }

    if (! $dryRun) {
        file_put_contents($path, $updated);
    }

    $changed[] = $relativePath;
};

$replaceRegex('.env.example', '/^APP_VERSION=.*$/m', 'APP_VERSION='.$version, '.env.example APP_VERSION');
$replaceRegex('config/app.php', "/'version'\\s*=>\\s*env\\('APP_VERSION',\\s*'[^']*'\\),/", "'version' => env('APP_VERSION', '".$version."'),", 'config app.version fallback');
$replaceRegex('resources/views/layouts/auth.blade.php', "/config\\('app\\.version',\\s*'[^']*'\\)/", "config('app.version', '".$version."')", 'auth layout fallback version');
$replaceRegex('resources/views/partials/version-footer.blade.php', "/config\\('app\\.version',\\s*'[^']*'\\)/", "config('app.version', '".$version."')", 'footer fallback version');
$replaceRegex('README.md', '/Stand:\\s+\\*\\*Release-Beta\\s+`[^`]+`\\*\\*/', 'Stand: **Release-Beta `'.$version.'`**', 'README beta version line');
$replaceRegex('docs/PROJEKT-ÜBERSICHT.md', '/- Laufende Versionslinie:\\s+\\*\\*`[^`]+`\\*\\*\\./', '- Laufende Versionslinie: **`'.$version.'`**.', 'project overview version line');
$replaceRegex('docs/RELEASE-CHECKLISTE.md', '/\\(z\\. B\\. `[^`]+`\\)\\./', '(z. B. `'.$version.'`).', 'release checklist version example');

if ($updateDotenv) {
    $upsertEnvKey('.env', 'APP_VERSION', $version);
    $upsertEnvKey('.env', 'APP_BUILD', $build);
}

$changed = array_values(array_unique($changed));
sort($changed);

echo "Prepared release metadata.\n";
echo "- Version: {$version}\n";
echo "- Build: {$build}\n";
echo "- Dry run: ".($dryRun ? 'yes' : 'no')."\n";
echo "- Updated .env: ".($updateDotenv ? 'yes (if file exists)' : 'no')."\n";

if ($changed === []) {
    echo "- Changed files: none (already up to date)\n";
} else {
    echo "- Changed files:\n";

    foreach ($changed as $file) {
        echo "  - {$file}\n";
    }
}
PHP

echo
echo "Next steps:"
echo "  1) git diff --stat"
echo "  2) scripts/release_smoke.sh"
echo "  3) git add -A && git commit -m \"release: ${VERSION}\" && git push origin main"

