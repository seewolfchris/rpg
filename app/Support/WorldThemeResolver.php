<?php

namespace App\Support;

use App\Models\World;

class WorldThemeResolver
{
    /**
     * @return array{
     *   world_slug: string,
     *   theme_key: string,
     *   label: string,
     *   marker_label: string,
     *   marker_symbol: string,
     *   marker_bg: string,
     *   marker_fg: string,
     *   marker_border: string,
     *   theme_color: string,
     *   html_class: string,
     *   body_class: string,
     *   css_variables: array<string, string>,
     *   css_variable_style: string
     * }
     */
    public function resolve(?string $worldSlug): array
    {
        $resolvedSlug = $this->normalizeSlug($worldSlug);
        $default = (array) config('world_themes.default', []);
        $worldProfiles = (array) config('world_themes.worlds', []);
        $profile = (array) ($worldProfiles[$resolvedSlug] ?? []);
        $merged = $this->mergeProfile($default, $profile);

        $worldLabel = $this->normalizeMarkerText((string) ($merged['label'] ?? ''));
        if ($worldLabel === '') {
            $worldLabel = 'Standardwelt';
        }

        $markerLabel = $this->normalizeMarkerText((string) ($merged['marker_label'] ?? ''));
        if ($markerLabel === '') {
            $markerLabel = $this->buildShortLabel($worldLabel);
        }

        $markerSymbol = $this->normalizeMarkerText((string) ($merged['marker_symbol'] ?? ''));
        if ($markerSymbol === '') {
            $markerSymbol = 'WELT';
        }

        $cssVariables = $this->normalizeCssVariables((array) ($merged['css_variables'] ?? []));

        return [
            'world_slug' => $resolvedSlug,
            'theme_key' => (string) ($merged['theme_key'] ?? 'default'),
            'label' => $worldLabel,
            'marker_label' => $markerLabel,
            'marker_symbol' => $markerSymbol,
            'marker_bg' => $this->normalizeVisualToken((string) ($merged['marker_bg'] ?? ''), 'rgba(245, 158, 11, 0.16)'),
            'marker_fg' => $this->normalizeVisualToken((string) ($merged['marker_fg'] ?? ''), 'rgb(254, 243, 199)'),
            'marker_border' => $this->normalizeVisualToken((string) ($merged['marker_border'] ?? ''), 'rgba(217, 119, 6, 0.62)'),
            'theme_color' => (string) ($merged['theme_color'] ?? '#0f0f14'),
            'html_class' => (string) data_get($merged, 'classes.html', ''),
            'body_class' => (string) data_get($merged, 'classes.body', ''),
            'css_variables' => $cssVariables,
            'css_variable_style' => $this->buildCssVariableStyle($cssVariables),
        ];
    }

    private function normalizeSlug(?string $worldSlug): string
    {
        $slug = is_string($worldSlug) ? trim($worldSlug) : '';

        return $slug !== '' ? $slug : World::defaultSlug();
    }

    /**
     * @param  array<string, mixed>  $default
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function mergeProfile(array $default, array $profile): array
    {
        $merged = array_replace_recursive($default, $profile);
        $merged['css_variables'] = array_replace(
            (array) ($default['css_variables'] ?? []),
            (array) ($profile['css_variables'] ?? [])
        );

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function normalizeCssVariables(array $input): array
    {
        $result = [];

        foreach ($input as $name => $value) {
            $variable = $this->normalizeVariableName((string) $name);

            if ($variable === null) {
                continue;
            }

            $normalizedValue = trim(str_replace([';', '{', '}', '<', '>'], '', (string) $value));

            if ($normalizedValue === '') {
                continue;
            }

            $result[$variable] = $normalizedValue;
        }

        return $result;
    }

    private function normalizeVariableName(string $name): ?string
    {
        $candidate = trim($name);

        if ($candidate === '') {
            return null;
        }

        if (! str_starts_with($candidate, '--')) {
            $candidate = '--'.$candidate;
        }

        if (preg_match('/^--[a-z0-9-]+$/', $candidate) !== 1) {
            return null;
        }

        return $candidate;
    }

    private function normalizeMarkerText(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        if (! is_string($normalized)) {
            return '';
        }

        return $normalized;
    }

    private function buildShortLabel(string $label): string
    {
        $parts = preg_split('/[\s\-]+/u', trim($label), 3, PREG_SPLIT_NO_EMPTY);

        if (! is_array($parts) || $parts === []) {
            return 'STD';
        }

        $acronym = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $acronym .= mb_substr($part, 0, 1);

            if (mb_strlen($acronym) >= 3) {
                break;
            }
        }

        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/u', '', (string) mb_strtoupper($acronym)));

        if ($normalized !== '') {
            return $normalized;
        }

        $lettersOnly = strtoupper((string) preg_replace('/[^A-Z0-9]/u', '', (string) mb_strtoupper($label)));

        return $lettersOnly !== '' ? mb_substr($lettersOnly, 0, 3) : 'STD';
    }

    private function normalizeVisualToken(string $value, string $fallback): string
    {
        $clean = trim(str_replace([';', '{', '}', '<', '>'], '', $value));

        return $clean !== '' ? $clean : $fallback;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function buildCssVariableStyle(array $variables): string
    {
        if ($variables === []) {
            return '';
        }

        $chunks = [];

        foreach ($variables as $name => $value) {
            $chunks[] = $name.': '.$value;
        }

        return implode('; ', $chunks).';';
    }
}
