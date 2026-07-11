<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostDeploySecurityGuardTest extends TestCase
{
    public function test_deploy_guard_fails_when_queue_after_commit_is_empty(): void
    {
        $result = $this->runPostDeployScript($this->validProductionEnvironment([
            'QUEUE_AFTER_COMMIT' => '',
        ]));

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString(
            'QUEUE_AFTER_COMMIT fehlt, ist leer oder deaktiviert.',
            $result['output']
        );
    }

    public function test_deploy_guard_fails_when_session_secure_cookie_is_empty_in_production(): void
    {
        $result = $this->runPostDeployScript($this->validProductionEnvironment([
            'SESSION_SECURE_COOKIE' => '',
        ]));

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString(
            'SESSION_SECURE_COOKIE fehlt, ist leer oder in Produktion deaktiviert.',
            $result['output']
        );
    }

    #[DataProvider('productionEnvironmentProvider')]
    public function test_deploy_guard_requires_trusted_proxies_for_prod_and_production(string $appEnv): void
    {
        $result = $this->runPostDeployScript($this->validProductionEnvironment([
            'APP_ENV' => $appEnv,
            'TRUSTED_PROXIES' => '',
        ]));

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString(
            'TRUSTED_PROXIES fehlt in Produktion.',
            $result['output']
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function productionEnvironmentProvider(): array
    {
        return [
            'production' => ['production'],
            'prod' => ['prod'],
        ];
    }

    public function test_deploy_guard_requires_redis_queue_connection(): void
    {
        $result = $this->runPostDeployScript($this->validProductionEnvironment([
            'QUEUE_CONNECTION' => 'database',
        ]));

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString(
            'QUEUE_CONNECTION=database ist fuer Produktion nicht zulaessig.',
            $result['output']
        );
    }

    public function test_deploy_guard_requires_redis_cache_store(): void
    {
        $result = $this->runPostDeployScript($this->validProductionEnvironment([
            'CACHE_STORE' => 'database',
        ]));

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString(
            'CACHE_STORE=database ist fuer Produktion nicht zulaessig.',
            $result['output']
        );
    }

    public function test_deploy_guard_rejects_non_production_environment(): void
    {
        $result = $this->runPostDeployScript($this->validProductionEnvironment([
            'APP_ENV' => 'local',
        ]));

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString(
            'APP_ENV=local ist fuer dieses Produktions-Deploy nicht zulaessig.',
            $result['output']
        );
    }

    public function test_deploy_guard_requires_disabled_app_debug(): void
    {
        $result = $this->runPostDeployScript($this->validProductionEnvironment([
            'APP_DEBUG' => 'true',
        ]));

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString(
            'APP_DEBUG fehlt oder ist fuer ein Produktions-Deploy aktiviert.',
            $result['output']
        );
    }

    public function test_deploy_guard_requires_https_app_url(): void
    {
        $result = $this->runPostDeployScript($this->validProductionEnvironment([
            'APP_URL' => 'http://rpg.c76.org',
        ]));

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString(
            'APP_URL fehlt, ist ungueltig oder verwendet kein HTTPS.',
            $result['output']
        );
    }

    public function test_deploy_guard_requires_non_empty_webpush_endpoint_allowlist(): void
    {
        $result = $this->runPostDeployScript($this->validProductionEnvironment([
            'WEBPUSH_ENDPOINT_ALLOWED_HOSTS' => ' , , ',
        ]));

        $this->assertSame(1, $result['exit_code']);
        $this->assertStringContainsString(
            'WEBPUSH_ENDPOINT_ALLOWED_HOSTS fehlt oder enthaelt keine Hosts.',
            $result['output']
        );
    }

    public function test_deploy_guard_requires_positive_numeric_hsts_max_age(): void
    {
        foreach (['', 'disabled', '0'] as $invalidMaxAge) {
            $result = $this->runPostDeployScript($this->validProductionEnvironment([
                'SECURITY_HSTS_MAX_AGE' => $invalidMaxAge,
            ]));

            $this->assertSame(1, $result['exit_code']);
            $this->assertStringContainsString(
                'SECURITY_HSTS_MAX_AGE fehlt, ist ungueltig oder in Produktion <= 0.',
                $result['output']
            );
        }
    }

    public function test_deploy_guard_accepts_complete_production_configuration(): void
    {
        $result = $this->runPostDeployScript($this->validProductionEnvironment());

        $this->assertSame(0, $result['exit_code'], $result['output']);
        $this->assertStringContainsString('Deploy abgeschlossen.', $result['output']);
    }

    /**
     * @param  list<string>  $envLines
     * @return array{exit_code: int, output: string}
     */
    private function runPostDeployScript(array $envLines): array
    {
        $projectRoot = $this->createStubProjectRoot($envLines);

        try {
            $process = new Process(
                ['/bin/bash', 'scripts/post_deploy.sh'],
                $projectRoot,
                [
                    'PHP_BIN' => $projectRoot.'/php-stub.sh',
                    'COMPOSER_PATH' => $projectRoot.'/composer-stub.sh',
                ],
            );

            $process->run();

            return [
                'exit_code' => $process->getExitCode() ?? 1,
                'output' => $process->getOutput().$process->getErrorOutput(),
            ];
        } finally {
            $this->deleteDirectoryRecursive($projectRoot);
        }
    }

    /**
     * @param  array<string, string>  $overrides
     * @return list<string>
     */
    private function validProductionEnvironment(array $overrides = []): array
    {
        $variables = array_replace([
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://rpg.c76.org',
            'APP_KEY' => 'base64:guard-test-key',
            'QUEUE_CONNECTION' => 'redis',
            'CACHE_STORE' => 'redis',
            'QUEUE_AFTER_COMMIT' => 'true',
            'SESSION_SECURE_COOKIE' => 'true',
            'TRUSTED_PROXIES' => '127.0.0.1',
            'SECURITY_HSTS_MAX_AGE' => '31536000',
            'WEBPUSH_ENDPOINT_ALLOWED_HOSTS' => 'fcm.googleapis.com,*.push.services.mozilla.com',
        ], $overrides);

        $lines = [];

        foreach ($variables as $key => $value) {
            $lines[] = $key.'='.$value;
        }

        return $lines;
    }

    /**
     * @param  list<string>  $envLines
     */
    private function createStubProjectRoot(array $envLines): string
    {
        $projectRoot = sys_get_temp_dir().'/c76-post-deploy-guard-'.bin2hex(random_bytes(6));
        $scriptsDir = $projectRoot.'/scripts';
        $buildDir = $projectRoot.'/public/build';

        mkdir($scriptsDir, 0777, true);
        mkdir($buildDir, 0777, true);

        copy(base_path('scripts/post_deploy.sh'), $scriptsDir.'/post_deploy.sh');
        chmod($scriptsDir.'/post_deploy.sh', 0755);

        file_put_contents($projectRoot.'/.env', implode(PHP_EOL, $envLines).PHP_EOL);
        file_put_contents($buildDir.'/manifest.json', '{}');
        file_put_contents($projectRoot.'/artisan', "#!/usr/bin/env php\n<?php\n");
        chmod($projectRoot.'/artisan', 0755);

        $phpStub = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail

if [[ "${1:-}" == "-r" ]]; then
  expression="${2:-}"

  if [[ "$expression" == *"echo PHP_VERSION"* ]]; then
    printf '8.5.4'
    exit 0
  fi

  if [[ "$expression" == *"version_compare(PHP_VERSION, \"8.5.0\", \">=\")"* ]]; then
    exit 0
  fi

  exit 0
fi

if [[ "${1:-}" == "artisan" ]]; then
  command="${2:-}"

  case "$command" in
    list)
      echo "perf:posts-latest-by-id-benchmark"
      exit 0
      ;;
    perf:posts-latest-by-id-benchmark|migrate|storage:link|optimize:clear|config:cache|route:cache|view:cache)
      exit 0
      ;;
    *)
      exit 0
      ;;
  esac
fi

exit 0
BASH;

        file_put_contents($projectRoot.'/php-stub.sh', $phpStub.PHP_EOL);
        chmod($projectRoot.'/php-stub.sh', 0755);

        $composerStub = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
exit 0
BASH;

        file_put_contents($projectRoot.'/composer-stub.sh', $composerStub.PHP_EOL);
        chmod($projectRoot.'/composer-stub.sh', 0755);

        return $projectRoot;
    }

    private function deleteDirectoryRecursive(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.'/'.$item;

            if (is_dir($path)) {
                $this->deleteDirectoryRecursive($path);

                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
