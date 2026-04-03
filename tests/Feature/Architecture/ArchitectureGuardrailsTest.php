<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class ArchitectureGuardrailsTest extends TestCase
{
    public function test_controllers_do_not_use_transactions_or_row_locks(): void
    {
        $violations = [];

        foreach ($this->phpFiles(app_path('Http/Controllers')) as $file) {
            $tokens = token_get_all((string) file_get_contents($file));

            foreach ($this->findControllerDbViolations($tokens, $file) as $violation) {
                $violations[] = $violation;
            }
        }

        $this->assertSame([], $violations, implode(PHP_EOL, $violations));
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
    private function findControllerDbViolations(array $tokens, string $file): array
    {
        $violations = [];

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];

            if (! is_array($token) || $token[0] !== T_STRING) {
                continue;
            }

            $value = strtolower($token[1]);

            if ($value === 'db') {
                $doubleColonIndex = $this->nextMeaningfulTokenIndex($tokens, $index + 1);
                $callIndex = $doubleColonIndex !== null
                    ? $this->nextMeaningfulTokenIndex($tokens, $doubleColonIndex + 1)
                    : null;
                $doubleColon = $doubleColonIndex !== null ? $this->tokenText($tokens[$doubleColonIndex]) : '';
                $callName = $callIndex !== null ? strtolower($this->tokenText($tokens[$callIndex])) : '';

                if ($doubleColon === '::' && in_array($callName, ['transaction', 'begintransaction'], true)) {
                    $violations[] = sprintf(
                        '%s:%d uses forbidden DB::%s call in controller layer.',
                        $file,
                        $token[2],
                        $callName,
                    );
                }
            }

            if (! in_array($value, ['begintransaction', 'lockforupdate'], true)) {
                continue;
            }

            $previousIndex = $this->previousMeaningfulTokenIndex($tokens, $index - 1);
            $previousText = $previousIndex !== null ? strtolower($this->tokenText($tokens[$previousIndex])) : '';

            if ($previousText === 'function') {
                continue;
            }

            $violations[] = sprintf(
                '%s:%d uses forbidden %s call in controller layer.',
                $file,
                $token[2],
                $value,
            );
        }

        return $violations;
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

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo) {
                continue;
            }

            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }
}
