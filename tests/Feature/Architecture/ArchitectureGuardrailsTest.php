<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class ArchitectureGuardrailsTest extends TestCase
{
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
