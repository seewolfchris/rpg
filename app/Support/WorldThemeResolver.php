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

        $cssVariables = $this->normalizeCssVariables((array) ($merged['css_variables'] ?? []));

        return [
            'world_slug' => $resolvedSlug,
            'theme_key' => (string) ($merged['theme_key'] ?? 'default'),
            'label' => (string) ($merged['label'] ?? 'Standardwelt'),
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
