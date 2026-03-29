<?php

namespace App\Support;

class PushNarrativeTextResolver
{
    /**
     * @param  array<string, scalar|null>  $context
     * @param  array{title?: string, body?: string, action_label?: string}  $fallback
     * @return array{title: string, body: string, action_label: string}
     */
    public function resolve(string $kind, string $worldSlug, array $context = [], array $fallback = []): array
    {
        $template = $this->resolveTemplate($kind, $worldSlug);
        $replacements = $this->buildReplacements($context);

        $titleTemplate = (string) ($template['title'] ?? ($fallback['title'] ?? 'C76-RPG'));
        $bodyTemplate = (string) ($template['body'] ?? ($fallback['body'] ?? 'Neue Nachricht.'));
        $actionLabelTemplate = (string) ($template['action_label'] ?? ($fallback['action_label'] ?? 'Oeffnen'));

        return [
            'title' => $this->renderTemplate($titleTemplate, $replacements),
            'body' => $this->renderTemplate($bodyTemplate, $replacements),
            'action_label' => $this->renderTemplate($actionLabelTemplate, $replacements),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resolveTemplate(string $kind, string $worldSlug): array
    {
        $defaultTemplate = (array) config("push_narrative.default.{$kind}", []);
        $normalizedWorldSlug = $this->normalizeWorldSlug($worldSlug);
        $worldTemplate = (array) config("push_narrative.worlds.{$normalizedWorldSlug}.{$kind}", []);

        return array_replace($defaultTemplate, $worldTemplate);
    }

    private function normalizeWorldSlug(string $worldSlug): string
    {
        $normalized = trim(strtolower($worldSlug));

        if ($normalized === '' || preg_match('/^[a-z0-9-]+$/', $normalized) !== 1) {
            return 'default';
        }

        return $normalized;
    }

    /**
     * @param  array<string, scalar|null>  $context
     * @return array<string, string>
     */
    private function buildReplacements(array $context): array
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $cleanKey = trim(strtolower($key));

            if ($cleanKey === '' || preg_match('/^[a-z0-9_]+$/', $cleanKey) !== 1) {
                continue;
            }

            $replacements[':'.$cleanKey] = $this->normalizeText((string) ($value ?? ''));
        }

        return $replacements;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function renderTemplate(string $template, array $replacements): string
    {
        $rendered = strtr($template, $replacements);

        return $this->normalizeText($rendered);
    }

    private function normalizeText(string $value): string
    {
        $singleLine = preg_replace('/\s+/u', ' ', trim($value));

        if (! is_string($singleLine)) {
            return '';
        }

        return $singleLine;
    }
}
