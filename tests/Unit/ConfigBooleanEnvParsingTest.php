<?php

namespace Tests\Unit;

use Tests\TestCase;

class ConfigBooleanEnvParsingTest extends TestCase
{
    public function test_feature_flags_parse_string_booleans_consistently(): void
    {
        $config = $this->loadConfigWithEnv('config/features.php', [
            'FEATURE_WAVE3_EDITOR_PREVIEW' => 'off',
            'FEATURE_WAVE3_DRAFT_AUTOSAVE' => 'on',
            'FEATURE_WAVE4_MENTIONS' => 'no',
            'FEATURE_WAVE4_REACTIONS' => 'yes',
            'FEATURE_WAVE4_ACTIVE_CHARACTERS' => '0',
        ]);

        $this->assertFalse($config['wave3']['editor_preview']);
        $this->assertTrue($config['wave3']['draft_autosave']);
        $this->assertFalse($config['wave4']['mentions']);
        $this->assertTrue($config['wave4']['reactions']);
        $this->assertFalse($config['wave4']['active_characters_week']);
    }

    public function test_content_and_outbox_flags_parse_string_booleans_consistently(): void
    {
        $content = $this->loadConfigWithEnv('config/content.php', [
            'WORLD_MARKDOWN_PREVIEW' => 'off',
        ]);
        $outbox = $this->loadConfigWithEnv('config/outbox.php', [
            'OUTBOX_SPIKE_LOG_CANDIDATES' => 'yes',
        ]);

        $this->assertFalse($content['world_markdown_preview']);
        $this->assertTrue($outbox['spike_log_candidates']);
    }

    public function test_privacy_flags_fall_back_to_defaults_for_invalid_tokens(): void
    {
        $config = $this->loadConfigWithEnv('config/privacy.php', [
            'PRIVACY_NOINDEX_HEADERS' => 'definitely-not-a-boolean',
            'PRIVACY_BLOCK_KNOWN_BOTS' => 'definitely-not-a-boolean',
            'PRIVACY_ALLOW_LINK_PREVIEW_BOTS' => 'definitely-not-a-boolean',
        ]);

        $this->assertTrue($config['send_noindex_headers']);
        $this->assertTrue($config['block_known_bots']);
        $this->assertTrue($config['allow_link_preview_bots']);
    }

    public function test_app_debug_parses_off_and_on_tokens(): void
    {
        $debugOff = $this->loadConfigWithEnv('config/app.php', [
            'APP_DEBUG' => 'off',
        ]);
        $debugOn = $this->loadConfigWithEnv('config/app.php', [
            'APP_DEBUG' => 'on',
        ]);

        $this->assertFalse($debugOff['debug']);
        $this->assertTrue($debugOn['debug']);
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, mixed>
     */
    private function loadConfigWithEnv(string $configPath, array $values): array
    {
        /** @var array<string, string|null> $previous */
        $previous = [];
        foreach ($values as $key => $value) {
            $currentValue = getenv($key);
            $previous[$key] = $currentValue === false ? null : (string) $currentValue;
            $this->setEnvironmentVariable($key, $value);
        }

        try {
            /** @var array<string, mixed> $config */
            $config = require base_path($configPath);

            return $config;
        } finally {
            foreach ($previous as $key => $value) {
                $this->setEnvironmentVariable($key, $value);
            }
        }
    }

    private function setEnvironmentVariable(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv(sprintf('%s=%s', $key, $value));
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
