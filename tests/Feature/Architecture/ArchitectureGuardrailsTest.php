<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class ArchitectureGuardrailsTest extends TestCase
{
    /**
     * Non-final Actions must be explicitly read-only/query/render helpers.
     *
     * @var list<class-string>
     */
    private const READ_ONLY_NON_FINAL_ACTIONS = [
        \App\Actions\CampaignGmContact\BuildCampaignGmContactPanelDataAction::class,
        \App\Actions\Character\BuildCharacterCreateDataAction::class,
        \App\Actions\Character\BuildCharacterEditDataAction::class,
        \App\Actions\Character\BuildCharacterIndexDataAction::class,
        \App\Actions\Character\BuildCharacterShowDataAction::class,
        \App\Actions\Knowledge\LoadActiveWorldCatalogAction::class,
        \App\Actions\Post\ApplyPostModerationFiltersAction::class,
        \App\Actions\Post\BuildPostThreadItemFragmentAction::class,
        \App\Actions\Scene\BuildSceneShowNavigationDataAction::class,
        \App\Actions\Scene\BuildSceneShowPanelDataAction::class,
        \App\Actions\Scene\BuildSceneShowThreadDataAction::class,
        \App\Actions\Scene\BuildSceneThreadPageDataAction::class,
        \App\Actions\Scene\ResolveSceneJumpRedirectAction::class,
    ];

    public function test_mutating_route_closure_detector_flags_mutating_route_closure(): void
    {
        $tokens = token_get_all(<<<'PHP'
<?php

Route::post('/example', function (): void {
    // violation
});
PHP);

        $violations = $this->findMutatingRouteClosureViolations($tokens, 'sample.php');

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('Route::post', $violations[0]);
    }

    public function test_mutating_route_closure_detector_ignores_controller_handler(): void
    {
        $tokens = token_get_all(<<<'PHP'
<?php

Route::post('/example', [SampleController::class, 'store']);
PHP);

        $violations = $this->findMutatingRouteClosureViolations($tokens, 'sample.php');

        $this->assertSame([], $violations);
    }

    public function test_authenticated_routes_do_not_define_mutating_closure_routes(): void
    {
        $violations = [];
        $files = array_merge(
            [base_path('routes/web/authenticated.php')],
            glob(base_path('routes/web/auth/*.php')) ?: [],
        );

        foreach ($files as $file) {
            $tokens = token_get_all((string) file_get_contents($file));

            foreach ($this->findMutatingRouteClosureViolations($tokens, $file) as $violation) {
                $violations[] = $violation;
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    public function test_action_finality_detector_flags_non_final_unclassified_action(): void
    {
        $tokens = token_get_all(<<<'PHP'
<?php

namespace App\Actions\Sample;

class StoreSampleAction
{
}
PHP);

        $violations = $this->findNonFinalActionViolations($tokens, 'sample.php');

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('App\\Actions\\Sample\\StoreSampleAction', $violations[0]);
    }

    public function test_action_finality_detector_accepts_allowlisted_read_only_action(): void
    {
        $tokens = token_get_all(<<<'PHP'
<?php

namespace App\Actions\Scene;

class BuildSceneThreadPageDataAction
{
}
PHP);

        $violations = $this->findNonFinalActionViolations($tokens, 'sample.php');

        $this->assertSame([], $violations);
    }

    public function test_app_actions_are_final_unless_explicitly_read_only(): void
    {
        $violations = [];

        foreach ($this->actionFiles() as $file) {
            $tokens = token_get_all((string) file_get_contents($file));

            foreach ($this->findNonFinalActionViolations($tokens, $file) as $violation) {
                $violations[] = $violation;
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
    }

    /**
     * @return list<string>
     */
    private function findMutatingRouteClosureViolations(array $tokens, string $file): array
    {
        $violations = [];
        $mutatingMethods = ['post', 'patch', 'put', 'delete'];

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if (! is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'route') {
                continue;
            }

            $doubleColonIndex = $this->nextMeaningfulTokenIndex($tokens, $index + 1);
            if ($doubleColonIndex === null || $this->tokenText($tokens[$doubleColonIndex]) !== '::') {
                continue;
            }

            $methodIndex = $this->nextMeaningfulTokenIndex($tokens, $doubleColonIndex + 1);
            if ($methodIndex === null || ! is_array($tokens[$methodIndex]) || $tokens[$methodIndex][0] !== T_STRING) {
                continue;
            }

            $method = strtolower($this->tokenText($tokens[$methodIndex]));
            if (! in_array($method, $mutatingMethods, true)) {
                continue;
            }

            $openParenIndex = $this->nextMeaningfulTokenIndex($tokens, $methodIndex + 1);
            if ($openParenIndex === null || $this->tokenText($tokens[$openParenIndex]) !== '(') {
                continue;
            }

            $closureLine = $this->findClosureAsSecondArgument($tokens, $openParenIndex);
            if ($closureLine === null) {
                continue;
            }

            $violations[] = sprintf(
                '%s:%d defines mutating Route::%s with closure handler.',
                $file,
                $closureLine,
                $method,
            );
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function findNonFinalActionViolations(array $tokens, string $file): array
    {
        $violations = [];
        $namespace = '';

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespace($tokens, $index);

                continue;
            }

            if (! is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }

            $classNameIndex = $this->nextMeaningfulTokenIndex($tokens, $index + 1);
            if ($classNameIndex === null || ! is_array($tokens[$classNameIndex]) || $tokens[$classNameIndex][0] !== T_STRING) {
                continue;
            }

            $shortClassName = $this->tokenText($tokens[$classNameIndex]);
            if (! str_ends_with($shortClassName, 'Action')) {
                continue;
            }

            $fqcn = ltrim($namespace.'\\'.$shortClassName, '\\');
            if ($this->isClassFinal($tokens, $index) || in_array($fqcn, self::READ_ONLY_NON_FINAL_ACTIONS, true)) {
                continue;
            }

            $violations[] = sprintf(
                '%s:%d defines non-final Action %s without read-only allowlist entry.',
                $file,
                $this->tokenLine($token),
                $fqcn,
            );
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function actionFiles(): array
    {
        $root = base_path('app/Actions');
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (! $fileInfo instanceof \SplFileInfo || $fileInfo->getExtension() !== 'php') {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }

        sort($files);

        return $files;
    }

    private function readNamespace(array $tokens, int $namespaceIndex): string
    {
        $parts = [];

        for ($index = $namespaceIndex + 1; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            $text = $this->tokenText($token);

            if ($text === ';' || $text === '{') {
                break;
            }

            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $parts[] = $text;
            }
        }

        return implode('', $parts);
    }

    private function isClassFinal(array $tokens, int $classIndex): bool
    {
        $previousIndex = $this->previousMeaningfulTokenIndex($tokens, $classIndex - 1);

        return $previousIndex !== null
            && is_array($tokens[$previousIndex])
            && $tokens[$previousIndex][0] === T_FINAL;
    }

    private function findClosureAsSecondArgument(array $tokens, int $openParenIndex): ?int
    {
        $depth = 0;
        $seenCommaAtDepthOne = false;

        for ($index = $openParenIndex; $index < count($tokens); $index++) {
            $text = $this->tokenText($tokens[$index]);

            if ($text === '(') {
                $depth++;
            } elseif ($text === ')') {
                $depth--;

                if ($depth === 0) {
                    break;
                }
            }

            if ($depth !== 1) {
                continue;
            }

            if (! $seenCommaAtDepthOne && $text === ',') {
                $seenCommaAtDepthOne = true;

                continue;
            }

            if (! $seenCommaAtDepthOne) {
                continue;
            }

            $candidateIndex = $this->nextMeaningfulTokenIndex($tokens, $index);
            if ($candidateIndex === null) {
                return null;
            }

            if ($this->isClosureToken($tokens, $candidateIndex)) {
                return $this->tokenLine($tokens[$candidateIndex]);
            }

            return null;
        }

        return null;
    }

    private function isClosureToken(array $tokens, int $index): bool
    {
        $token = $tokens[$index];

        if (is_array($token) && in_array($token[0], [T_FUNCTION, T_FN], true)) {
            return true;
        }

        if (! is_array($token) || $token[0] !== T_STATIC) {
            return false;
        }

        $nextIndex = $this->nextMeaningfulTokenIndex($tokens, $index + 1);
        if ($nextIndex === null || ! is_array($tokens[$nextIndex])) {
            return false;
        }

        return in_array($tokens[$nextIndex][0], [T_FUNCTION, T_FN], true);
    }

    private function nextMeaningfulTokenIndex(array $tokens, int $startIndex): ?int
    {
        for ($index = $startIndex; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if (! is_array($token)) {
                return $index;
            }

            if (! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $index;
            }
        }

        return null;
    }

    private function previousMeaningfulTokenIndex(array $tokens, int $startIndex): ?int
    {
        for ($index = $startIndex; $index >= 0; $index--) {
            $token = $tokens[$index];

            if (! is_array($token)) {
                return $index;
            }

            if (! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $index;
            }
        }

        return null;
    }

    private function tokenText(mixed $token): string
    {
        if (is_array($token)) {
            return $token[1];
        }

        return (string) $token;
    }

    private function tokenLine(mixed $token): int
    {
        if (is_array($token)) {
            return (int) $token[2];
        }

        return 0;
    }
}
